<?php
declare(strict_types=1);

const BASE_REPORT_LEGACY_MONTH = '202607';

function base_report_valid_month($month): bool {
 return is_string($month)&&preg_match('/^(?!0000)[0-9]{4}(0[1-9]|1[0-2])$/D',$month)===1;
}
function base_report_month(array $layout): string {
 return $layout['reportMonth']??BASE_REPORT_LEGACY_MONTH;
}
function base_report_scope(array $layout): string {
 return $layout['sha256'].'-'.base_report_month($layout);
}
function base_report_csrf(string $month): string {
 return hash_hmac('sha256',$month,$_SESSION['baseCsrf']);
}
function base_report_month_label(string $month): string {
 return DateTimeImmutable::createFromFormat('!Ym',$month)->format('F Y');
}

// Presentation policy only: never filter the source worksheet/calculation layout.
function base_report_rendered_columns(array $report): array {
 return array_values(array_filter(range(1,$report['lastCol']),static fn(int $column)=>col_name($column)!=='S'));
}

function base_report_row_hidden(string $label): bool {
 return preg_match('/(?<![\p{L}\p{N}\p{M}_])ADJ(?![\p{L}\p{N}\p{M}_])/iu',$label)===1;
}
function base_report_row_spacer(array $sheet,array $report,int $row): bool {
 if($row<=$report['firstRow']+1)return false;
 foreach($sheet['merges'] as $merge){[$a,$b]=explode(':',$merge);[, $first]=cell_coordinates($a);[, $last]=cell_coordinates($b);if($row>=$first&&$row<=$last)return false;}
 for($c=1;$c<=$report['lastCol'];$c++){$cell=$sheet['cells'][col_name($c).$row]??null;if($cell&&($cell['value']!==''||$cell['formula']!==null))return false;}
 return true;
}

function base_report_empty_row_candidates(array $sheet,array $report): array {
 $columns=array_fill_keys(base_report_rendered_columns($report),true);
 $candidates=array_fill_keys(range($report['firstRow']+2,$report['lastRow']),true);
 foreach($sheet['merges'] as $merge){
  [$start,$end]=explode(':',$merge);
  [$left,$first]=cell_coordinates($start);[$right,$last]=cell_coordinates($end);
  if(!array_intersect_key($columns,array_fill_keys(range($left,$right),true)))continue;
  for($row=$first;$row<=$last;$row++)unset($candidates[$row]);
 }
 foreach($sheet['cells'] as $ref=>$cell){
  [$column,$row]=cell_coordinates($ref);
  if(!isset($candidates[$row],$columns[$column]))continue;
  if(base_report_has_content($cell['value']??null)||($cell['formula']??null)!==null||
   !empty($cell['editable'])||!empty($cell['databaseInput'])||!empty($cell['formulaProtected']))unset($candidates[$row]);
 }
 return $candidates;
}

function base_report_has_content(mixed $value): bool {
 return $value!==null&&(!is_string($value)||preg_match('/\A[\s\p{Z}]*\z/u',$value)!==1);
}

function base_report_empty_rows(array $candidates,array $draft,array $cells,array $configuredRows,array $renderedColumns): array {
 $columns=array_fill_keys($renderedColumns,true);
 foreach($configuredRows as $row)unset($candidates[$row]);
 foreach($draft['formulas'] as $ref=>$_){
  [$column,$row]=cell_coordinates($ref);
  if(isset($columns[$column]))unset($candidates[$row]);
 }
 foreach($cells as $ref=>$cell)if(base_report_has_content($cell['value']??null)){
  [$column,$row]=cell_coordinates($ref);
  if(isset($columns[$column]))unset($candidates[$row]);
 }
 return $candidates;
}
