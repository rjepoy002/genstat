<?php
declare(strict_types=1);
require __DIR__.'/../lib/query-settings.php';
require __DIR__.'/../lib/coop-consumption.php';
require __DIR__.'/../lib/coop-cloud.php';
function check(bool $value,string $label): void {
 if(!$value)throw new RuntimeException($label);
 echo 'PASS: '.$label.PHP_EOL;
}
$l=base_report_layout();
$targets=coop_targets($l);
check(count($targets)===15&&$targets['main']['ref']==='B26'&&$targets['araceli/dumaran']['ref']==='J26'&&$targets['cuyo/magsaysay']['ref']==='N26','Original Summary mapping and combined areas');
$accounts=[
 ['id'=>1,'area_key'=>'main','acct_code'=>'01','canonical_acct_no'=>'0001'],
 ['id'=>2,'area_key'=>'main','acct_code'=>'02','canonical_acct_no'=>'0002'],
 ['id'=>3,'area_key'=>'aborlan','acct_code'=>'03','canonical_acct_no'=>'0003']
];
$master=[];$bills=[];
foreach($accounts as $i=>$account){
 $master[$account['acct_code']]=[['AcctCode'=>$account['acct_code'],'AcctNo'=>$account['canonical_acct_no']]];
 $bills[$account['canonical_acct_no']]=['records'=>1,'valid_records'=>1,'kwh'=>['0.1','0.2','0'][$i]];
}
$result=coop_aggregate($accounts,$master,$bills,$targets,['B26'=>'1']);
check($result['complete']&&$result['total']==='0.300000000000'&&$result['areas']['main']['difference']==='-0.700000000000'&&$result['accounts'][2]['status']==='Zero KWH','Exact aggregation, different area counts and genuine zero');
$local=coop_aggregate($accounts,$master,$bills,$targets,[],true);
check($local['complete']&&$local['areas']['main']['kwh']==='0.300000000000'&&$local['areas']['aborlan']['kwh']==='0.000000000000'&&$local['areas']['narra']['kwh']===null,'Local preview aggregates exact single-record candidates and preserves unoccupied areas');
$disputed=$bills;
$disputed['0001']=['records'=>2,'valid_records'=>2,'kwh'=>'4.00'];
$disputed['0002']['kwh']='-1.00';
unset($disputed['0003']);
$local=coop_aggregate($accounts,$master,$disputed,$targets,[],true);
check(!$local['complete']&&$local['areas']['main']['kwh']===null&&$local['accounts'][0]['count']===2&&$local['accounts'][0]['status']==='Requires billing review'&&$local['accounts'][1]['reason']==='Negative KWH'&&$local['accounts'][2]['status']==='No billing data','Local preview fails closed for multiple, negative and missing bills');
check(coop_aggregate($accounts,$master,$disputed+$bills,$targets,[])['complete'],'Reviewed aggregation contract remains available');
unset($bills['0002'],$master['03']);
$result=coop_aggregate($accounts,$master,$bills,$targets,[]);
check(!$result['complete']&&$result['areas']['main']['kwh']===null&&$result['accounts'][1]['status']==='No Bill History'&&$result['accounts'][2]['status']==='Account Not Found','Missing bill and identity prevent application');
$master['01'][]=$master['01'][0];
check(coop_aggregate($accounts,$master,$bills,$targets,[])['accounts'][0]['status']==='Ambiguous Account','Repeated master rows fail closed');
$local=coop_aggregate($accounts,$master,$bills,$targets,[],true);
check($local['accounts'][0]['status']==='Requires billing review'&&$local['accounts'][0]['reason']==='Ambiguous Account'&&$local['accounts'][2]['reason']==='Account Not Found','Local preview flags ambiguous and missing identities');
$l['sheets'][0]['cells']['B26']['editable']=false;
try{coop_targets($l);throw new RuntimeException('Protected target accepted');}
catch(DomainException){check(true,'Workbook mapping changes require review');}
unset($_SERVER['REMOTE_USER']);
foreach(['connection-save','enroll','apply'] as $action){
 try{coop_actor($action);throw new LogicException('Missing authorization accepted');}
 catch(RuntimeException $error){check($error->getCode()===403,'Unauthorized '.$action.' rejected');}
}
putenv('GENSTAT_COOP_CLOUD_ENABLED=0');
try{coop_cloud([]);throw new RuntimeException('Deployment gate accepted');}
catch(DomainException){check(true,'Cloud access disabled by default');}

$root=sys_get_temp_dir().DIRECTORY_SEPARATOR.'coop-connection-test-'.bin2hex(random_bytes(8));
if(!mkdir($root))throw new RuntimeException('Cannot create temporary test directory.');
$ca=$root.DIRECTORY_SEPARATOR.'test-ca.pem';
try{
 file_put_contents($ca,'fixture');
 putenv('GENSTAT_COOP_SECRET_FILE='.$root.DIRECTORY_SEPARATOR.'settings.json');
 putenv('GENSTAT_COOP_SECRET_KEY='.base64_encode(random_bytes(32)));
 putenv('GENSTAT_COOP_ENDPOINTS=fixture.example:3306,other.example:3306');
 putenv('GENSTAT_COOP_CA_FILES='.$ca);
 $input=['host'=>'fixture.example','port'=>3306,'database'=>'fixture','username'=>'reader','ca'=>$ca,'password'=>'fixture-password'];
 $current=coop_connection_settings();
 $candidate=coop_connection_input($input,$current);
 $receipt=coop_connection_test_receipt('admin',$candidate,$current);
 coop_require_connection_test($receipt,'admin',coop_connection_input($input,$current),$current);
 check(!str_contains($candidate['secret'],$input['password'])&&coop_connection_password($candidate)===$input['password'],'Encryption round trip and receipt survives randomized ciphertext');
 foreach([
  [null,'admin',$candidate,$current],
  [array_replace($receipt,['expires'=>time()-1]),'admin',$candidate,$current],
  [$receipt,'other',$candidate,$current],
  [$receipt,'admin',$candidate,$current+['version'=>'changed']],
  [$receipt,'admin',coop_connection_input(array_replace($input,['password'=>'updated']),$current),$current]
 ] as [$token,$actor,$submitted,$baseline]){
  try{coop_require_connection_test($token,$actor,$submitted,$baseline);throw new LogicException('Invalid receipt accepted');}
  catch(DomainException){}
 }
 check(true,'Missing, expired, mismatched and stale test receipts rejected');
 foreach([['host'=>'https://fixture.example'],['port'=>65536],['host'=>'unapproved.example']] as $change){
  try{coop_connection_input(array_replace($input,$change),$current);throw new LogicException('Invalid endpoint accepted');}
  catch(DomainException){}
 }
 $blank=array_replace($input,['password'=>'']);
 check(coop_connection_input($blank,$candidate)['secret']===$candidate['secret'],'Unchanged configured connection may retain its password');
 try{coop_connection_input(array_replace($blank,['host'=>'other.example']),$candidate);throw new LogicException('Changed host accepted without a password');}
 catch(DomainException){}
 putenv('GENSTAT_COOP_SECRET_KEY');
 try{coop_connection_input($input,$current);throw new LogicException('Missing encryption key accepted');}
 catch(DomainException){}
 putenv('GENSTAT_COOP_SECRET_FILE='.$root.DIRECTORY_SEPARATOR.'missing'.DIRECTORY_SEPARATOR.'settings.json');
 try{coop_connection_settings();throw new LogicException('Missing secret directory accepted');}
 catch(DomainException){}
 check(true,'Invalid endpoints, changed host without credentials and missing secure storage rejected');
}finally{
 if(is_file($ca))unlink($ca);
 rmdir($root);
}

$direct=['REMOTE_ADDR'=>'127.0.0.1','SERVER_NAME'=>'127.0.0.1','HTTP_HOST'=>'127.0.0.1:8087','GENSTAT_COOP_DEV_DIRECT'=>'cli-router'];
check(coop_is_local_dev_request($direct,'cli-server',true),'Explicit direct loopback development request accepted');
check(!coop_is_local_dev_request($direct,'cli-server',false),'Development mode is off by default');
foreach([
 ['REMOTE_ADDR'=>'192.0.2.10'],['SERVER_NAME'=>'0.0.0.0'],['GENSTAT_COOP_DEV_DIRECT'=>''],
 ['HTTP_FORWARDED'=>'for=127.0.0.1'],['HTTP_X_FORWARDED_FOR'=>'127.0.0.1'],
 ['HTTP_X_REAL_IP'=>'127.0.0.1'],['HTTP_HOST'=>'external.invalid'],['HTTP_X_REMOTE_USER'=>'admin'],['REMOTE_USER'=>'existing-user']
] as $change)check(!coop_is_local_dev_request(array_replace($direct,$change),'cli-server',true),'Development authorization rejects '.array_key_first($change));
check(!coop_is_local_dev_request($direct+['SERVER_ADDR'=>'127.0.0.1'],'apache2handler',true),'CLI marker cannot grant Apache development access');
check(coop_is_local_dev_request(['REMOTE_ADDR'=>'::1','SERVER_ADDR'=>'::1','HTTP_HOST'=>'localhost','GENSTAT_COOP_DEV_DIRECT'=>'apache-loopback'],'apache2handler',true),'Explicit Apache loopback marker enables a direct local request');
check(!coop_is_local_dev_request(['REMOTE_ADDR'=>'::1','SERVER_ADDR'=>'192.0.2.12','HTTP_HOST'=>'localhost','GENSTAT_COOP_DEV_DIRECT'=>'apache-loopback'],'apache2handler',true),'Apache request addressed to a non-loopback listener rejected');
