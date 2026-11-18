<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/base-report.php';
function base_expect(bool $ok,string $message): void{if(!$ok)throw new RuntimeException($message);echo 'PASS: '.$message.PHP_EOL;}
$source=__DIR__.'/../excel-files/reference/GENSTAT_BASE.xlsx';$hash=hash_file('sha256',$source);$raw=build_layout($source);$l=base_report_layout();$s=$l['sheets'][0];
base_expect($l['report']['range']==='A8:S47','Source-discovered range A8:S47');
$original=expanded_formulas($raw['sheets'][0]);$formulas=expanded_formulas($s);base_expect($original===$formulas,'Every source formula and dependency preserved');
foreach($raw['sheets'][0]['cells'] as $ref=>$cell){base_expect(($s['cells'][$ref]['formula']===$cell['formula'])&&($s['cells'][$ref]['formulaAttributes']===$cell['formulaAttributes'])&&$s['cells'][$ref]['style']===$cell['style']&&$s['cells'][$ref]['value']===$cell['value'],'Original formula expression, attributes, value and style retained: '.$ref);}
base_expect($s['rows']===$raw['sheets'][0]['rows']&&$s['columns']===$raw['sheets'][0]['columns']&&$s['merges']===$raw['sheets'][0]['merges'],'Source row heights, column widths and merges retained');
$calc=base_report_calculate($l);$mismatches=[];$matched=0;$count=0;$db=[];$editable=0;
foreach($s['cells'] as $ref=>$c){if(!range_contains($l['report']['range'],$ref))continue;
 if($c['editable'])$editable++;
 if($c['databaseInput']){[$col,$row]=cell_coordinates($ref);base_expect(!isset($formulas[$ref])&&$col>1&&$row>9,'Database cell '.$ref.' is a non-formula data slot');$db[]=['reference'=>$ref,'description'=>trim($s['cells']['A'.$row]['value']),'area'=>trim(($s['cells'][col_name($col).'8']['value']??'').' '.($s['cells'][col_name($col).'9']['value']??'')),'originalValue'=>$c['value'],'sourceFill'=>cell_css($l,$c['style'])['background-color']??'none','eligibility'=>in_array(strtoupper(cell_css($l,$c['style'])['background-color']??''),['#00B0F0','#4BACC6'],true)?'Individual blue data cell':'Designated blue input row, area B:P (excludes totals/comparisons)'];}
 if(!isset($formulas[$ref]))continue;$count++;$actual=$calc['cells'][$ref]['value'];
 if(is_numeric($c['value'])&&is_numeric($actual)&&abs((float)$actual-(float)$c['value'])<=max(1e-7,abs((float)$c['value'])*1e-12))$matched++;
 elseif((string)$actual===$c['value'])$matched++;else $mismatches[]=['cell'=>$ref,'formula'=>$formulas[$ref],'cached'=>$c['value'],'calculated'=>$actual,'error'=>$calc['cells'][$ref]['error']];
}
$changed=base_report_calculate($l,['B11'=>(string)((float)$s['cells']['B11']['value']+100)]);
base_expect(abs($changed['cells']['Q11']['value']-$calc['cells']['Q11']['value']-100)<1e-7,'Editing B11 recalculates Q11 by exactly 100');
base_expect(abs($changed['cells']['B15']['value']-$calc['cells']['B15']['value']-100)<1e-7,'Editing B11 recalculates dependent purchased total B15');
try{base_report_validate($l,['Q11'=>'1']);throw new RuntimeException('Formula overwrite accepted');}catch(InvalidArgumentException $e){echo 'PASS: Formula overwrite rejected'.PHP_EOL;}
base_expect(hash_file('sha256',$source)===$hash,'Source workbook unchanged');
$fullEngine=new FormulaEngine($l);$fullEngine->results();
$report=['sourceHash'=>$hash,'range'=>$l['report']['range'],'formulasPreserved'=>$count,'wholeWorksheetFormulas'=>count($formulas),'databaseInputs'=>count($db),'editableNonFormulaCells'=>$editable,'manualOtherCells'=>$editable-count($db),'cachedMatches'=>$matched,'differences'=>$mismatches,'warnings'=>$calc['warnings'],'errors'=>$calc['errors'],'wholeWorksheetWarnings'=>$fullEngine->warnings,'wholeWorksheetErrors'=>$fullEngine->errors,'databaseInputCells'=>$db];
file_put_contents(__DIR__.'/../docs/base-report-validation.json',json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR).PHP_EOL);
$csv=fopen(__DIR__.'/../docs/base-report-database-inputs.csv','w');fputcsv($csv,['Worksheet','Cell','Row description','Area','Original value','Source fill','Eligibility']);foreach($db as $row)fputcsv($csv,['Jul26',...array_values($row)]);fclose($csv);
echo json_encode(array_diff_key($report,array_flip(['databaseInputCells','differences','warnings','errors'])),JSON_PRETTY_PRINT).PHP_EOL;
echo 'DIFFERENCES '.json_encode($mismatches).PHP_EOL.'WARNINGS '.json_encode($calc['warnings']).PHP_EOL.'ERRORS '.json_encode($calc['errors']).PHP_EOL;
