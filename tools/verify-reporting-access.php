<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require_once __DIR__.'/../lib/query-settings.php';
$approved=['overallsalessummary','billadjustment','billhistorycancelled','billhistorycancelled_2','area_mapping','area_master','kwh_diff_allocation'];
$actual=qs_config()['allowedTables'];sort($actual);sort($approved);if($actual!==$approved)throw new RuntimeException('Reporting allowlist must contain exactly the seven approved tables.');
echo 'PASS exact seven-table allowlist'.PHP_EOL;
$db=qs_db('reader');
echo 'Reader identity: '.$db->query('SELECT CURRENT_USER()')->fetch_row()[0].PHP_EOL;
foreach($db->query('SHOW GRANTS')->fetch_all(MYSQLI_NUM) as [$grant]){preg_match('/^GRANT (.*?) ON (.*?) TO /i',$grant,$m);echo 'Grant: '.($m[1]??'unknown').' ON '.($m[2]??'unknown').PHP_EOL;}
$missing=[];
foreach(qs_config()['allowedTables'] as $table){
 try{$db->query('SELECT 1 FROM `'.$table.'` LIMIT 1');echo 'PASS SELECT '.$table.PHP_EOL;}
 catch(mysqli_sql_exception $e){if($e->getCode()!==1142)throw $e;$missing[]=$table;echo 'MISSING SELECT '.$table.PHP_EOL;}
}
$db->close();
$writer=qs_db('writer');$definitions=$writer->query('SELECT query_key,sql_text FROM genstat_query_definitions')->fetch_all(MYSQLI_ASSOC);$writer->close();
echo 'Saved query definitions: '.count($definitions).PHP_EOL;
foreach($definitions as $definition){echo 'Saved query: '.$definition['query_key'].PHP_EOL;if(stripos($definition['sql_text'],'billhistorycancelled')!==false){
 $parsed=qs_parse($definition['sql_text']);echo 'PASS saved SQL validation: '.implode(', ',$parsed['tables']).PHP_EOL;
 if(!$missing){$result=qs_execute($definition['sql_text'],'202607');echo 'July result: '.count($result['rows']).' rows; columns '.implode(', ',$result['columns']).PHP_EOL;}
}}
// Optional approved-query file is read verbatim; never reconstruct calculation SQL.
if(isset($argv[1])){$sql=file_get_contents($argv[1]);$parsed=qs_parse($sql);echo 'PASS supplied SQL validation: '.implode(', ',$parsed['tables']).PHP_EOL;if(!$missing){$result=qs_execute($sql,'202607');$required=['BillMonth','AreaID','AreaName','SACode','Sales_KWH','KWH_Diff','Cancelled_KWH','Cancelled_KWH_2','Total_Cancelled_KWH','TotalKWhSold'];$absent=array_diff($required,$result['columns']);if($absent)throw new RuntimeException('Missing columns: '.implode(', ',$absent));echo 'PASS July 202607: '.count($result['rows']).' rows; all ten required columns; '.($result['truncated']?'TRUNCATED':'complete').PHP_EOL;}}
if($missing){echo 'DBA action required for: '.implode(', ',$missing).PHP_EOL;exit(2);}
