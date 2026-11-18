<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/base-report.php';
require_once __DIR__.'/../lib/base-report-view.php';
function view_check(bool $ok,string $message): void{if(!$ok)throw new RuntimeException($message);echo 'PASS: '.$message.PHP_EOL;}
$l=base_report_layout();$s=$l['sheets'][0];$source=build_layout(__DIR__.'/../excel-files/reference/GENSTAT_BASE.xlsx');$hidden=[];
for($r=$l['report']['firstRow'];$r<=$l['report']['lastRow'];$r++)if(base_report_row_hidden($s['cells']['A'.$r]['value']??''))$hidden[]=$r;
view_check($hidden===[19,34,37,46,47],'Only requested Excel rows 19, 34, 37, 46, 47 hidden');
foreach(base_report_hidden_descriptions() as $label)view_check(base_report_row_hidden('  '.strtoupper(str_replace(' ','   ',$label)).'  '),'Case and repeated whitespace normalized: '.$label);
view_check(!base_report_row_hidden('Total KWH Purchased - ADJ')&&!base_report_row_hidden('Rate of System Loss')&&!base_report_row_hidden('Rate of System Loss -ADJ extra'),'Similar but nonmatching descriptions remain visible');
view_check(expanded_formulas($s)===expanded_formulas($source['sheets'][0]),'All 402 formulas intact, including hidden rows');
view_check(count(array_filter($s['cells'],fn($c)=>$c['databaseInput']))===96,'All 96 database-input classifications retained');
foreach($source['sheets'][0]['cells'] as $ref=>$cell){view_check($s['cells'][$ref]['value']===$cell['value']&&$s['cells'][$ref]['formula']===$cell['formula']&&$s['cells'][$ref]['formulaAttributes']===$cell['formulaAttributes'],'Underlying value, formula and attributes unchanged: '.$ref);}
$initial=base_report_calculate($l);$changed=base_report_calculate($l,['B24'=>(string)((float)$s['cells']['B24']['value']+1)]);
view_check(abs($changed['cells']['B19']['value']-$initial['cells']['B19']['value']-1)<1e-7,'Hidden B19 recalculates after editing B24');
view_check(abs($changed['cells']['B34']['value']-$initial['cells']['B34']['value']+1)<1e-7,'Hidden B34 still depends on hidden B19');
view_check(abs($changed['cells']['B46']['value']-$initial['cells']['B46']['value']+1)<1e-7,'Hidden B46 still depends on hidden B34');
view_check(abs(($changed['cells']['B47']['value']-$initial['cells']['B47']['value'])+1/$initial['cells']['B16']['value'])<1e-12,'Hidden B47 still calculates rate from hidden B46');
view_check(!base_report_row_spacer($s,$l['report'],45),'Blank merged row is not collapsed');
view_check(hash_file('sha256',__DIR__.'/../excel-files/reference/GENSTAT_BASE.xlsx')==='752c1ad06bd770ea749ef7925a49adbc725ee81b3a7dd6342f5b1585253c3dce','Reference workbook checksum unchanged');
