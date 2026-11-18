<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/query-settings.php';
function replacement_check(bool $ok,string $message): void{if(!$ok)throw new RuntimeException($message);echo 'PASS: '.$message.PHP_EOL;}
$l=base_report_layout();$snapshot=serialize($l);$key='genstat-base/Jul26/A18';$ids=[1,8,9,10,12,2,5,15,4,3,6,19,16,18,11];$bindings=[];$rows=[];
foreach($ids as $i=>$id){$bindings[col_name($i+2).'18']=['areaId'=>(string)$id,'sacode'=>''];$rows[]=['AreaID'=>(string)$id,'TotalKWhSold'=>$id===12?'3776446.00':(string)($id*100)];}
$config=qs_normalize($l,['rowKey'=>$key,'queryKey'=>'GENSTAT_SQL_UI_TEST','sql'=>'SELECT :ReportMonth','areaColumn'=>'AreaID','valueColumn'=>'TotalKWhSold','bindings'=>$bindings,'enabled'=>true,'allowFormulaReplacement'=>true]);
$area=qs_area_metadata();$match=qs_match($l,$config,['columns'=>['AreaID','TotalKWhSold'],'rows'=>$rows,'truncated'=>false],$area['areas'],$area['areaMappings']);
replacement_check(!$match['errors']&&count($match['changes'])===15&&$match['changes']['F18']['formulaReplacement'],'Approved row maps all 15 areas including F18 as formula replacement');
replacement_check($match['changes']['G18']['areaId']==='2'&&!$match['changes']['G18']['formulaReplacement'],'Roxas remains an ordinary AreaID 2 mapping');
$disabled=$config;$disabled['allowFormulaReplacement']=false;$disabled=qs_normalize($l,$disabled);
replacement_check(isset(qs_targets($l,$disabled)['F18']),'Approved row includes F18 independently of the legacy flag');
replacement_check(!isset(qs_rows($l)['genstat-base/Jul26/A15']),'Formula-only calculated rows remain excluded');
$record=sql_replacement_issue($l,'F18','3776446.00',$config,1);$calc=base_report_calculate($l,['B11'=>'24850653.84'],[],['F18'=>$record]);
$legacyRecord=$record;unset($legacyRecord['reportMonth']);$legacyRecord['signature']=sql_replacement_signature($legacyRecord);
sql_replacement_validate($l,['F18'=>$legacyRecord]);
$august=$l;$august['reportMonth']='202608';
foreach([$record,$legacyRecord] as $julyRecord){
 try{sql_replacement_validate($august,['F18'=>$julyRecord]);throw new RuntimeException('July signature accepted in August');}
 catch(InvalidArgumentException $e){replacement_check(str_contains($e->getMessage(),'different reporting month'),'July replacements stay in July, including legacy signatures');}
}
replacement_check($calc['cells']['F18']['value']===3776446.0&&$calc['cells']['Q18']['display']==='38,724,209.00','Approved numeric overlay replaces F18 and recalculates Q18');
replacement_check($calc['cells']['B11']['value']===24850653.84,'Manual input outside the SQL row preserved');
replacement_check($record['originalFormula']==='=3011664+767072'&&$l['sheets'][0]['cells']['F18']['formula']==='3011664+767072','Original formula retained in signed provenance and untouched template metadata');
try{base_report_validate($l,['F18'=>'1']);throw new RuntimeException('Manual bypass accepted');}catch(InvalidArgumentException $e){echo 'PASS: ordinary manual value edits still cannot overwrite formulas'.PHP_EOL;}
try{base_report_validate($l,['B15'=>'1']);throw new RuntimeException('Unrelated formula overwrite accepted');}catch(InvalidArgumentException $e){echo 'PASS: formula protection remains active in unrelated calculated rows'.PHP_EOL;}
$tampered=$record;$tampered['value']='1';try{base_report_calculate($l,[],[],['F18'=>$tampered]);throw new RuntimeException('Tampered approval accepted');}catch(InvalidArgumentException $e){echo 'PASS: tampered SQL replacement rejected'.PHP_EOL;}
try{base_report_calculate($l,[],['F18'=>'=1'],['F18'=>$record]);throw new RuntimeException('Manual formula override accepted');}catch(InvalidArgumentException $e){echo 'PASS: manual formula edits cannot coexist with an SQL replacement'.PHP_EOL;}
$array=$l;$array['sheets'][0]['cells']['F18']['formulaKind']='array';replacement_check(!isset(qs_targets($array,$config)['F18']),'Array/data-table protections are not bypassed by row opt-in');
$merged=$l;$merged['sheets'][0]['merges'][]='E18:F18';replacement_check(!isset(qs_targets($merged,$config)['F18']),'Covered merged placeholders remain excluded');
$shared=$l;$shared['sheets'][0]['cells']['F18']['formulaAttributes']=['t'=>'shared','si'=>'999','ref'=>'F18:G18'];$shared['sheets'][0]['cells']['F18']['formulaKind']='shared';$shared['sheets'][0]['cells']['G18']['formula']='';$shared['sheets'][0]['cells']['G18']['formulaAttributes']=['t'=>'shared','si'=>'999'];$shared['sheets'][0]['cells']['G18']['classification']='formula';
$sharedRecord=sql_replacement_issue($shared,'F18','3776446.00',$config,1);$sharedResult=base_report_calculate($shared,[],[],['F18'=>$sharedRecord]);replacement_check($sharedResult['cells']['G18']['value']===3778736.0,'Replacing a shared anchor retains its sibling calculation in the working copy');
replacement_check(serialize($l)===$snapshot,'All source values, formulas, classifications and formatting unchanged');
replacement_check(hash_file('sha256',__DIR__.'/../excel-files/reference/GENSTAT_BASE.xlsx')==='752c1ad06bd770ea749ef7925a49adbc725ee81b3a7dd6342f5b1585253c3dce','Reference workbook remains byte-identical');
