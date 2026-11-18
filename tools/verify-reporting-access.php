<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require_once __DIR__.'/../lib/query-settings.php';
$config=qs_config();
echo 'PASS exact eight-table allowlist'.PHP_EOL;
$db=qs_db('reader');
echo 'Reader identity: '.$db->query('SELECT CURRENT_USER()')->fetch_row()[0].PHP_EOL;
foreach($db->query('SHOW GRANTS')->fetch_all(MYSQLI_NUM) as [$grant]){preg_match('/^GRANT (.*?) ON (.*?) TO /i',$grant,$m);echo 'Grant: '.($m[1]??'unknown').' ON '.($m[2]??'unknown').PHP_EOL;}
foreach($config['allowedTables'] as $table){
 $db->query('SELECT 1 FROM `'.$table.'` LIMIT 1');echo 'PASS SELECT '.$table.PHP_EOL;
}
$db->close();
echo 'PASS reporting reader permissions'.PHP_EOL;
$months=array_slice(qs_available_months(),0,2);
if(!$months)throw new RuntimeException('No reporting months are available for verification.');
foreach($months as $month){
 $probe=qs_execute('SELECT :ReportMonth AS ReportMonth FROM area_master LIMIT 1',$month);
 if((string)($probe['rows'][0]['ReportMonth']??'')!==$month)throw new RuntimeException('Reporting month binding failed.');
 echo 'PASS prepared reporting month '.$month.PHP_EOL;
}
qs_execute('SELECT :ReportMonth AS ReportMonth FROM latebills LIMIT 1',$months[0]);
echo 'PASS latebills through SQL validator and executor'.PHP_EOL;
$writer=qs_db('writer');$definitions=$writer->query('SELECT query_key,sql_text FROM genstat_query_definitions')->fetch_all(MYSQLI_ASSOC);$writer->close();
echo 'Saved query definitions: '.count($definitions).PHP_EOL;
foreach($definitions as $definition){echo 'Saved query: '.$definition['query_key'].PHP_EOL;if(stripos($definition['sql_text'],'billhistorycancelled')!==false){
 $parsed=qs_parse($definition['sql_text']);echo 'PASS saved SQL validation: '.implode(', ',$parsed['tables']).PHP_EOL;
 $result=qs_execute($definition['sql_text'],'202607');echo 'July result: '.count($result['rows']).' rows; columns '.implode(', ',$result['columns']).PHP_EOL;
}}
// Optional approved-query file is read verbatim; never reconstruct calculation SQL.
if(isset($argv[1])){$sql=file_get_contents($argv[1]);$parsed=qs_parse($sql);echo 'PASS supplied SQL validation: '.implode(', ',$parsed['tables']).PHP_EOL;$result=qs_execute($sql,'202607');$required=['BillMonth','AreaID','AreaName','SACode','Sales_KWH','KWH_Diff','Cancelled_KWH','Cancelled_KWH_2','Total_Cancelled_KWH','TotalKWhSold'];$absent=array_diff($required,$result['columns']);if($absent)throw new RuntimeException('Missing columns: '.implode(', ',$absent));echo 'PASS July 202607: '.count($result['rows']).' rows; all ten required columns; '.($result['truncated']?'TRUNCATED':'complete').PHP_EOL;}
