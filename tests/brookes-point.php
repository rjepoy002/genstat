<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/query-settings.php';
function bp_check(bool $ok,string $message): void{if(!$ok)throw new RuntimeException($message);echo 'PASS: '.$message.PHP_EOL;}
$l=base_report_layout();$s=$l['sheets'][0];$before=serialize($l);$area=qs_area_metadata();$headers=report_area_headers($l);
bp_check(count($headers)===15&&$headers[6]['label']==="Brooke's Point",'All 15 area headers recognized; F8/F9 combined correctly');
bp_check($headers[10]['label']==='Araceli/Dumaran'&&$headers[14]['label']==='Cuyo/Magsaysay','Combined headers remain distinct and correctly recognized');
foreach(["Brooke's Point","Brooke's Pt.",'Brookes Point',"BROOKE'S POINT","Brooke’s\nPoint"] as $name)bp_check(report_area_alias($name,$area['areas'],$area['areaMappings'])==='12','Explicit alias resolves: '.$name);
bp_check(report_area_alias("Brooke's Point Annex",$area['areas'],$area['areaMappings'])===null,'No fuzzy/prefix matching');
$key='genstat-base/Jul26/A18';$rows=qs_rows($l);bp_check(count($rows[$key]['cells'])===14&&isset($rows[$key]['protectedCells']['F18']),'Real source F18 remains protected by its legitimate formula');
bp_check($s['cells']['F18']['formula']==='3011664+767072'&&merged_owner($s['merges'],'F18')===null,'F18 is an ordinary formula, not a merge placeholder');
$ids=[1,8,9,10,12,2,5,15,4,3,6,19,16,18,11];$bindings=[];$results=[];
foreach($ids as $i=>$id){$ref=col_name($i+2).'18';$bindings[$ref]=['areaId'=>(string)$id,'sacode'=>''];$results[]=['AreaID'=>(string)$id,'TotalKWhSold'=>$id===12?'3776446.00':(string)($id*100)];}
$config=qs_normalize($l,['rowKey'=>$key,'queryKey'=>'READONLY_BP_FIXTURE','sql'=>'SELECT :ReportMonth','areaColumn'=>'AreaID','valueColumn'=>'TotalKWhSold','bindings'=>$bindings,'excluded'=>[],'enabled'=>true]);
$result=['columns'=>['AreaID','TotalKWhSold'],'rows'=>array_reverse($results),'truncated'=>false];$matched=qs_match($l,$config,$result,$area['areas'],$area['areaMappings']);
bp_check(!$matched['errors']&&count($matched['changes'])===15&&$matched['changes']['F18']['formulaReplacement'],'Approved row accepts all 15 areas including the source F18 formula');
bp_check($matched['changes']['F18']['originalFormula']==='=3011664+767072'&&$matched['changes']['F18']['value']==='3776446.00','SQL mapping retains F18 formula provenance and the computed value');
bp_check(!$config['excluded'],'AreaID 12 is never automatically excluded');
try{$bad=$config;$bad['bindings']['B15']=['areaId'=>'12','sacode'=>''];qs_normalize($l,$bad);throw new RuntimeException('Unauthorized target accepted');}catch(QuerySettingsError $e){bp_check(str_contains($e->getMessage(),'outside the selected authorized'),'Formula outside the approved input row remains rejected');}
// In-memory alternate template only: verify the requested 15-input behavior
// when the authoritative source genuinely contains a non-formula input.
$constant=$l;$constant['sheets'][0]['cells']['F18']=array_replace($s['cells']['F18'],['formula'=>null,'formulaAttributes'=>null,'formulaProtected'=>false,'editable'=>true,'classification'=>'database_input']);
$config['bindings']['F18']=['areaId'=>'12','sacode'=>''];$full=qs_normalize($constant,$config);$valid=qs_match($constant,$full,$result,$area['areas'],$area['areaMappings']);
bp_check(!$valid['errors']&&count($valid['changes'])===15&&$valid['changes']['F18']['areaId']==='12','Genuine non-formula template maps all 15 unique areas correctly');
$duplicate=$result;$duplicate['rows'][]=$duplicate['rows'][0];bp_check((bool)qs_match($constant,$full,$duplicate,$area['areas'],$area['areaMappings'])['errors'],'Duplicate-area detection retained');
$single=$constant;$single['sheets'][0]['cells']['F8']['value']="Brooke's\nPoint";$single['sheets'][0]['cells']['F9']['value']='';bp_check(report_area_headers($single)[6]['label']==="Brooke's Point",'Single-cell line-break heading supported without changing source');
$other=$constant;$other['sheets'][0]['name']='Nov26';
foreach([8,9] as $r){$old=$other['sheets'][0]['cells']['F'.$r];$other['sheets'][0]['cells']['F'.$r]=$other['sheets'][0]['cells']['G'.$r];$other['sheets'][0]['cells']['G'.$r]=$old;}
for($c=1;$c<=19;$c++)$other['sheets'][0]['cells'][col_name($c).'20']=$other['sheets'][0]['cells'][col_name($c).'18'];$other['sheets'][0]['cells']['A18']['value']='';
$otherKey='genstat-base/Nov26/A20';$otherRows=qs_rows($other);bp_check(report_area_alias($otherRows[$otherKey]['cells']['G20']['label'],$area['areas'],$area['areaMappings'])==='12','Another month/row/column resolves Brooke’s Point at G20, not hardcoded F18');
bp_check(serialize($l)===$before,'Input layout values, formulas, styles, references and classifications unchanged');
bp_check(hash_file('sha256',__DIR__.'/../excel-files/reference/GENSTAT_BASE.xlsx')==='752c1ad06bd770ea749ef7925a49adbc725ee81b3a7dd6342f5b1585253c3dce','Reference workbook unchanged');
