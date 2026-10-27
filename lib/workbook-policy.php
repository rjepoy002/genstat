<?php
declare(strict_types=1);
require_once __DIR__.'/xlsx.php';

function cell_coordinates(string $ref): array {
    if (!preg_match('/^\$?([A-Z]+)\$?([1-9][0-9]*)$/D', $ref, $m)) {
        throw new RuntimeException("Invalid cell reference: $ref");
    }
    $col = 0;
    foreach (str_split($m[1]) as $char) $col = $col * 26 + ord($char) - 64;
    if ($col > 16384 || (int)$m[2] > 1048576) throw new RuntimeException('Cell exceeds Excel bounds.');
    return [$col, (int)$m[2]];
}

function range_contains(string $range, string $ref): bool {
    $ends = explode(':', $range);
    [$left, $top] = cell_coordinates($ends[0]);
    [$right, $bottom] = cell_coordinates($ends[1] ?? $ends[0]);
    [$col, $row] = cell_coordinates($ref);
    return $col >= $left && $col <= $right && $row >= $top && $row <= $bottom;
}

// An empty <f t="shared" si="..."/> is a formula too. Formula ranges also
// protect shared/array/data-table result cells with no individual <f> node.
function worksheet_policy(DOMDocument $doc): array {
    $x = xml_path($doc);
    $formulas = []; $ranges = []; $merges = [];
    foreach ($x->query('//s:sheetData/s:row/s:c/s:f') as $f) {
        $ref = $f->parentNode->getAttribute('r');
        $formulas[$ref] = ['kind'=>$f->getAttribute('t') ?: 'ordinary', 'formula'=>$f->textContent];
        if ($f->hasAttribute('ref')) $ranges[] = ['range'=>$f->getAttribute('ref'), 'anchor'=>$ref, 'kind'=>$f->getAttribute('t') ?: 'ordinary'];
    }
    foreach ($x->query('//s:mergeCell') as $m) $merges[] = $m->getAttribute('ref');
    return ['formulas'=>$formulas, 'ranges'=>$ranges, 'merges'=>$merges];
}

function formula_info(array $policy, string $ref): ?array {
    if (isset($policy['formulas'][$ref])) return $policy['formulas'][$ref];
    foreach ($policy['ranges'] as $range) {
        if (range_contains($range['range'], $ref)) return ['kind'=>$range['kind'], 'anchor'=>$range['anchor'], 'formula'=>null];
    }
    return null;
}

function merged_owner(array $merges, string $ref): ?string {
    foreach ($merges as $range) if (range_contains($range, $ref)) return explode(':', $range)[0];
    return null;
}

// Browser policy deliberately ignores Excel's default locked style. Only real
// formulas, covered merge cells and explicit application read-only flags lock a cell.
function worksheet_cell(array $sheet, string $ref): ?array {
    cell_coordinates($ref);
    if (!range_contains($sheet['range'], $ref)) return null;
    [$col, $row] = cell_coordinates($ref);
    $style = (int)($sheet['rows'][$row]['s'] ?? 0);
    if (!isset($sheet['rows'][$row]['s'])) foreach ($sheet['columns'] as $column) {
        if ($col >= (int)$column['min'] && $col <= (int)$column['max']) $style = (int)($column['style'] ?? 0);
    }
    $cell = $sheet['cells'][$ref] ?? ['style'=>$style, 'value'=>'', 'type'=>'', 'formula'=>null,
        'formulaAttributes'=>null, 'formulaProtected'=>false, 'formulaKind'=>null, 'implicit'=>true];
    $protected = $cell['formula'] !== null || ($cell['formulaProtected'] ?? false);
    foreach ($sheet['formulaRanges'] ?? [] as $range) if (range_contains($range['range'], $ref)) {
        $protected = true;
        $cell['formulaKind'] = $cell['formulaKind'] ?? $range['kind'];
    }
    $owner = merged_owner($sheet['merges'], $ref);
    $cell['formulaProtected'] = $protected;
    $cell['editable'] = !$protected && !($cell['readOnly'] ?? false) && !($sheet['readOnly'] ?? false) && ($owner === null || $owner === $ref);
    $cell['inputType'] = $cell['editable'] ? 'text-or-number' : null;
    $cell['classification'] = $protected ? 'formula' : ($cell['editable'] ? 'manual-input' : 'read-only');
    return $cell;
}

function apply_browser_edit_policy(array $layout): array {
    foreach ($layout['sheets'] as &$sheet) {
        foreach ($sheet['cells'] as $ref=>&$cell) $cell = worksheet_cell($sheet, $ref);
        unset($cell);
        $sheet['analysis']['inputCount'] = count(array_filter($sheet['cells'], fn($c)=>$c['editable']));
        $sheet['analysis']['inputPolicy'] = 'Non-formula cells; explicit read-only and merged ownership restrictions apply.';
        $sheet['inputDefinitionIssues'] = [];
    }
    unset($sheet);
    return $layout;
}
