<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$c=(require __DIR__.'/../config/monthly-source.php')['connection'];mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli($c['host'],$c['user'],$c['password'],$c['database'],$c['port']);
$columns=array_column($db->query('SHOW COLUMNS FROM genstat_row_query_mappings')->fetch_all(MYSQLI_ASSOC),'Field');
if(in_array('allow_formula_replacement',$columns,true)){echo "Row formula replacement migration already applied.\n";exit;}
$db->query(file_get_contents(__DIR__.'/../migrations/002-row-formula-replacement.sql'));
echo "Added default-disabled row permission. Existing configurations and source records preserved.\n";
