<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$cfg=require __DIR__.'/../config/monthly-source.php';$c=$cfg['connection'];
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli($c['host'],$c['user'],$c['password'],$c['database'],$c['port']);$db->set_charset('utf8mb4');
$db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
echo 'SERVER '.$db->server_info.PHP_EOL;
echo 'TABLES '.json_encode($db->query('SHOW FULL TABLES')->fetch_all(MYSQLI_ASSOC)).PHP_EOL;
foreach(['area_master','area_mapping'] as $table){echo $table.' '.json_encode($db->query('SELECT * FROM `'.$table.'`')->fetch_all(MYSQLI_ASSOC)).PHP_EOL;}
$db->rollback();
