<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
require_once __DIR__.'/../lib/files.php';
$root=dirname(__DIR__);$dest=$root.'/config/query-settings.local.php';
if(is_file($dest)){echo "Already configured; no accounts or tables changed.\n";exit;}
$c=(require $root.'/config/monthly-source.php')['connection'];mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$db=new mysqli($c['host'],$c['user'],$c['password'],$c['database'],$c['port']);$db->set_charset('utf8mb4');
$schema=$c['database'];if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/D',$schema))throw new RuntimeException('Invalid database name.');
$tables=['genstat_query_definitions','genstat_row_query_mappings','genstat_query_history','genstat_query_apply_operations'];
$existing=array_column($db->query('SHOW TABLES')->fetch_all(MYSQLI_NUM),0);
if(array_intersect($tables,$existing))throw new RuntimeException('Query tables already exist. Inspect and configure them manually; refusing duplicate/partial setup.');
$approved=['area_master','area_mapping','overallsalessummary','billadjustment','kwh_diff_allocation','billhistorycancelled','billhistorycancelled_2'];
if(array_diff($approved,$existing))throw new RuntimeException('Required reporting tables are missing.');
foreach(['genstat_report_reader','genstat_settings_writer'] as $user){$s=$db->prepare('SELECT COUNT(*) FROM mysql.user WHERE User=?');$s->bind_param('s',$user);$s->execute();if((int)$s->get_result()->fetch_row()[0])throw new RuntimeException('An application account already exists; refusing to change its grants.');}
foreach(explode(';',file_get_contents($root.'/migrations/001-query-settings.sql')) as $sql)if(trim($sql))$db->query($sql);
foreach(['002-row-formula-replacement.sql','003-query-calculation-settings.sql','004-query-apply-operations.sql'] as $migration)$db->query(file_get_contents($root.'/migrations/'.$migration));
$readerPass=bin2hex(random_bytes(24));$writerPass=bin2hex(random_bytes(24));
foreach(['genstat_report_reader'=>$readerPass,'genstat_settings_writer'=>$writerPass] as $user=>$password){$db->query("CREATE USER '$user'@'127.0.0.1' IDENTIFIED BY '$password'");}
foreach($approved as $table)$db->query("GRANT SELECT ON `$schema`.`$table` TO 'genstat_report_reader'@'127.0.0.1'");
foreach($tables as $table){if($table==='genstat_query_apply_operations'){$db->query("GRANT SELECT, INSERT ON `$schema`.`$table` TO 'genstat_settings_writer'@'127.0.0.1'");continue;}$db->query("GRANT SELECT, INSERT, UPDATE ON `$schema`.`$table` TO 'genstat_settings_writer'@'127.0.0.1'");}
$base=['host'=>'127.0.0.1','port'=>$c['port'],'database'=>$schema];
$config=['reader'=>$base+['user'=>'genstat_report_reader','password'=>$readerPass],'writer'=>$base+['user'=>'genstat_settings_writer','password'=>$writerPass],
 'allowedTables'=>$approved,'timeoutSeconds'=>3,'resultLimit'=>500,'node'=>'C:/Program Files/nodejs/node.exe'];
atomic_file_write($dest,"<?php\n// Local credentials: never commit or serve this file.\nreturn ".var_export($config,true).";\n");
echo "Created query configuration/history/operation tables and two least-privilege accounts. Local credentials saved without printing secrets. Existing source/area records unchanged.\n";
