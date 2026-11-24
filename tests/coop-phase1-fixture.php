<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli')exit;
$directory=realpath($argv[2]??'');
if(!$directory||strcasecmp(dirname($directory),realpath(sys_get_temp_dir()))!==0||!str_starts_with(basename($directory),'genstat-coop-phase1-'))
 throw new RuntimeException('Expected an isolated COOP test directory.');
$config=(require __DIR__.'/../config/monthly-source.php')['connection'];
$db=new mysqli($config['host'],$config['user'],$config['password'],$config['database'],$config['port']);
$path=$directory.'/fixture.json';
if($argv[1]==='setup'){
 $suffix=bin2hex(random_bytes(6));
 $database='coop_fixture_'.$suffix;$username='coop_reader_'.$suffix;$password=bin2hex(random_bytes(20));
 $writer='coop_writer_'.$suffix;$writerPassword=bin2hex(random_bytes(20));
 $connection=['type'=>'local','host'=>'127.0.0.1','port'=>$config['port'],'database'=>$database,'username'=>$username,'password'=>$password];
 file_put_contents($path,json_encode($connection,JSON_THROW_ON_ERROR));
 $db->query("CREATE DATABASE `$database`");
 $db->query("CREATE TABLE `$database`.area_master LIKE area_master");
 $db->query("INSERT INTO `$database`.area_master SELECT * FROM area_master");
 $db->select_db($database);
 foreach(glob(__DIR__.'/../migrations/*.sql') as $migration){
  $db->multi_query(file_get_contents($migration));
  while($db->more_results())$db->next_result();
 }
 $db->query("CREATE USER '$writer'@'127.0.0.1' IDENTIFIED BY '$writerPassword'");
 foreach(['coop_registered_accounts','coop_audit','genstat_query_definitions','genstat_row_query_mappings','genstat_query_history','genstat_query_apply_operations'] as $table)
  $db->query("GRANT SELECT, INSERT, UPDATE, DELETE ON `$database`.`$table` TO '$writer'@'127.0.0.1'");
 $db->query("GRANT SELECT, INSERT, UPDATE ON `$database`.genstat_settings_security TO '$writer'@'127.0.0.1'");
 $writerConfig=['host'=>'127.0.0.1','port'=>$config['port'],'database'=>$database,'user'=>$writer,'password'=>$writerPassword];
 file_put_contents($directory.'/web/config/query-settings.local.php','<?php $cfg=require '.
  var_export(realpath(__DIR__.'/../config/query-settings.local.php'),true).'; $cfg["writer"]='.var_export($writerConfig,true).';return $cfg;');
 $db->query("CREATE TABLE master (AcctCode VARCHAR(16), AcctNo VARCHAR(16), Name VARCHAR(255), Status CHAR(1) NOT NULL DEFAULT 'A')");
 $db->query("CREATE TABLE billhistory (AcctNo VARCHAR(16), BillMonth CHAR(6), KWH DECIMAL(19,2))");
 $db->query("INSERT INTO master (AcctCode,AcctNo,Name) VALUES ('001','0001','Fixture Alpha'),('002','0002','Fixture Zero'),('003','0003','Fixture Review'),('004','0004','Fixture Missing'),('005','0001','Alias'),('AMB','9991','First'),('AMB','9992','Second')");
 $db->query("INSERT INTO billhistory VALUES ('0001','202607',12.35),('0001','202608',22.55),('0002','202607',0),('0003','202607',1),('0003','202607',2)");
 $db->query("CREATE TABLE `$database`.monthly (BillMonth CHAR(6), Area VARCHAR(40), KWH DECIMAL(24,9))");
 $db->query("INSERT INTO `$database`.monthly VALUES ('202607','Main',123.123456789),('202607','Narra',1.000000001),('202608','Main',2.250000000)");
 $db->query("CREATE TABLE `$database`.accounts (BillMonth CHAR(6), AreaID CHAR(2), AreaName VARCHAR(40), AccountID VARCHAR(20), AccountName VARCHAR(60), KWH DECIMAL(24,9))");
 $rows=[];
 for($i=1;$i<=510;$i++){
  $code=str_pad((string)$i,4,'0',STR_PAD_LEFT);
  $rows[]="('202607','01','Main','$code','Fixture $code',1.000000001)";
 }
 $rows[]="('202607','02','Narra','0001','Duplicate account',2.000000000)";
 $rows[]="('202607','02','Narra','7000','Narra fixture',2.500000000)";
 $rows[]="('202608','01','Main','9999','August only',0.000000000)";
 $db->query("INSERT INTO `$database`.accounts VALUES ".implode(',',$rows));
 $db->query("CREATE VIEW `$database`.slow_accounts AS SELECT * FROM `$database`.accounts WHERE SLEEP(4)=0");
 $db->query("CREATE USER '$username'@'127.0.0.1' IDENTIFIED BY '$password'");
 $db->query("GRANT SELECT ON `$database`.monthly TO '$username'@'127.0.0.1'");
 $db->query("GRANT SELECT ON `$database`.accounts TO '$username'@'127.0.0.1'");
 $db->query("GRANT SELECT ON `$database`.slow_accounts TO '$username'@'127.0.0.1'");
 $db->query("GRANT SELECT ON `$database`.master TO '$username'@'127.0.0.1'");
 $db->query("GRANT SELECT ON `$database`.billhistory TO '$username'@'127.0.0.1'");
}elseif(in_array($argv[1],['seed-pagination','cleanup','billing-off','billing-on','check-export','connection-off','connection-on','seed-inactive','activate-pending','deactivate-pending','require-canonical','allow-pending','expire-settings-lockout'],true)&&is_file($path)){
 ['database'=>$database,'username'=>$username]=json_decode(file_get_contents($path),true,32,JSON_THROW_ON_ERROR);
 if(!preg_match('/^coop_fixture_[a-f0-9]{12}$/D',$database)||!preg_match('/^coop_reader_[a-f0-9]{12}$/D',$username))
  throw new RuntimeException('Unsafe COOP fixture cleanup.');
 if($argv[1]==='expire-settings-lockout'){
  $db->select_db($database);
  $db->execute_query('UPDATE genstat_settings_security SET LockedUntil=? WHERE ID=?',[time()-1,1]);
  exit;
 }
 if(in_array($argv[1],['require-canonical','allow-pending'],true)){
  $db->select_db($database);
  $db->query($argv[1]==='require-canonical'
   ?'ALTER TABLE coop_registered_accounts MODIFY COLUMN AcctNo VARBINARY(128) NOT NULL'
   :file_get_contents(__DIR__.'/../migrations/006-coop-unresolved-accounts.sql'));
  exit;
 }
 if(in_array($argv[1],['billing-off','billing-on'],true)){
  $db->select_db($database);
  $db->query($argv[1]==='billing-off'?'RENAME TABLE billhistory TO billing_off':'RENAME TABLE billing_off TO billhistory');
  exit;
 }
 if(in_array($argv[1],['connection-off','connection-on'],true)){
  [$from,$to]=$argv[1]==='connection-off'?[$username,$username.'_off']:[$username.'_off',$username];
  $db->query("RENAME USER '$from'@'127.0.0.1' TO '$to'@'127.0.0.1'");
  exit;
 }
 if($argv[1]==='seed-inactive'){
  $db->select_db($database);
  $db->query("INSERT INTO master VALUES ('70-0550-0750','0070','Fixture Pending','D'),('70-0550-0750','0071','Fixture Inactive','D'),('OFFLINE','0070','Fixture Alias','D'),('INACTIVE','0072','Fixture Inactive Only','D')");
  $db->query("INSERT INTO billhistory VALUES ('0070','202607',7.25),('0070','202608',9.50),('0071','202608',100.00)");
  exit;
 }
 if(in_array($argv[1],['activate-pending','deactivate-pending'],true)){
  $db->select_db($database);
  $db->execute_query("UPDATE master SET Status=? WHERE AcctNo='0070'",[$argv[1]==='activate-pending'?'A':'D']);
  exit;
 }
 if($argv[1]==='check-export'){
  require_once __DIR__.'/../lib/base-report.php';
  $layout=base_report_layout();
  $part=$layout['sheets'][0]['path'];
  $book=xml_doc(zip_text(zip_read($directory.'/coop-export.xlsx'),$part));
  $xpath=xml_path($book);
  $source=$xpath->query('//s:c[@r="C26"]/s:v')->item(0)?->textContent;
  $total=$xpath->query('//s:c[@r="Q26"]/s:v')->item(0)?->textContent;
  $original=xml_path(xml_doc(zip_text(zip_read(__DIR__.'/../excel-files/reference/GENSTAT_BASE.xlsx'),$part)));
  $shared='//s:f[@si="4"]';
  $definitions=static fn(DOMXPath $path)=>array_map(fn($node)=>$node->C14N(),iterator_to_array($path->query($shared)));
  if($source!=='12.35'||abs((float)$total-12.35)>0.000001||$definitions($xpath)!==$definitions($original))
   throw new RuntimeException('Export must retain effective COOP KWH and the shared template formulas.');
  exit;
 }
 if($argv[1]==='seed-pagination'){
  $db->select_db($database);
  $events=$db->query('SELECT action,COUNT(*) FROM coop_audit GROUP BY action')->fetch_all(MYSQLI_NUM);
  $counts=array_column($events,1,0);
  foreach(['accounts-add'=>4,'accounts-transfer'=>1,'accounts-remove'=>1] as $event=>$count)
   if((int)($counts[$event]??0)!==$count)throw new RuntimeException('Unexpected audit count for '.$event);
  foreach(['master'=>7,'billhistory'=>5] as $table=>$count)
   if((int)$db->query("SELECT COUNT(*) FROM $table")->fetch_row()[0]!==$count)
    throw new RuntimeException('Fixture COOP source row count changed.');
  $area=$db->query('SELECT MIN(AreaID) FROM area_master')->fetch_row()[0];
  for($i=0;$i<25;$i++){
   $db->execute_query('INSERT INTO master (AcctCode,AcctNo,Name) VALUES (?,?,?)',
    ['PAGE'.$i,'PAGENO'.$i,'Pagination '.$i]);
   $db->execute_query('INSERT INTO coop_registered_accounts (AcctCode,AcctNo,AccountName,GenstatAreaID) VALUES (?,?,?,?)',
    ['PAGE'.$i,'PAGENO'.$i,'Pagination '.$i,$area]);
  }
  exit;
 }
 $db->query("DROP USER IF EXISTS '$username'@'127.0.0.1'");
 $db->query("DROP USER IF EXISTS '{$username}_off'@'127.0.0.1'");
 $writer='coop_writer_'.substr($database,strlen('coop_fixture_'));
 $db->query("DROP USER IF EXISTS '$writer'@'127.0.0.1'");
 $db->query("DROP DATABASE IF EXISTS `$database`");
}
