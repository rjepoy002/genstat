<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$action=$argv[1]??'';$root=realpath($argv[2]??'');$temp=realpath(sys_get_temp_dir());
if(!$root||!$temp||strtolower(dirname($root))!==strtolower($temp)||!str_starts_with(basename($root),'genstat-apply-save-'))throw new RuntimeException('Unsafe isolated test root.');
$c=(require __DIR__.'/../config/monthly-source.php')['connection'];mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
if(!in_array($c['host'],['127.0.0.1','::1','localhost'],true))throw new RuntimeException('Test fixtures require a loopback server.');
$db=new mysqli($c['host'],$c['user'],$c['password'],$c['database'],$c['port']);$db->set_charset('utf8mb4');
function fixture_production_hash(mysqli $db,string $schema): string {$all=[];foreach(['genstat_query_definitions','genstat_row_query_mappings','genstat_query_history','genstat_query_apply_operations'] as $table)$all[$table]=$db->query("SELECT * FROM `$schema`.`$table` ORDER BY ".($table==='genstat_query_apply_operations'?'sequence_id':'id'))->fetch_all(MYSQLI_ASSOC);return hash('sha256',json_encode($all,JSON_THROW_ON_ERROR));}
if($action==='setup'){
 $schema='genstat_apply_test_'.bin2hex(random_bytes(6));$user='genstat_apply_'.bin2hex(random_bytes(5));$password=bin2hex(random_bytes(24));$state=['schema'=>$schema,'user'=>$user,'productionHash'=>fixture_production_hash($db,$c['database'])];file_put_contents($root.'/fixture-state.json',json_encode($state,JSON_THROW_ON_ERROR));
 $db->query("CREATE DATABASE `$schema` CHARACTER SET utf8mb4");$db->select_db($schema);
 foreach(explode(';',file_get_contents(__DIR__.'/../migrations/001-query-settings.sql')) as $sql)if(trim($sql))$db->query($sql);
 foreach(['002-row-formula-replacement.sql','003-query-calculation-settings.sql','004-query-apply-operations.sql'] as $migration)$db->query(file_get_contents(__DIR__.'/../migrations/'.$migration));
 $db->query("CREATE USER '$user'@'127.0.0.1' IDENTIFIED BY '$password'");foreach(['genstat_query_definitions','genstat_row_query_mappings','genstat_query_history'] as $table)$db->query("GRANT SELECT, INSERT, UPDATE ON `$schema`.`$table` TO '$user'@'127.0.0.1'");$db->query("GRANT SELECT, INSERT ON `$schema`.`genstat_query_apply_operations` TO '$user'@'127.0.0.1'");
 $cfg=require __DIR__.'/../config/query-settings.local.php';$cfg['writer']=['host'=>'127.0.0.1','port'=>$c['port'],'database'=>$schema,'user'=>$user,'password'=>$password];if(!is_dir($root.'/config'))mkdir($root.'/config');file_put_contents($root.'/config/query-settings.local.php',"<?php\nreturn ".var_export($cfg,true).";\n");
 $ids=[1,8,9,10,12,2,5,15,4,3,6,19,16,18,11];$bindings=[];foreach($ids as $i=>$id)$bindings[chr(66+$i).'11']=['areaId'=>(string)$id,'sacode'=>''];
 $sql='WITH params AS (SELECT :ReportMonth AS ReportMonth) SELECT AreaID, AreaName, 1200838.00 + CAST(RIGHT((SELECT ReportMonth FROM params),2) AS UNSIGNED) - 7 AS Sales_KWH, 455.00 AS KWH_Diff, 36.00 AS Total_Cancelled_KWH, 1201293.00 AS TotalKWhSold FROM area_master WHERE AreaID IN ('.implode(',',$ids).')';$queryKey='legacy_existing';$now='2026-01-01 00:00:00';
 $stmt=$db->prepare("INSERT INTO genstat_query_definitions(query_key,sql_text,area_column,sacode_column,created_at,updated_at) VALUES(?,?,'AreaID','',?,?)");$stmt->bind_param('ssss',$queryKey,$sql,$now,$now);$stmt->execute();$id=$db->insert_id;$key='genstat-base/Jul26/A11';$hash=hash_file('sha256',__DIR__.'/../excel-files/reference/GENSTAT_BASE.xlsx');$label='a.)  NPC/PPGI/DELTA P/DMCI';$json=json_encode($bindings,JSON_THROW_ON_ERROR);
 $stmt=$db->prepare("INSERT INTO genstat_row_query_mappings(template_row_key,template_hash,row_label,query_definition_id,value_column,bindings_json,excluded_json,enabled,created_at,updated_at) VALUES(?,?,?,?,'TotalKWhSold',?,'[]',0,?,?)");$stmt->bind_param('sssisss',$key,$hash,$label,$id,$json,$now,$now);$stmt->execute();echo "Isolated writer database and disabled legacy fixture created. Reader account/source tables unchanged.\n";exit;
}
$state=json_decode(file_get_contents($root.'/fixture-state.json'),true,512,JSON_THROW_ON_ERROR);$schema=$state['schema'];$user=$state['user'];if(!preg_match('/^genstat_apply_test_[a-f0-9]{12}$/D',$schema)||!preg_match('/^genstat_apply_[a-f0-9]{10}$/D',$user))throw new RuntimeException('Unsafe test schema/account.');if($action==='cleanup'){
 $unchanged=fixture_production_hash($db,$c['database'])===$state['productionHash'];
 $db->query("DROP USER IF EXISTS '{$user}_coop'@'127.0.0.1'");
 $db->query("DROP DATABASE IF EXISTS `{$schema}_coop`");
 $db->query("DROP USER IF EXISTS '$user'@'127.0.0.1'");$db->query("DROP DATABASE IF EXISTS `$schema`");if(!$unchanged)throw new RuntimeException('Production settings changed during the test; fixture did not write them.');echo "Isolated schema/account removed; production configurations/history/receipts unchanged.\n";exit;
}
$db->select_db($schema);
if($action==='coop-local-grant-write'||$action==='coop-local-revoke-write'){
 $source=$schema.'_coop';$reader=$user.'_coop';
 $db->query($action==='coop-local-grant-write'
  ?"GRANT INSERT ON `$source`.master TO '$reader'@'127.0.0.1'"
  :"REVOKE INSERT ON `$source`.master FROM '$reader'@'127.0.0.1'");
 exit;
}
if($action==='coop-setup'){
 if(!in_array($c['host'],['127.0.0.1','localhost','::1'],true))throw new RuntimeException('COOP fixture requires a loopback server.');
 foreach(explode(';',file_get_contents(__DIR__.'/../migrations/005-coop-consumption.sql')) as $sql)if(trim($sql))$db->query($sql);
 foreach(['coop_account_lock','coop_enrollment_state','coop_consumption_accounts'] as $table)$db->query("GRANT SELECT,INSERT,UPDATE ON `$schema`.`$table` TO '$user'@'127.0.0.1'");
 foreach(['coop_audit','coop_runs','coop_applications'] as $table)$db->query("GRANT SELECT,INSERT ON `$schema`.`$table` TO '$user'@'127.0.0.1'");
 $db->query("CREATE TABLE master(AcctCode VARCHAR(20),AcctNo VARCHAR(20))");
 $db->query("CREATE TABLE billhistory(AcctNo VARCHAR(20),BillMonth CHAR(6),KWH DECIMAL(20,12))");
 $db->query("INSERT INTO master VALUES('001','0001'),('002','0002'),('003','0003'),('004','0004'),('101','0001'),('006','0006'),('006','0007')");
 $db->query("INSERT INTO billhistory VALUES('0001','202607',0.100000000000),('0002','202607',0.200000000000),('0003','202607',0),('0001','202608',12),('0002','202608',15),('0003','202608',0)");
 foreach(['master','billhistory'] as $table)$db->query("GRANT SELECT ON `$schema`.`$table` TO '$user'@'127.0.0.1'");
 exit;
}
if($action==='coop-dev-audit'){
 $count=$db->query("SELECT COUNT(DISTINCT action) FROM coop_audit WHERE actor='GENSTAT_LOCAL_DEV' AND period='202607' AND action IN ('consumption-refreshed','preview-generated','consumption-applied') AND JSON_EXTRACT(details,'$.developmentMode')=true AND JSON_EXTRACT(details,'$.areas') IS NOT NULL")->fetch_row()[0];
 $enrolled=$db->query("SELECT COUNT(*) FROM coop_audit WHERE actor='GENSTAT_LOCAL_DEV' AND period='202608' AND action='enroll' AND JSON_EXTRACT(details,'$.developmentMode')=true AND JSON_EXTRACT(details,'$.after') IS NOT NULL")->fetch_row()[0];
 if((int)$count!==3||(int)$enrolled!==1)throw new RuntimeException('Development audit identity, period or area missing.');
 echo "PASS: development audit identity, period, area and marker.\n";
 exit;
}
if($action==='coop-local-source'||$action==='coop-local-source-hash'){
 $source=$schema.'_coop';$reader=$user.'_coop';
 if($action==='coop-local-source'){
  $password=bin2hex(random_bytes(24));
  $db->query("CREATE DATABASE `$source`");
  foreach(['master','billhistory'] as $table){
   $db->query("CREATE TABLE `$source`.$table LIKE $table");
   $db->query("INSERT INTO `$source`.$table SELECT * FROM $table");
  }
  $db->query("CREATE USER '$reader'@'127.0.0.1' IDENTIFIED BY '$password'");
  foreach(['master','billhistory'] as $table)$db->query("GRANT SELECT ON `$source`.$table TO '$reader'@'127.0.0.1'");
  echo json_encode(['host'=>'127.0.0.1','port'=>$c['port'],'database'=>$source,'username'=>$reader,'password'=>$password,'ca'=>''],JSON_THROW_ON_ERROR);
 }else{
  $master=$db->query("SELECT * FROM `$source`.master ORDER BY AcctCode,AcctNo")->fetch_all(MYSQLI_NUM);
  $bills=$db->query("SELECT * FROM `$source`.billhistory ORDER BY AcctNo,BillMonth,KWH")->fetch_all(MYSQLI_NUM);
  echo hash('sha256',json_encode([$master,$bills],JSON_THROW_ON_ERROR));
 }
 exit;
}
if($action==='coop-bill'){
 $db->query("INSERT INTO billhistory VALUES('0004','202607',0)");
 exit;
}
if($action==='coop-conflict'){
 $db->query("UPDATE genstat_row_query_mappings SET template_row_key='genstat-base/Jul26/A26',enabled=1 WHERE template_row_key='genstat-base/Jul26/A11'");
 exit;
}
if($action==='coop-clear-conflict'){
 $db->query("UPDATE genstat_row_query_mappings SET template_row_key='genstat-base/Jul26/A11',enabled=0 WHERE template_row_key='genstat-base/Jul26/A26'");
 exit;
}
if($action==='coop-export'){
 require $root.'/lib/workbook-storage.php';
 $zip=workbook_validate($root.'/coop-export.xlsx');
 $xml=xml_path(xml_doc(zip_text($zip,'xl/worksheets/sheet1.xml')));
 if((float)$xml->evaluate('string(//s:c[@r="B26"]/s:v)')!==0.3||
    $xml->evaluate('count(//s:c[@r="Q26"]/s:f)')!==1.0||
    abs((float)$xml->evaluate('string(//s:c[@r="Q26"]/s:v)')-10677.3)>1e-8||
    $xml->evaluate('count(//s:c[@r="R26"]/s:f)')!==1.0)throw new RuntimeException('COOP export lost input or formulas.');
 echo "PASS: COOP Excel export retains numeric B26 and Q26/R26 formulas.\n";exit;
}
if($action==='adjustment-export'){
 require $root.'/lib/workbook-storage.php';
 $zip=workbook_validate($root.'/adjustment-export.xlsx');
 $xml=xml_path(xml_doc(zip_text($zip,'xl/worksheets/sheet1.xml')));
 if($xml->evaluate('string(//s:c[@r="B25"]/s:v)')!=='910'
   ||$xml->evaluate('count(//s:c[@r="B25"]/s:f)')!==0.0
   ||$xml->evaluate('count(//s:c[@r="Q25"]/s:f)')!==1.0)
  throw new RuntimeException('Expected numeric A25 result and preserved total formula in export.');
 echo "PASS: A25 export contains numeric formula results and retains the workbook total formula.\n";exit;
}
if($action==='period-export'){
 require $root.'/lib/workbook-storage.php';
 $zip=workbook_validate($root.'/period-export.xlsx');
 $xml=xml_path(xml_doc(zip_text($zip,'xl/worksheets/sheet1.xml')));
 foreach(['A6'=>'For the Month of September 2027','G18'=>'1201331','F18'=>'1201331'] as $ref=>$expected){
  $actual=$xml->evaluate('string(//s:c[@r="'.$ref.'"]/*[1])');
  if($actual!==$expected)throw new RuntimeException('Unexpected exported cell '.$ref.': '.$actual);
 }
 foreach([16,19,21,34,37,46,47] as $row){
  if($xml->evaluate('string(//s:row[@r="'.$row.'"]/@hidden)')!=='1')throw new RuntimeException('Export exposed ADJ row '.$row.'.');
 }
 if($xml->evaluate('string(//s:row[@r="25"]/@hidden)')==='1')throw new RuntimeException('Export hid Bill Adjustment.');
 if($xml->evaluate('count(//s:c[@r="F18"]/s:f)')!==0.0||$xml->evaluate('string(//s:c[@r="Q18"]/s:v)')!=='18019965'||$xml->evaluate('count(//s:c[@r="Q18"]/s:f)')!==1.0)
  throw new RuntimeException('Exported calculation caches did not match the loaded period.');
 foreach(str_split('BCDEFGHIJKLMNOP') as $area){
  $cell='//s:c[@r="'.$area.'18"]';
  if($xml->evaluate('string('.$cell.'/s:v)')!=='1201331'||$xml->evaluate('count('.$cell.'/s:f)')!==0.0)
   throw new RuntimeException('Expected a numeric SQL result in '.$area.'18.');
 }
 foreach(['Q','R','S'] as $column){
  if($xml->evaluate('count(//s:c[@r="'.$column.'9"])')!==1.0||$xml->evaluate('count(//s:c[@r="'.$column.'18"])')!==1.0)
   throw new RuntimeException('Collapsed TOTAL column missing from export: '.$column);
 }
 echo "PASS: period export package, heading, preview value, protected formula and hidden row verified.\n";exit;
}
if($action==='total-unavailable'){
 $db->query("UPDATE genstat_query_definitions q JOIN genstat_row_query_mappings m ON m.query_definition_id=q.id SET q.sql_text='SELECT :ReportMonth AS ReportMonth, AreaID FROM area_master WHERE AreaID IS NULL' WHERE m.template_row_key='genstat-base/Jul26/A18'");
 exit;
}
if($action==='reader-offline'){
 $path=$root.'/config/query-settings.local.php';$cfg=require $path;$cfg['reader']['host']='127.0.0.1';$cfg['reader']['port']=1;
 file_put_contents($path,"<?php\nreturn ".var_export($cfg,true).";\n");exit;
}
if($action==='expire-test'){$session=(string)($argv[3]??'');if(!preg_match('/^[a-zA-Z0-9,-]{16,128}$/D',$session))throw new RuntimeException('Invalid fixture session.');session_save_path($root.'/storage/sessions');session_id($session);session_start();if(!isset($_SESSION['queryTest']))throw new RuntimeException('No isolated proposal to expire.');$_SESSION['queryTest']['expires']=time()-1;session_write_close();exit;}
if($action==='stats'){
 $out=[];foreach(['genstat_query_definitions','genstat_row_query_mappings','genstat_query_history','genstat_query_apply_operations'] as $table)$out[$table]=(int)$db->query('SELECT COUNT(*) FROM '.$table)->fetch_row()[0];$out['records']=$db->query('SELECT m.template_row_key,m.enabled,m.revision,q.query_key FROM genstat_row_query_mappings m JOIN genstat_query_definitions q ON q.id=m.query_definition_id ORDER BY m.id')->fetch_all(MYSQLI_ASSOC);echo json_encode($out,JSON_THROW_ON_ERROR);exit;
}
if($action==='fail-receipt'){$db->query("CREATE TRIGGER fixture_receipt_failure BEFORE INSERT ON genstat_query_apply_operations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Isolated injected receipt failure'");echo "Receipt failure injection enabled in isolated database only.\n";exit;}
if($action==='allow-receipt'){$db->query('DROP TRIGGER IF EXISTS fixture_receipt_failure');exit;}
if($action==='service-tests'){
 require $root.'/lib/query-settings.php';$l=base_report_layout();$key='genstat-base/Jul26/A18';$rows=qs_rows($l);$ids=[1,8,9,10,12,2,5,15,4,3,6,19,16,18,11];$bindings=[];foreach($ids as $i=>$id)$bindings[chr(66+$i).'18']=['areaId'=>(string)$id,'sacode'=>''];$record=qs_load($db)['genstat-base/Jul26/A11'];
 $check=function(bool $ok,string $message){if(!$ok)throw new RuntimeException($message);echo 'PASS: '.$message.PHP_EOL;};
 $check(qs_canonical_row_id($l,$key)==='genstat_total_kwh_sold','Canonical business identifier is generated without worksheet coordinates');$otherMonth=$l;$otherMonth['sheets'][0]['name']='Feb27';$check(qs_canonical_row_id($otherMonth,'genstat-base/Feb27/A18')===qs_canonical_row_id($l,$key),'Canonical identifier remains stable across reporting months');$check(qs_internal_query_key($l,'genstat-base/Jul26/A11',qs_load($db))==='legacy_existing','Existing identifiers are preserved');
 $check(qs_internal_query_key($otherMonth,'genstat-base/Feb27/A11',qs_load($db))==='legacy_existing','Legacy identifier is reused across months without renaming records');
 $db->begin_transaction();try{$config=qs_normalize($l,['rowKey'=>$key,'queryKey'=>'legacy_existing','sql'=>$record['sql_text'],'areaColumn'=>'AreaID','valueColumn'=>'TotalKWhSold','bindings'=>$bindings,'enabled'=>true]);$saved=qs_save($l,$config,0,$db);$check($saved['query_definition_id']===$record['query_definition_id'],'Legacy shared definition can remain shared when SQL is identical');$config['sql']=str_replace('1200838.00','1200839.00',$config['sql']);$config=qs_normalize($l,qs_editor_input($l,$config,qs_load($db)));$saved=qs_save($l,$config,1,$db);$check($saved['query_key']!=='legacy_existing'&&qs_load($db)['genstat-base/Jul26/A11']['sql_text']===$record['sql_text'],'Editing a shared definition forks only the selected row');$historyCount=(int)$db->query('SELECT COUNT(*) FROM genstat_query_history')->fetch_row()[0];$again=qs_save($l,$config,2,$db);$check((int)$again['revision']===2&&(int)$db->query('SELECT COUNT(*) FROM genstat_query_history')->fetch_row()[0]===$historyCount,'Identical configuration persistence deduplicates revision/history');}finally{$db->rollback();}
 $check(count(qs_load($db))===1,'Service test transaction rolled back all fixture changes');exit;
}
throw new RuntimeException('Unknown fixture command.');
