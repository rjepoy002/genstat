<?php
declare(strict_types=1);
require_once __DIR__.'/../tools/build-layout.php';
require_once __DIR__.'/files.php';
require_once __DIR__.'/workbook-storage.php';
require_once __DIR__.'/formula-engine.php';

function refresh_lock(string $root) {
    $lock = fopen($root.'/storage/template.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Cannot acquire template refresh lock.');
    return $lock;
}

function formula_signature(?array $cell): ?array {
    if ($cell === null || !($cell['formulaProtected'] ?? ($cell['formula'] !== null))) return null;
    return ['formula'=>$cell['formula'], 'attributes'=>$cell['formulaAttributes'], 'kind'=>$cell['formulaKind'] ?? ($cell['formulaAttributes']['t'] ?? 'ordinary')];
}

function input_signature(?array $cell, ?string $approval = null): ?array {
    $evidence = $approval ?? ($cell['evidence'] ?? null);
    if ($evidence === null) return null;
    return ['editable'=>$cell['editable'] ?? false, 'evidence'=>$evidence, 'type'=>$cell['inputType'] ?? null];
}

function refresh_snapshot(string $root, ?array $candidate = null): array {
    if (is_file($root.'/storage/refresh-in-progress.json')) throw new RuntimeException('An incomplete refresh requires recovery from its recorded backup before continuing.');
    $layoutFile = $root.'/config/layout.json';
    $oldRaw = is_file($layoutFile) ? file_get_contents($layoutFile) : null;
    $old = $oldRaw === null ? null : json_decode($oldRaw, true, 512, JSON_THROW_ON_ERROR);
    $sourceHash = hash_file('sha256', workbook_path($root));
    $inputHash = hash_file('sha256', $root.'/config/inputs.php');
    $zip = zip_read(workbook_path($root));
    foreach ($zip as $name=>$entry) {
        $raw = zip_text($zip, $name);
        if (strlen($raw) !== $entry['header']['size'] || sprintf('%u', crc32($raw)) !== sprintf('%u', $entry['header']['crc'])) throw new RuntimeException("Invalid ZIP CRC/length: $name");
        if (str_ends_with($name, '.xml') || str_ends_with($name, '.rels')) xml_doc($raw);
    }
    $next = $candidate ?? build_layout(workbook_path($root), $root.'/config/inputs.php');
    if ($sourceHash !== $next['sha256'] || $sourceHash !== hash_file('sha256', workbook_path($root)) || $inputHash !== hash_file('sha256', $root.'/config/inputs.php')) throw new RuntimeException('Source or input definitions changed while generating the plan. Close Excel and retry.');
    $report = ['version'=>1, 'oldTemplateHash'=>$old['sha256'] ?? null, 'newTemplateHash'=>$next['sha256'],
        'fingerprints'=>['layout'=>$oldRaw === null ? null : hash('sha256', $oldRaw), 'inputs'=>$inputHash, 'candidateLayout'=>hash('sha256', json_bytes($next)), 'drafts'=>[]],
        'formulaChanges'=>[], 'inputDefinitionChanges'=>[], 'inputDefinitionIssues'=>[], 'templateValueChanges'=>[], 'structureChanges'=>[], 'drafts'=>[], 'conflicts'=>[]];

    if ($old !== null && array_column($old['sheets'], 'name') !== array_column($next['sheets'], 'name')) $report['conflicts'][] = ['reason'=>'Worksheet identity changed. Draft coordinates cannot be mapped automatically.'];
    foreach ($next['sheets'] as $i=>$sheet) {
        $prior = $old['sheets'][$i] ?? ['cells'=>[]];
        $refs = array_unique(array_merge(array_keys($prior['cells']), array_keys($sheet['cells']), $i===0 ? array_keys($old['approvedInputDefinitions'] ?? []) : [], $i===0 ? array_keys($next['approvedInputDefinitions']) : []));
        foreach ($refs as $ref) {
            $before = $prior['cells'][$ref] ?? null; $after = $sheet['cells'][$ref] ?? null;
            $a = formula_signature($before); $b = formula_signature($after);
            if ($a !== $b) $report['formulaChanges'][] = ['sheet'=>$sheet['name'], 'cell'=>$ref, 'before'=>$a, 'after'=>$b];
            $a = input_signature($before, $i===0 ? ($old['approvedInputDefinitions'][$ref] ?? null) : null); $b = input_signature($after, $i===0 ? ($next['approvedInputDefinitions'][$ref] ?? null) : null);
            if ($a !== $b) $report['inputDefinitionChanges'][] = ['sheet'=>$sheet['name'], 'cell'=>$ref, 'before'=>$a, 'after'=>$b];
            if (($before['value'] ?? null) !== ($after['value'] ?? null) && formula_signature($before) === null && formula_signature($after) === null) {
                $report['templateValueChanges'][] = ['sheet'=>$sheet['name'], 'cell'=>$ref, 'before'=>$before['value'] ?? null, 'after'=>$after['value'] ?? null];
            }
        }
        foreach (['name','state','range','merges','rows','columns','settings','formulaRanges'] as $key) {
            if (($prior[$key] ?? null) !== ($sheet[$key] ?? null)) $report['structureChanges'][] = ['sheet'=>$sheet['name'], 'property'=>$key, 'before'=>$prior[$key] ?? null, 'after'=>$sheet[$key] ?? null];
        }
        foreach ($sheet['inputDefinitionIssues'] as $issue) $report['inputDefinitionIssues'][] = ['sheet'=>$sheet['name']] + $issue;
    }
    $migrations = [];
    $files = glob($root.'/storage/draft-*.json') ?: []; sort($files);
    foreach ($files as $file) {
        $name = basename($file); $raw = file_get_contents($file);
        $report['fingerprints']['drafts'][$name] = hash('sha256', $raw);
        try {
            $draft = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($draft) || !is_array($draft['values'] ?? null) || !is_int($draft['revision'] ?? null) || (!is_array($draft['sourceCells']??null)&&!in_array($draft['templateHash'] ?? null,[$old['sha256'] ?? null,$next['sha256']],true))) throw new RuntimeException('Invalid draft schema or draft does not match the previous/current template hash.');
            $conflicts = [];
            foreach ($draft['values'] as $ref=>$webValue) {
                $before = $old['sheets'][0]['cells'][$ref] ?? null;
                $after = $next['sheets'][0]['cells'][$ref] ?? null;
                $priorDefault=$draft['sourceCells'][$ref]['value']??$before['value']??null;
                $reason = null;
                if (!is_string($webValue) || strlen($webValue)>64 || ($webValue !== '' && (!preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/D', $webValue) || !is_finite((float)$webValue)))) $reason = 'Saved value is not a supported finite number or blank.';
                elseif (!$before || !$before['editable']) $reason = 'Saved value was not an approved input in the old layout.';
                elseif (!$after || !$after['editable'] || formula_signature($after) !== null) $reason = 'Saved input was removed, merged away, revoked, or became a formula.';
                elseif (($before['inputType'] ?? null) !== ($after['inputType'] ?? null)) $reason = 'Input type changed.';
                elseif ($priorDefault !== $after['value'] && $webValue !== $after['value']) $reason = 'Both the Excel default and web value differ. Explicit reconciliation is required.';
                elseif ($old['sheets'][0]['merges'] !== $next['sheets'][0]['merges']) {
                    $oldMerge = array_values(array_filter($old['sheets'][0]['merges'], fn($range)=>range_contains($range, $ref)));
                    $newMerge = array_values(array_filter($next['sheets'][0]['merges'], fn($range)=>range_contains($range, $ref)));
                    if ($oldMerge !== $newMerge) $reason = 'Saved input merge geometry changed.';
                }
                // The worksheet uses A for row labels, and rows 8/9 for area
                // headings. Changed context must not silently remap a draft.
                if ($reason === null) {
                    preg_match('/^([A-Z]+)([0-9]+)$/D', $ref, $m);
                    foreach (['A'.$m[2], $m[1].'8', $m[1].'9'] as $context) {
                        if (($old['sheets'][0]['cells'][$context]['value'] ?? '') !== ($next['sheets'][0]['cells'][$context]['value'] ?? '')) $reason = 'Row or column label changed around the saved input; coordinate mapping requires review.';
                    }
                }
                if ($reason !== null) $conflicts[] = ['draft'=>$name, 'cell'=>$ref, 'reason'=>$reason, 'webValue'=>$webValue, 'oldDefault'=>$before['value'] ?? null, 'newDefault'=>$after['value'] ?? null];
            }
            foreach($draft['formulas']??[] as $ref=>$formula){
                $before=$old['sheets'][0]['cells'][$ref]??null;$after=$next['sheets'][0]['cells'][$ref]??null;$origin=$draft['sourceCells'][$ref]??$before;$reason=null;
                if(!is_string($formula)||!str_starts_with($formula,'='))$reason='Invalid saved formula.';
                elseif(!$before||!$after)$reason='Saved formula coordinate was removed.';
                elseif($origin['formula']!==$after['formula']||(array_key_exists('attributes',$origin)?$origin['attributes']:($origin['formulaAttributes']??null))!==$after['formulaAttributes']||($origin['formula']===null&&$origin['value']!==$after['value']))$reason='The source of a saved web formula changed; manual reconciliation is required.';
                elseif(!$after['formulaProtected']&&!($after['editable']&&$after['value']===''))$reason='Saved formula is no longer permitted at this coordinate.';
                elseif(merged_owner($next['sheets'][0]['merges'],$ref)!==merged_owner($old['sheets'][0]['merges'],$ref))$reason='Saved formula merge ownership changed.';
                if($reason!==null)$conflicts[]=['draft'=>$name,'cell'=>$ref,'reason'=>$reason,'webFormula'=>$formula];
            }
            $report['conflicts'] = array_merge($report['conflicts'], $conflicts);
            $report['drafts'][] = ['file'=>$name, 'overrideCount'=>count($draft['values']), 'status'=>$conflicts ? 'conflict' : 'compatible', 'preservedValues'=>$draft['values'],'preservedFormulas'=>$draft['formulas']??[]];
            // Preserve every override, even one numerically equal to the new
            // default. It records the user's explicit web-entry choice.
            $draft['templateHash'] = $next['sha256']; $draft['revision']++;
            $draft['sourceCells']=[];foreach(array_unique(array_merge(array_keys($draft['values']),array_keys($draft['formulas']??[]))) as $ref)if(isset($next['sheets'][0]['cells'][$ref])){$c=$next['sheets'][0]['cells'][$ref];$draft['sourceCells'][$ref]=['value'=>$c['value'],'formula'=>$c['formula'],'attributes'=>$c['formulaAttributes']];}
            $draft['values'] = (object)$draft['values'];
            $migrations[$name] = $draft;
        } catch (Throwable $e) {
            $report['conflicts'][] = ['draft'=>$name, 'reason'=>$e->getMessage()];
            $report['drafts'][] = ['file'=>$name, 'status'=>'conflict'];
        }
    }
    $report['canApply'] = $report['conflicts'] === [];
    return ['report'=>$report, 'layout'=>$next, 'migrations'=>$migrations];
}

function refresh_review(string $root): array {
    $lock = refresh_lock($root);
    try {return refresh_snapshot($root)['report'];}
    finally {flock($lock, LOCK_UN); fclose($lock);}
}

function refresh_apply(string $root, array $review): array {
    $lock = refresh_lock($root);
    try {
        $snapshot = refresh_snapshot($root); $current = $snapshot['report'];
        // Recompute the report rather than trusting edited review data. This
        // also catches a draft, allowlist or workbook changed after review.
        if (json_bytes($review) !== json_bytes($current)) throw new RuntimeException('Review is stale or altered. Generate and review a new plan. No files were applied.');
        if (!$current['canApply']) throw new RuntimeException('Refresh has conflicts. No layout or drafts were changed. Resolve the report conflicts and generate a new plan.');
        $id = gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
        $backup = $root.'/storage/backups/'.$id;
        if (!mkdir($backup, 0777, true)) throw new RuntimeException('Cannot create backup directory.');
        $originals = [];
        if (is_file($root.'/config/layout.json')) $originals['config/layout.json'] = file_get_contents($root.'/config/layout.json');
        $originals['config/inputs.php'] = file_get_contents($root.'/config/inputs.php');
        foreach ($current['fingerprints']['drafts'] as $name=>$hash) $originals['storage/'.$name] = file_get_contents($root.'/storage/'.$name);
        foreach ($originals as $relative=>$raw) {
            $dest = $backup.'/'.$relative;
            if (!is_dir(dirname($dest)) && !mkdir(dirname($dest), 0777, true)) throw new RuntimeException('Cannot create backup path.');
            atomic_file_write($dest, $raw);
            if (hash_file('sha256', $dest) !== hash('sha256', $raw)) throw new RuntimeException('Backup verification failed.');
        }
        atomic_file_write($backup.'/review.json', json_bytes($current));
        atomic_file_write($backup.'/observed-template.xlsx', file_get_contents(workbook_path($root)));
        if (hash_file('sha256', workbook_path($root)) !== $current['newTemplateHash'] || hash_file('sha256', $backup.'/observed-template.xlsx') !== $current['newTemplateHash']) throw new RuntimeException('Workbook changed during refresh. Generate a new plan.');
        if (hash_file('sha256', $root.'/config/inputs.php') !== $current['fingerprints']['inputs'] || (is_file($root.'/config/layout.json') ? hash_file('sha256', $root.'/config/layout.json') : null) !== $current['fingerprints']['layout']) throw new RuntimeException('Configuration changed during refresh. Generate a new plan.');
        foreach ($current['fingerprints']['drafts'] as $name=>$hash) if (!is_file($root.'/storage/'.$name) || hash_file('sha256', $root.'/storage/'.$name) !== $hash) throw new RuntimeException('A draft changed during refresh. Generate a new plan.');
        $marker = $root.'/storage/refresh-in-progress.json';
        atomic_file_write($marker, json_bytes(['backup'=>$backup, 'createdAt'=>gmdate('c')]));
        $applied = [];
        try {
            foreach ($snapshot['migrations'] as $name=>$draft) {
                $relative = 'storage/'.$name;
                atomic_file_write($root.'/'.$relative, json_bytes($draft)); $applied[] = $relative;
            }
            atomic_file_write($root.'/config/layout.json', json_bytes($snapshot['layout'])); $applied[] = 'config/layout.json';
            if (!unlink($marker)) throw new RuntimeException('Cannot clear refresh marker.');
        } catch (Throwable $e) {
            foreach (array_reverse($applied) as $relative) {
                if (isset($originals[$relative])) atomic_file_write($root.'/'.$relative, $originals[$relative]);
                elseif (is_file($root.'/'.$relative)) unlink($root.'/'.$relative);
            }
            if (is_file($marker)) unlink($marker);
            throw new RuntimeException('Refresh failed; applied files rolled back. Backup: '.$backup, 0, $e);
        }
        return ['backup'=>$backup, 'migratedDrafts'=>count($snapshot['migrations']), 'templateHash'=>$current['newTemplateHash']];
    } finally {flock($lock, LOCK_UN); fclose($lock);}
}

