<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/query-settings.php';
$count=0;
function check(bool $ok,string $message):void{global $count;if(!$ok)throw new RuntimeException($message);$count++;echo "PASS: $message\n";}
function rejects(callable $f,string $message):void{try{$f();}catch(QuerySettingsError $e){check(true,$message);return;}throw new RuntimeException('Expected rejection: '.$message);}
$r=['Sales_KWH'=>'1200838.00','KWH_Diff'=>'455.00','Total_Cancelled_KWH'=>'36.00','TotalKWhSold'=>'1201293.00','odd name]'=>'0.1'];$cols=array_keys($r);
foreach(['TotalKWhSold'=>'1201293','Sales_KWH + KWH_Diff'=>'1201293','Sales_KWH + KWH_Diff + Total_Cancelled_KWH'=>'1201329','(Sales_KWH + KWH_Diff) * 1.02'=>'1225318.86','0.1 + 0.2'=>'0.3','1 / 3'=>'0.333333333333','-2 * (3 + 4)'=>'-14','[odd name]]] + 0.2'=>'0.3','0'=>'0','1000'=>'1000','1 - 2 * 3'=>'-5'] as $e=>$expected)check((new ReportExpression((string)$e))->evaluate($r,$cols)===$expected,'Decimal expression: '.$e);
$negative=$r;$negative['KWH_Diff']='-455';check((new ReportExpression('Sales_KWH + KWH_Diff'))->evaluate($negative,$cols)==='1200383','Negative adjustment preserved');
foreach(['1/0','Unknown','Sales_KWH +','(1+2','1; phpinfo()','SUM(Sales_KWH)','1 ** 2','1e309',str_repeat('(',40).'1'.str_repeat(')',40),str_repeat('1+',140).'1','1'.str_repeat('0',31),'[missing]'] as $e)rejects(fn()=>(new ReportExpression($e))->evaluate($r,$cols),'Reject unsafe/invalid formula: '.substr($e,0,45));
rejects(fn()=>(new ReportExpression('Sales_KWH'))->evaluate(['Sales_KWH'=>null],$cols),'NULL operand rejected');
rejects(fn()=>(new ReportExpression('Sales_KWH'))->evaluate(['Sales_KWH'=>'abc'],$cols),'Non-numeric column rejected');
$l=base_report_layout();$key='genstat-base/Jul26/A18';$targets=qs_rows($l)[$key]['cells'];
$areas=[['AreaID'=>'8','AreaName'=>'Aborlan'],['AreaID'=>'9','AreaName'=>'Narra'],['AreaID'=>'2','AreaName'=>'Roxas'],['AreaID'=>'1','AreaName'=>'Puerto Princesa City'],['AreaID'=>'12','AreaName'=>"Brooke's Pt."]];$maps=[['GenstatAreaID'=>'12']];
$c=['rowKey'=>$key,'areaColumn'=>'AreaID','sacodeColumn'=>'','autoMatchColumn'=>'AreaName','matchingMethod'=>'normalized','mappingSources'=>[],'bindings'=>[],'formulaEnabled'=>true,'enabled'=>true,'allowFormulaReplacement'=>true];
$result=['columns'=>['AreaID','AreaName','SACode'],'rows'=>[['AreaID'=>'8','AreaName'=>'  ABORLAN  '],['AreaID'=>'9','AreaName'=>'Narra'],['AreaID'=>'2','AreaName'=>'Roxas'],['AreaID'=>'1','AreaName'=>'Puerto Princesa City']],'truncated'=>false];
$a=qs_auto_bindings($l,$c,$result,$areas,$maps);check($a['bindings']['C18']['areaId']==='8'&&$a['bindings']['D18']['areaId']==='9','Exact, case and whitespace matches');check($a['bindings']['B18']['areaId']==='','Main has no implicit city alias');
$dup=$result;$dup['rows'][]=['AreaID'=>'1','AreaName'=>'Narra'];$a=qs_auto_bindings($l,$c,$dup,$areas,$maps);check($a['statuses']['D18']==='Ambiguous'&&$a['bindings']['D18']['areaId']==='','Duplicate matching labels do not choose first row');
$c['bindings']['C18']=['areaId'=>'9','sacode'=>''];$c['mappingSources']['C18']='manual';$a=qs_auto_bindings($l,$c,$result,$areas,$maps);check($a['bindings']['C18']['areaId']==='9','Manual override survives retest');$c['autoMatchColumn']='SACode';$a=qs_auto_bindings($l,$c,$result,$areas,$maps);check($a['bindings']['C18']['areaId']==='9','Manual override survives matching column change');
$c['autoMatchColumn']='AreaName';$c['mappingSources']['C18']='saved';$a=qs_auto_bindings($l,$c,$result,$areas,$maps);check($a['statuses']['C18']==='Previously saved','Legacy saved mappings retained');
$c['mappingSources']['C18']='auto';$c['sacodeColumn']='SACode';$dup=$result;$dup['rows'][0]['SACode']='01';$dup['rows'][]=['AreaID'=>'8','AreaName'=>'Aborlan','SACode'=>'02'];$a=qs_auto_bindings($l,$c,$dup,$areas,$maps);check($a['statuses']['C18']==='Ambiguous','Repeated AreaID with SACodes requires resolution');
check(isset(qs_targets($l,$c)['F18']),'Formula bar retains approved F18 replacement target');
$alias=report_area_alias("Brooke’s Point",$areas,$maps);check($alias==='12','Existing verified alias supports apostrophe normalization');
$legacy=qs_calculation_settings([]);check(!$legacy['formulaEnabled'],'Legacy configurations retain value-column behavior');
$before=hash_file('sha256',__DIR__.'/../lib/query-settings.php');check(qs_fingerprint($c,$l)!==qs_fingerprint(array_replace($c,['formulaExpression'=>'Sales_KWH']),$l),'Formula edits invalidate validation fingerprint');
$settings=qs_calculation_settings(['formulaEnabled'=>true,'formulaExpression'=>'Sales_KWH','mappingSources'=>['C18'=>'manual'],'autoMatchColumn'=>'AreaName']);check(qs_calculation_settings(json_decode(json_encode($settings),true))===$settings,'Formula, match settings and overrides round-trip');
rejects(fn()=>(new ReportExpression('TotalKWhSold'))->evaluate($r,$cols,['Sales_KWH']),'SQL text columns rejected even when values look numeric');
$area=qs_area_metadata();$ids=[1,8,9,10,12,2,5,15,4,3,6,19,16,18,11];$bindings=[];
foreach(array_keys(qs_targets($l,['rowKey'=>$key])) as $ref){$i=ord($ref[0])-ord('B');$bindings[$ref]=['areaId'=>(string)$ids[$i],'sacode'=>''];}
$config=qs_normalize($l,['rowKey'=>$key,'queryKey'=>'READONLY_FORMULA_CHECK','areaColumn'=>'AreaID','valueColumn'=>'TotalKWhSold','sql'=>'','bindings'=>$bindings,'excluded'=>['7','13','14','17','20'],'formulaEnabled'=>true,'formulaExpression'=>'Sales_KWH + KWH_Diff + Total_Cancelled_KWH','enabled'=>true]);
$result=qs_execute('WITH params AS (SELECT :ReportMonth AS ReportMonth) SELECT AreaID, 1200838.00 AS Sales_KWH, -455.00 AS KWH_Diff, 36.00 AS Total_Cancelled_KWH, 1201293.00 AS TotalKWhSold FROM area_master ORDER BY AreaID DESC','202607');
$match=qs_match($l,$config,$result,$area['areas'],$area['areaMappings']);check(!$match['errors']&&$match['changes']['G18']['value']==='1200419','Live SQL computed value uses formula and preserves negative adjustment');
check(isset($match['changes']['F18'])&&count($match['changes'])===15&&!$match['blockedTargets'],'Approved formula and 14 ordinary targets all receive computed values');
$live=base_report_calculate($l,[],[]);$review=qs_review_target($l,$match['changes']['G18'],$live,['G18'=>'1'],[],[]);check($review['manualConflict']&&$review['formulaUsed']===$config['formulaExpression'],'Computed review keeps manual conflict warning and formula provenance');
rejects(fn()=>qs_normalize($l,array_replace($config,['rowKey'=>'genstat-base/Jul26/A11'])),'Formula editing restricted to row 18');
$review=qs_review_target($l,$match['changes']['F18'],$live,[],[],[]);check($review['existingFormula']==='=3011664+767072'&&$review['changeType']==='Formula replacement','F18 review retains original formula and replacement warning');
$unknown=$config;$unknown['bindings']['ZZ999']=['areaId'=>'8','sacode'=>''];rejects(fn()=>qs_normalize($l,$unknown),'Unknown workbook mapping rejected');
$outside=$config;$outside['bindings']['B15']=['areaId'=>'8','sacode'=>''];rejects(fn()=>qs_normalize($l,$outside),'Mapping outside approved input row rejected');
$missing=$config;unset($missing['bindings']['F18']);rejects(fn()=>qs_normalize($l,$missing),'Unresolved F18 mapping must be completed');
$uncached=$l;$uncached['sheets'][0]['cells']['F18']['formula']='UNSUPPORTED(1)';$uncached['sheets'][0]['cells']['F18']['value']='';$uncachedLive=base_report_calculate($uncached);$review=qs_review_target($uncached,$match['changes']['F18'],$uncachedLive,[],[],[]);check($review['difference']===null&&$review['differenceDisplay']==='Unavailable','Missing formula cache never becomes a zero comparison');
$unapproved=$config;$unapproved['rowKey']='genstat-base/Jul26/A15';try{sql_replacement_issue($l,'B15','1',$unapproved,1);throw new RuntimeException('Unauthorized replacement accepted');}catch(InvalidArgumentException $e){check(true,'Signed replacement cannot be issued outside approved row');}
echo "$count assertions passed. No workbook or saved configuration changed.\n";
