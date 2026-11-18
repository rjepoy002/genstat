<?php
declare(strict_types=1);

// Presentation policy only: never filter the source worksheet/calculation layout.
function base_report_hidden_descriptions(): array {
 return ['Total KWH Sold (w/o BILL ADJ)','Dist. System Loss In KWH - ADJ','Rate of Dist. System - ADJ','System Loss in KWH - ADJ','Rate of System Loss -ADJ'];
}
function base_report_normalize_description(string $label): string {
 return strtolower(trim(preg_replace('/\s+/u',' ',$label)));
}
function base_report_row_hidden(string $label): bool {
 return in_array(base_report_normalize_description($label),array_map('base_report_normalize_description',base_report_hidden_descriptions()),true);
}
function base_report_row_spacer(array $sheet,array $report,int $row): bool {
 if($row<=$report['firstRow']+1)return false;
 foreach($sheet['merges'] as $merge){[$a,$b]=explode(':',$merge);[, $first]=cell_coordinates($a);[, $last]=cell_coordinates($b);if($row>=$first&&$row<=$last)return false;}
 for($c=1;$c<=$report['lastCol'];$c++){$cell=$sheet['cells'][col_name($c).$row]??null;if($cell&&($cell['value']!==''||$cell['formula']!==null))return false;}
 return true;
}
