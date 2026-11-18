<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$c=(require __DIR__.'/../config/monthly-source.php')['connection'];mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli($c['host'],$c['user'],$c['password'],$c['database'],$c['port']);
$columns=array_column($db->query('SHOW COLUMNS FROM genstat_row_query_mappings')->fetch_all(MYSQLI_ASSOC),'Field');
if(in_array('calculation_settings_json',$columns,true)){echo "Calculation settings migration already applied.\n";exit;}
$db->query(file_get_contents(__DIR__.'/../migrations/003-query-calculation-settings.sql'));
echo "Added nullable calculation settings. Existing configurations remain unchanged.\n";
