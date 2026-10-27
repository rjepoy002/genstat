<?php
declare(strict_types=1);

final class FormulaPermissionException extends RuntimeException {}

function formula_grant_key(string $ref): string {
    return layout()['sha256'].'|'.report_sheet()['name'].'|'.$ref;
}
function formula_auth_status(): array {
    return ['mode'=>'cell-confirmation','unlocked'=>false];
}
function confirm_formula_cell(string $ref): array {
    $cell=worksheet_cell(report_sheet(),$ref);
    if (!$cell) throw new InvalidArgumentException('Select a formula cell.');
    if (!($cell['formulaProtected'] ?? false)) {
        $draft=read_draft(period($_GET['period']??'2026-06'));
        if (!str_starts_with($draft['formulas'][$ref]??'','=')) throw new InvalidArgumentException('Select a formula cell.');
    }
    if (($cell['readOnly']??false) || (report_sheet()['readOnly']??false) || !in_array($cell['formulaKind']??'ordinary',['ordinary','shared'],true)) {
        throw new InvalidArgumentException('This formula belongs to a read-only or array/data-table range.');
    }
    $_SESSION['formulaCells'][formula_grant_key($ref)]=true;
    return ['cell'=>$ref,'unlocked'=>true];
}
function require_formula_cell_confirmation(string $ref): void {
    if (empty($_SESSION['formulaCells'][formula_grant_key($ref)])) throw new FormulaPermissionException('Confirm Unlock & Edit for '.$ref.' before saving its formula change.');
}
