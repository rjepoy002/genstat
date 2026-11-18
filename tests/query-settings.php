<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/query-settings.php';
function qcheck(bool $ok,string $message): void{if(!$ok)throw new RuntimeException($message);echo 'PASS: '.$message.PHP_EOL;}
$l=base_report_layout();$rows=qs_rows($l);$key='genstat-base/Jul26/A11';
qcheck(qs_month('Jan26')==='202601'&&qs_month('Jul26')==='202607'&&qs_month('Nov26')==='202611','Month comes from worksheet, including November');
qcheck(array_keys(qs_rows(base_report_layout()))===array_map(fn($row)=>'genstat-base/Jul26/A'.$row,[11,18,24,25,26,39]),'Visibility changes preserve the SQL input rows');
$months=qs_sorted_months(['202607',202608,'202607',null,'','202613','202600','2026-09',' 202609','000001','202601']);
qcheck($months===['202608','202607','202601'],'Valid calendar months are deduplicated and sorted newest first');
qcheck(qs_select_month($months,null)==='202608'&&qs_select_month($months,null,'202607')==='202607'&&qs_select_month($months,'202601','202607')==='202601'&&qs_select_month($months,null,'202609')==='202608','Month selection honors explicit, previous and latest available month');
foreach(['202613','202609',[],null] as $unavailable){
 try{qs_select_month($unavailable===null?[]:$months,$unavailable);throw new RuntimeException('Unavailable month accepted');}
 catch(QuerySettingsError $e){qcheck($e->getCode()===($unavailable===null?503:0),'Invalid or empty month selection fails closed');}
}
qcheck(isset($rows[$key])&&!isset($rows['genstat-base/Jul26/A19'])&&!isset($rows['genstat-base/Jul26/A15']),'Eligible metadata excludes hidden and formula-only rows');
qcheck(!isset($rows['genstat-base/Jul26/A18']['cells']['F18']),'Mixed row excludes original F18 formula');
$ids=[1,8,9,10,12,2,5,15,4,3,6,19,16,18,11];$bindings=[];foreach(array_keys($rows[$key]['cells']) as $i=>$ref)$bindings[$ref]=['areaId'=>(string)$ids[$i],'sacode'=>''];
$sql='WITH params AS (SELECT :ReportMonth AS ReportMonth) SELECT AreaID, AreaName, CAST((SELECT ReportMonth FROM params) AS UNSIGNED) AS Sales_KWH, AreaID * 10 AS KWH_Diff, AreaID * 100 AS TotalKWhSold FROM area_master ORDER BY AreaID DESC';
$config=qs_normalize($l,['rowKey'=>$key,'queryKey'=>'GENSTAT_TEST_ONLY','sql'=>$sql,'areaColumn'=>'AreaID','valueColumn'=>'Sales_KWH','sacodeColumn'=>'','bindings'=>$bindings,'excluded'=>['7','13','14','17','20'],'enabled'=>true]);
$area=qs_area_metadata();$result=qs_execute($sql,'202607');qcheck(count($result['rows'])===20&&(string)$result['rows'][0]['Sales_KWH']==='202607','Real CTE executes with server-side bound worksheet month');
$matched=qs_match($l,$config,$result,$area['areas'],$area['areaMappings']);qcheck(!$matched['errors']&&count($matched['changes'])===15&&$matched['changes']['B11']['areaId']==='1','Explicit area identity mapping works independently of result order');
$bad=$result;$bad['rows'][]=$bad['rows'][0];qcheck((bool)qs_match($l,$config,$bad,$area['areas'],$area['areaMappings'])['errors'],'Duplicate areas rejected without aggregation');
$bad=$result;$bad['rows'][0]['Sales_KWH']=null;qcheck((bool)qs_match($l,$config,$bad,$area['areas'],$area['areaMappings'])['errors'],'Null results flagged');
$bad=$result;array_pop($bad['rows']);qcheck((bool)qs_match($l,$config,$bad,$area['areas'],$area['areaMappings'])['errors'],'Missing expected area flagged');
$bad=$config;$bad['excluded']=[];qcheck((bool)qs_match($l,$bad,$result,$area['areas'],$area['areaMappings'])['errors'],'Unmapped areas require explicit exclusion');
$bad=$result;$bad['truncated']=true;qcheck((bool)qs_match($l,$config,$bad,$area['areas'],$area['areaMappings'])['errors'],'Truncated results cannot be applied');
$saConfig=$config;$saConfig['sacodeColumn']='SACode';$saConfig['bindings']=['B11'=>['areaId'=>'9','sacode'=>'03'],'C11'=>['areaId'=>'9','sacode'=>'04']];$saConfig['excluded']=[];
$saResult=['columns'=>['SACode','AreaID','Sales_KWH'],'rows'=>[['SACode'=>'03','AreaID'=>'9','Sales_KWH'=>1.2345678901234567],['SACode'=>'04','AreaID'=>'9','Sales_KWH'=>'10.50']],'truncated'=>false];
$saMatch=qs_match($l,$saConfig,$saResult,$area['areas'],$area['areaMappings']);qcheck(!$saMatch['errors']&&count($saMatch['changes'])===2,'Verified SACode-aware matching distinguishes shared AreaIDs');
qcheck($saMatch['changes']['B11']['value']===sprintf('%.17g',1.2345678901234567),'Raw DOUBLE precision preserved rather than display-rounded');
$saResult['rows'][]=$saResult['rows'][0];qcheck((bool)qs_match($l,$saConfig,$saResult,$area['areas'],$area['areaMappings'])['errors'],'Duplicate SACode/AreaID pair rejected');
$limited=qs_execute('SELECT a.AreaID, CAST(:ReportMonth AS UNSIGNED) AS Value FROM area_master a CROSS JOIN area_master b CROSS JOIN area_master c','202607');qcheck($limited['truncated']&&count($limited['rows'])===500,'Executor limits real result sets to 500 preview rows');
$db=qs_db('reader');qcheck((float)$db->query('SELECT @@max_statement_time')->fetch_row()[0]===3.0,'Server enforces three-second query timeout');
try{$db->query('UPDATE area_master SET AreaName=AreaName WHERE 1=0');throw new RuntimeException('Reader can write');}catch(mysqli_sql_exception $e){qcheck($e->getCode()===1142,'Reader account cannot update source records');}$db->close();
echo 'No query configuration or source record was changed by these checks.'.PHP_EOL;
