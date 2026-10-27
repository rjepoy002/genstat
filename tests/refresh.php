<?php
declare(strict_types=1);
require __DIR__.'/../lib/template-refresh.php';

function ok(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException('FAIL: '.$message);
    echo 'PASS: '.$message.PHP_EOL;
}
function rejected(callable $action, string $contains): bool {
    try {$action();} catch (RuntimeException $e) {return str_contains($e->getMessage(), $contains);}
    return false;
}
$project = dirname(__DIR__);
$testRoot = sys_get_temp_dir().'/genstat-refresh-'.bin2hex(random_bytes(8));
mkdir($testRoot);
function fixture(string $name): string {
    global $project, $testRoot;
    $root = $testRoot.'/'.$name; mkdir($root); mkdir($root.'/config'); mkdir($root.'/storage');
    copy($project.'/genstat.xlsx', $root.'/genstat.xlsx'); copy($project.'/config/inputs.php', $root.'/config/inputs.php');
    $layout = build_layout($root.'/genstat.xlsx', $root.'/config/inputs.php'); atomic_file_write($root.'/config/layout.json', json_bytes($layout));
    return $root;
}
function web_draft(string $root, array $values): string {
    $l = json_decode(file_get_contents($root.'/config/layout.json'), true);
    $file = $root.'/storage/draft-2026-06.json';
    atomic_file_write($file, json_bytes(['period'=>'2026-06', 'templateHash'=>$l['sha256'], 'revision'=>3, 'savedAt'=>'2026-10-26T01:00:00Z', 'values'=>(object)$values]));
    return $file;
}
function change_sheet(string $root, callable $change): void {
    $zip = zip_read($root.'/genstat.xlsx'); $doc = xml_doc(zip_text($zip, 'xl/worksheets/sheet1.xml'));
    $change($doc, xml_path($doc));
    atomic_file_write($root.'/genstat.xlsx', zip_write($zip, ['xl/worksheets/sheet1.xml'=>$doc->saveXML()]));
}
function set_number(DOMXPath $x, string $ref, string $value): void {$x->query('//s:c[@r="'.$ref.'"]/s:v')->item(0)->nodeValue = $value;}
function install_probe(string $root): string {
 global $project;mkdir($root.'/lib');mkdir($root.'/tools');copy($project.'/tools/build-layout.php',$root.'/tools/build-layout.php');
 foreach(['app.php','xlsx.php','files.php','workbook-policy.php','template-sync.php','sync-mapping.php','template-refresh.php','formula-auth.php','formula-edits.php','formula-engine.php','render.php'] as $name)copy($project.'/lib/'.$name,$root.'/lib/'.$name);
 file_put_contents($root.'/probe-state.php','<?php require __DIR__."/lib/app.php";try{assert_template();read_draft("2026-06");echo "compatible";}catch(Throwable $e){echo $e->getMessage();exit(2);}');return '"'.PHP_BINARY.'" "'.$root.'/probe-state.php"';
}

try {
    $root = fixture('compatible'); $file = web_draft($root, ['B11'=>'77', 'C39'=>'']); $oldDraft = file_get_contents($file); $oldLayout = file_get_contents($root.'/config/layout.json');
    change_sheet($root, function($d,$x){set_number($x,'Z10','123');$x->query('//s:c[@r="Q11"]/s:f')->item(0)->nodeValue = 'SUM(B11:P11)+1';});
    mkdir($root.'/lib');mkdir($root.'/tools');copy($project.'/tools/build-layout.php',$root.'/tools/build-layout.php');foreach(['app.php','xlsx.php','files.php','workbook-policy.php','template-sync.php','sync-mapping.php','template-refresh.php','formula-auth.php','formula-edits.php','formula-engine.php','render.php'] as $name)copy($project.'/lib/'.$name,$root.'/lib/'.$name);
    file_put_contents($root.'/state.php','<?php require __DIR__."/lib/app.php"; try{assert_template();echo read_draft("2026-06")["values"]["B11"];}catch(RuntimeException $e){echo $e->getMessage();exit(2);}');
    $cmd='"'.PHP_BINARY.'" "'.$root.'/state.php"';$output=[];exec($cmd,$output,$status);
    ok($status===0 && implode('',$output)==='77', 'Content-only Excel changes preserve layout mappings and reopen compatible web values without rebuilding');
    $report = refresh_review($root);
    ok($report['canApply'] && count($report['formulaChanges']) === 1 && count($report['templateValueChanges']) === 1, 'Review identifies formula and Excel value changes before applying');
    ok(file_get_contents($file) === $oldDraft && file_get_contents($root.'/config/layout.json') === $oldLayout, 'Planning changes neither drafts nor active layout');
    $hash = hash_file('sha256',$root.'/genstat.xlsx'); $result = refresh_apply($root,$report); $draft = json_decode(file_get_contents($file),true);
    ok($draft['values'] === ['B11'=>'77','C39'=>''] && $draft['revision'] === 4 && $draft['templateHash'] === $hash && $draft['savedAt'] === '2026-10-26T01:00:00Z', 'Compatible refresh preserves web overrides and explicit blanks, updates revision/hash');
    ok(file_get_contents($result['backup'].'/storage/draft-2026-06.json') === $oldDraft && file_get_contents($result['backup'].'/config/layout.json') === $oldLayout, 'Byte-for-byte draft/layout backups exist before replacement');
    ok(hash_file('sha256',$root.'/genstat.xlsx') === $hash, 'Refresh does not modify the Excel workbook');
    $output=[];exec($cmd,$output,$status);ok($status===0 && implode('',$output)==='77','Web helpers reopen preserved values after the reviewed synchronization');
    ok(rejected(fn()=>refresh_apply($root,$report),'stale or altered'), 'A previously applied review cannot be replayed');

    $root=fixture('external-cache');$oldLayout=file_get_contents($root.'/config/layout.json');$cmd=install_probe($root);
    $z=zip_read($root.'/genstat.xlsx');$doc=xml_doc(zip_text($z,'xl/externalLinks/externalLink1.xml'));$xp=xml_path($doc);$xp->query('//s:v')->item(0)->nodeValue='123456';atomic_file_write($root.'/genstat.xlsx',zip_write($z,['xl/externalLinks/externalLink1.xml'=>$doc->saveXML()]));
    $output=[];exec($cmd,$output,$status);ok($status===0&&implode('',$output)==='compatible'&&file_get_contents($root.'/config/layout.json')===$oldLayout,'External cache value changes do not trigger layout rebuilds or template-layout errors');

    $root=fixture('structural-block');$cmd=install_probe($root);change_sheet($root,fn($d,$x)=>$x->query('//s:dimension')->item(0)->setAttribute('ref','A1:AH99'));
    $output=[];exec($cmd,$output,$status);ok($status===0,'Compatible trailing range expansion refreshes automatically');

    $root=fixture('formula-migration');$file=web_draft($root,[]);$draft=json_decode(file_get_contents($file),true);$draft['formulas']=['C39'=>'=ROUND(D11/3,2)'];$draft['formulaAudit']=[['cell'=>'C39','newFormula'=>'=ROUND(D11/3,2)','editor'=>'fixture']];atomic_file_write($file,json_bytes($draft));
    change_sheet($root,fn($d,$x)=>$x->query('//s:pageMargins')->item(0)->setAttribute('left','0.4'));$report=refresh_review($root);ok($report['canApply'],'Compatible formula overlays can migrate through a reviewed structural change');
    $result=refresh_apply($root,$report);$migrated=json_decode(file_get_contents($file),true);ok($migrated['formulas']===$draft['formulas']&&$migrated['formulaAudit']===$draft['formulaAudit']&&is_file($result['backup'].'/storage/draft-2026-06.json'),'Formula text and audit history survive migration with a complete draft backup');

    $root=fixture('formula-conflict');$file=web_draft($root,[]);$draft=json_decode(file_get_contents($file),true);$draft['formulas']=['C39'=>'=1+1'];atomic_file_write($file,json_bytes($draft));$raw=file_get_contents($file);
    change_sheet($root,function($d,$x){$cell=$x->query('//s:c[@r="C39"]')->item(0);$f=$d->createElementNS($d->documentElement->namespaceURI,'f');$f->textContent='2+2';$cell->insertBefore($f,$cell->firstChild);});$report=refresh_review($root);ok(!$report['canApply']&&file_get_contents($file)===$raw,'A source formula change cannot silently overwrite a user formula');

    $root = fixture('empty-draft'); $file=web_draft($root,[]); $report=refresh_review($root);
    $decoded=json_decode(json_bytes($report),true,512,JSON_THROW_ON_ERROR); refresh_apply($root,$decoded);
    ok(json_decode(file_get_contents($file),true)['values'] === [], 'Review JSON round trip and migration handle empty drafts');

    $root = fixture('dual-change'); $file = web_draft($root,['B11'=>'77']); $raw = file_get_contents($file); $layout = file_get_contents($root.'/config/layout.json');
    change_sheet($root,fn($d,$x)=>set_number($x,'B11','88')); $report=refresh_review($root);
    ok(!$report['canApply'] && str_contains($report['conflicts'][0]['reason'],'Both the Excel default'), 'Concurrent Excel and web edits are reported as a conflict');
    ok(rejected(fn()=>refresh_apply($root,$report),'has conflicts') && file_get_contents($file)===$raw && file_get_contents($root.'/config/layout.json')===$layout, 'Conflict refuses apply without discarding or overwriting any web value');

    $root = fixture('input-to-formula'); $file = web_draft($root,['B11'=>'77']); $raw = file_get_contents($file);
    change_sheet($root,function($d,$x){$c=$x->query('//s:c[@r="B11"]')->item(0);$f=$d->createElementNS($d->documentElement->namespaceURI,'f');$f->textContent='SUM(C11:P11)';$c->insertBefore($f,$c->firstChild);});
    $report=refresh_review($root); $candidate=build_layout($root.'/genstat.xlsx',$root.'/config/inputs.php');
    ok(!$candidate['sheets'][0]['cells']['B11']['editable'] && $candidate['sheets'][0]['cells']['B11']['formulaProtected'], 'An allowlisted input automatically becomes locked when Excel adds a formula');
    ok(!$report['canApply'] && count($report['inputDefinitionChanges'])===1 && rejected(fn()=>refresh_apply($root,$report),'has conflicts') && file_get_contents($file)===$raw, 'Input-to-formula refresh preserves draft and reports the input-definition conflict');

    $root=fixture('stale');$file=web_draft($root,['B11'=>'77']);$review=refresh_review($root);$d=json_decode(file_get_contents($file),true);$d['values']['B11']='99';atomic_file_write($file,json_bytes($d));
    ok(rejected(fn()=>refresh_apply($root,$review),'stale or altered'), 'A web save after review invalidates the plan');
    $review=refresh_review($root);file_put_contents($root.'/config/inputs.php',"\n// Administrator changed input definitions.\n",FILE_APPEND);
    ok(rejected(fn()=>refresh_apply($root,$review),'stale or altered'), 'Input-definition changes after review invalidate the plan');

    $root=fixture('revoke');$file=web_draft($root,['B11'=>'77']);$inputs=require $root.'/config/inputs.php';unset($inputs['B11']);file_put_contents($root.'/config/inputs.php','<?php return '.var_export($inputs,true).';');
    ok(!refresh_review($root)['canApply'], 'Revoking a saved input blocks migration');

    $root=fixture('merged-and-blank');$inputs=require $root.'/config/inputs.php';$inputs['Y6']='Explicit test approval of merge anchor';$inputs['Z6']='Explicit test approval of covered cell';$inputs['AG98']='Explicit test approval of implicit blank';
    file_put_contents($root.'/config/inputs.php','<?php return '.var_export($inputs,true).';');$l=build_layout($root.'/genstat.xlsx',$root.'/config/inputs.php');
    ok($l['sheets'][0]['cells']['Y6']['editable'] && !$l['sheets'][0]['cells']['Z6']['editable'] && $l['sheets'][0]['cells']['AG98']['editable'], 'Only the approved merged anchor is editable; approved implicit blanks are included');

    $doc=xml_doc('<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData><row r="1"><c r="A1"><f>1+1</f></c><c r="B1"><f t="shared" si="0" ref="B1:C1">A1+1</f></c><c r="C1"><f t="shared" si="0"/></c><c r="D1"><f>OtherSheet!A1</f></c><c r="E1"><f t="array" ref="E1:F1">1+1</f></c><c r="F1"/></row></sheetData></worksheet>');
    $policy=worksheet_policy($doc);
    ok(formula_info($policy,'A1')['kind']==='ordinary' && formula_info($policy,'C1')['kind']==='shared' && formula_info($policy,'D1')!==null && formula_info($policy,'F1')['kind']==='array', 'Ordinary, empty shared follower, cross-sheet, and ranged formulas are detected');

    // Prove the server reads actual source formulas even if both the layout
    // editable flag and local allowlist were accidentally made permissive.
    $root=fixture('live-server-defense'); mkdir($root.'/lib');mkdir($root.'/tools');copy($project.'/tools/build-layout.php',$root.'/tools/build-layout.php');
    foreach(['app.php','xlsx.php','files.php','workbook-policy.php','template-sync.php','sync-mapping.php','template-refresh.php','formula-auth.php','formula-edits.php','formula-engine.php','render.php'] as $name)copy($project.'/lib/'.$name,$root.'/lib/'.$name);
    $inputs=require $root.'/config/inputs.php';$inputs['Q11']='Test erroneous approval';file_put_contents($root.'/config/inputs.php','<?php return '.var_export($inputs,true).';');
    $l=build_layout($root.'/genstat.xlsx',$root.'/config/inputs.php');$l['sheets'][0]['cells']['Q11']['editable']=true;atomic_file_write($root.'/config/layout.json',json_bytes($l));
    $script='<?php require __DIR__."/lib/app.php"; foreach(["save","export"] as $action){try{if($action==="save")save_draft("2026-06",["Q11"=>"2"],0);else export_workbook(["Q11"=>"2"]);exit(9);}catch(InvalidArgumentException $e){if(!str_contains($e->getMessage(),"Formula cell"))exit(8);}} echo "live-source-locked";';
    file_put_contents($root.'/probe.php',$script);
    $cmd='"'.PHP_BINARY.'" "'.$root.'/probe.php"';$output=[];exec($cmd,$output,$status);
    ok($status===0 && implode('',$output)==='live-source-locked', 'Save and export reject live source formulas even with permissive metadata');

    $root=fixture('recovery-marker');atomic_file_write($root.'/storage/refresh-in-progress.json',json_bytes(['backup'=>'test']));
    ok(rejected(fn()=>refresh_review($root),'incomplete refresh'), 'An interrupted refresh requires explicit backup recovery');
    echo "Refresh fixtures were isolated from the real workbook and drafts.\n";
} finally {
    // Delete only this test's verified, random directory beneath the OS temp.
    $resolved=realpath($testRoot);$parent=realpath(sys_get_temp_dir());
    if($resolved!==false && str_starts_with(strtolower($resolved),strtolower($parent.DIRECTORY_SEPARATOR.'genstat-refresh-'))){
        $walk=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
        foreach($walk as $item){if($item->isDir())rmdir($item->getPathname());else unlink($item->getPathname());}rmdir($resolved);
    }
}
