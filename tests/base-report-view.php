<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/base-report.php';
require_once __DIR__.'/../lib/base-report-view.php';
function view_check(bool $ok,string $message): void{if(!$ok)throw new RuntimeException($message);echo 'PASS: '.$message.PHP_EOL;}
$l=base_report_layout();$s=$l['sheets'][0];$source=build_layout(__DIR__.'/../excel-files/reference/GENSTAT_BASE.xlsx');$hidden=[];
for($r=$l['report']['firstRow'];$r<=$l['report']['lastRow'];$r++)if(base_report_row_hidden($s['cells']['A'.$r]['value']??''))$hidden[]=$r;
view_check($hidden===[16,19,21,34,37,46,47],'All source rows containing standalone ADJ are hidden');
foreach(['Total KWH Purchased - ADJ','Rate of System Loss -ADJ extra','Sales (adj)','ADJ only'] as $label)
 view_check(base_report_row_hidden($label),'Standalone token matched: '.$label);
foreach(['Bill Adjustment (Previous months)','Rate of System Loss','ADJ2','preAdj','ADJ_code'] as $label)
 view_check(!base_report_row_hidden($label),'Nonmatching label stays visible: '.$label);
view_check(expanded_formulas($s)===expanded_formulas($source['sheets'][0]),'All 402 formulas intact, including hidden rows');
view_check(count(array_filter($s['cells'],fn($c)=>$c['databaseInput']))===96,'All 96 database-input classifications retained');
foreach($source['sheets'][0]['cells'] as $ref=>$cell){view_check($s['cells'][$ref]['value']===$cell['value']&&$s['cells'][$ref]['formula']===$cell['formula']&&$s['cells'][$ref]['formulaAttributes']===$cell['formulaAttributes'],'Underlying value, formula and attributes unchanged: '.$ref);}
$initial=base_report_calculate($l);$changed=base_report_calculate($l,['B24'=>(string)((float)$s['cells']['B24']['value']+1)]);
view_check(abs($changed['cells']['B19']['value']-$initial['cells']['B19']['value']-1)<1e-7,'Hidden B19 recalculates after editing B24');
view_check(abs($changed['cells']['B34']['value']-$initial['cells']['B34']['value']+1)<1e-7,'Hidden B34 still depends on hidden B19');
view_check(abs($changed['cells']['B46']['value']-$initial['cells']['B46']['value']+1)<1e-7,'Hidden B46 still depends on hidden B34');
view_check(abs(($changed['cells']['B47']['value']-$initial['cells']['B47']['value'])+1/$initial['cells']['B16']['value'])<1e-12,'Hidden B47 still calculates rate from hidden B46');
view_check(!base_report_row_spacer($s,$l['report'],45),'Blank merged row is not collapsed');
$candidates=base_report_empty_row_candidates($s,$l['report']);
view_check(array_keys($candidates)===[13,14,17,20,29,32,35,38,41,42],'Only rows empty across the browser Summary are spacer candidates');
$probe=$s;
$probe['cells']['S29']=['value'=>'0'];
$probe['cells']['T32']=['value'=>'','formula'=>'=""'];
$probe['cells']['B42']=['value'=>' ','editable'=>true];
view_check(array_keys(base_report_empty_row_candidates($probe,$l['report']))===[13,14,17,20,29,32,35,38,41],
 'Off-table values and formulas do not protect a browser spacer, but an empty manual input does');
$probe=$s;
$probe['cells']['B29']=['value'=>" \t\u{00a0}",'style'=>123];
view_check(isset(base_report_empty_row_candidates($probe,$l['report'])[29]),'Whitespace and formatting alone leave a row empty');
view_check(array_keys(base_report_empty_rows($candidates,['formulas'=>['B32'=>'=""','S13'=>'=""']],
 ['S29'=>['value'=>0],'R20'=>['value'=>0]],[42],base_report_rendered_columns($l['report'])))===[13,14,17,29,35,38,41],
 'A visible zero, visible saved formula, or SQL configuration keeps its row visible');
view_check(hash_file('sha256',__DIR__.'/../excel-files/reference/GENSTAT_BASE.xlsx')==='752c1ad06bd770ea749ef7925a49adbc725ee81b3a7dd6342f5b1585253c3dce','Reference workbook checksum unchanged');
