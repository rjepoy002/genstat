<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require_once __DIR__.'/../lib/query-settings.php';
$l=base_report_layout();$s=$l['sheets'][0];
foreach(['F8','F9','F18'] as $ref)echo $ref.' '.json_encode($s['cells'][$ref],JSON_UNESCAPED_SLASHES).PHP_EOL;
echo 'SOURCE '.$l['sha256'].PHP_EOL;
$db=qs_db('writer');$records=qs_load($db);$db->close();
foreach($records as $key=>$record)echo 'CONFIG '.json_encode(['key'=>$key,'queryKey'=>$record['query_key'],'sqlHash'=>hash('sha256',$record['sql_text']),'revision'=>$record['revision'],'bindings'=>$record['bindings'],'excluded'=>$record['excluded']]).PHP_EOL;
$area=qs_area_metadata();foreach($area['areas'] as $row)if((string)$row['AreaID']==='12')echo 'MASTER '.json_encode($row).PHP_EOL;
foreach($area['areaMappings'] as $row)if(in_array('12',array_map('strval',array_values($row)),true))echo 'AREA_MAPPING '.json_encode($row).PHP_EOL;
echo 'TARGETS '.json_encode(qs_rows($l)['genstat-base/Jul26/A18']['cells']).PHP_EOL;
