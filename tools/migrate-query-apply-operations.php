<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require_once __DIR__.'/../lib/query-settings.php';
$c=(require __DIR__.'/../config/monthly-source.php')['connection'];mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli($c['host'],$c['user'],$c['password'],$c['database'],$c['port']);
$columns=array_column($db->query('SHOW COLUMNS FROM genstat_row_query_mappings')->fetch_all(MYSQLI_ASSOC),'Field');
if(!in_array('calculation_settings_json',$columns,true))throw new RuntimeException('Apply migrations 002 and 003 first.');
$db->query(file_get_contents(__DIR__.'/../migrations/004-query-apply-operations.sql'));
$receiptColumns=array_column($db->query('SHOW COLUMNS FROM genstat_query_apply_operations')->fetch_all(MYSQLI_ASSOC),'Field');if(!in_array('sequence_id',$receiptColumns,true))$db->query('ALTER TABLE genstat_query_apply_operations ADD COLUMN sequence_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT UNIQUE');
$writer=qs_config()['writer'];$schema=$writer['database'];$user=$writer['user'];$host=$writer['host'];
foreach([$schema,$user] as $identifier)if(!preg_match('/^[a-zA-Z0-9_]+$/D',$identifier))throw new RuntimeException('Unsafe settings identifier.');
if($schema!==$c['database']||$host!=='127.0.0.1')throw new RuntimeException('Grant the writer SELECT, INSERT on genstat_query_apply_operations for its configured account host.');
$db->query("GRANT SELECT, INSERT ON `$schema`.`genstat_query_apply_operations` TO '$user'@'127.0.0.1'");
echo "Added idempotent Apply & Save receipts; granted only the settings writer SELECT/INSERT on this new table. Existing configurations, history, reader grants and workbook drafts unchanged.\n";
