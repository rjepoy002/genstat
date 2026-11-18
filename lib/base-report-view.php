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
function base_report_row_hidden(string $label): bool {
 return preg_match('/(?<![\p{L}\p{N}\p{M}_])ADJ(?![\p{L}\p{N}\p{M}_])/iu',$label)===1;
}
function base_report_row_spacer(array $sheet,array $report,int $row): bool {
 if($row<=$report['firstRow']+1)return false;
 foreach($sheet['merges'] as $merge){[$a,$b]=explode(':',$merge);[, $first]=cell_coordinates($a);[, $last]=cell_coordinates($b);if($row>=$first&&$row<=$last)return false;}
 for($c=1;$c<=$report['lastCol'];$c++){$cell=$sheet['cells'][col_name($c).$row]??null;if($cell&&($cell['value']!==''||$cell['formula']!==null))return false;}
 return true;
}
