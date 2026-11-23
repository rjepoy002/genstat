<?php
declare(strict_types=1);
require_once __DIR__.'/coop-report.php';

function coop_actor(string $action): string {
 $actor=$_SERVER['REMOTE_USER']??'';
 if(coop_development_active())return COOP_LOCAL_DEV_ACTOR;
 $admins=explode(',',getenv('GENSTAT_COOP_ADMINS')?:'');
 $operators=explode(',',getenv('GENSTAT_COOP_OPERATORS')?:'');
 if(!is_string($actor)||$actor===''||$actor===COOP_LOCAL_DEV_ACTOR||strlen($actor)>190||
    (!in_array($actor,$admins,true)&&(!in_array($actor,$operators,true)||in_array($action,['connection-view','connection-save','connection-test','connection-remove'],true))))
  throw new RuntimeException('COOP authorization required.',403);
 return $actor;
}
function coop_secret_path(): string {
 $path=getenv('GENSTAT_COOP_SECRET_FILE')?:'';
 $dir=$path!==''?realpath(dirname($path)):false;
 $webroots=[dirname(__DIR__),$_SERVER['DOCUMENT_ROOT']??''];
 if(!$dir||is_link($path))throw new DomainException('Configure a protected external COOP secret directory.');
 foreach($webroots as $root){
  $root=$root!==''?realpath($root):false;
  if($root&&str_starts_with(strtolower($dir.DIRECTORY_SEPARATOR),strtolower($root.DIRECTORY_SEPARATOR)))
   throw new DomainException('Configure a protected external COOP secret directory.');
 }
 return $dir.DIRECTORY_SEPARATOR.basename($path);
}
function coop_secret_key(): string {
 $key=base64_decode(getenv('GENSTAT_COOP_SECRET_KEY')?:'',true);
 if($key===false||strlen($key)!==32)throw new DomainException('Configure the server-side COOP encryption key.');
 return $key;
}
function coop_connection_defaults(): array {
 return ['host'=>'127.0.0.1','port'=>3306,'database'=>'palecxzp_pal_db','username'=>'','ca'=>''];
}
function coop_loopback_host(mixed $host): bool {
 return in_array($host,['127.0.0.1','::1'],true);
}
function coop_local_source(array $settings): bool {
 return coop_loopback_host($settings['host']??null)&&($settings['ca']??null)==='';
}
function coop_local_port_allowed(int $port): bool {
 return coop_development_active()&&in_array((string)$port,array_map('trim',explode(',',getenv('GENSTAT_COOP_LOCAL_PORTS')?:'')),true);
}
function coop_local_preview_allowed(array $settings): bool {
 return ($_SERVER['HTTPS']??'')==='on'&&isset($settings['secret'])
  &&coop_local_source($settings)&&coop_local_port_allowed((int)($settings['port']??0));
}
function coop_connection_settings(): array {
 $path=coop_secret_path();
 if(!is_file($path))return coop_connection_defaults();
 $stored=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);
 if(!is_array($stored))throw new DomainException('External configuration requires attention.');
 return $stored+coop_connection_defaults();
}
function coop_connection_metadata(array $settings): array {
 return array_intersect_key($settings,coop_connection_defaults());
}
function coop_connection_version(array $settings): string {
 return $settings['version']??(isset($settings['secret'])?'legacy:'.hash('sha256',$settings['secret']):'unconfigured');
}
function coop_connection_snapshot(array $settings): array {
 $ca=$settings['ca']??'';
 return [coop_connection_version($settings),$ca!==''&&is_readable($ca)?hash_file('sha256',$ca):null];
}
function coop_connection_password(array $settings): string {
 $encoded=base64_decode($settings['secret']??'',true);
 if($encoded===false||strlen($encoded)<=28)throw new DomainException('External credentials are unavailable.');
 $password=openssl_decrypt(substr($encoded,28),'aes-256-gcm',coop_secret_key(),OPENSSL_RAW_DATA,substr($encoded,0,12),substr($encoded,12,16),json_encode(coop_connection_metadata($settings),JSON_THROW_ON_ERROR));
 if($password===false)throw new DomainException('External credentials are unavailable.');
 return $password;
}
function coop_connection_fingerprint(array $settings): string {
 return hash_hmac('sha256',json_encode([coop_connection_metadata($settings),coop_connection_password($settings),$settings['ca']===''?null:hash_file('sha256',$settings['ca'])],JSON_THROW_ON_ERROR),coop_secret_key());
}
function coop_connection_test_receipt(string $actor,array $candidate,array $current): array {
 return ['actor'=>$actor,'baseline'=>coop_connection_version($current),'fingerprint'=>coop_connection_fingerprint($candidate),'expires'=>time()+300,'testedAt'=>gmdate('c')];
}
function coop_require_connection_test(?array $receipt,string $actor,array $candidate,array $current): void {
 if(!$receipt||$receipt['actor']!==$actor||$receipt['expires']<=time()||$receipt['baseline']!==coop_connection_version($current)
    ||!hash_equals($receipt['fingerprint'],coop_connection_fingerprint($candidate)))
  throw new DomainException('Test these exact connection settings again before saving.');
}
function coop_connection_lock(int $mode){
 $path=coop_secret_path().'.lock';
 if(is_link($path))throw new DomainException('Configure a protected external COOP secret directory.');
 $lock=fopen($path,'c');
 if(!$lock)throw new DomainException('Unable to lock external connection settings.');
 if(!flock($lock,$mode)){fclose($lock);throw new DomainException('Unable to lock external connection settings.');}
 return $lock;
}
function coop_connection_input(array $input,array $current): array {
 $host=$input['host']??null;$port=filter_var($input['port']??null,FILTER_VALIDATE_INT);
 $db=$input['database']??null;$user=$input['username']??null;$ca=$input['ca']??null;
 if(!coop_loopback_host($host)&&(!is_string($host)||!filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)||!str_contains($host,'.')))
  throw new DomainException('Enter a valid database hostname.');
 if(!$port||$port<1||$port>65535)throw new DomainException('Enter a valid TCP port.');
 if(!is_string($db)||!preg_match('/^[A-Za-z0-9_]{1,64}$/D',$db)||strcasecmp($db,'genstat_db')===0||!is_string($user)||!preg_match('/^[A-Za-z0-9_.-]{1,64}$/D',$user)||strtolower($user)==='root')
  throw new DomainException('Enter an approved database and dedicated read-only username.');
 $next=['host'=>$host,'port'=>$port,'database'=>$db,'username'=>$user,'ca'=>$ca];
 if(coop_local_source($next)){
  if(!coop_local_port_allowed($port))throw new DomainException('Local MySQL requires verified development mode and an approved loopback port.');
 }else{
  if(!in_array($host.':'.$port,explode(',',getenv('GENSTAT_COOP_ENDPOINTS')?:''),true))
   throw new DomainException('This database endpoint is not approved by the server configuration. Contact the system administrator.');
  if(!is_string($ca)||!in_array($ca,explode(PATH_SEPARATOR,getenv('GENSTAT_COOP_CA_FILES')?:''),true)||!is_file($ca)||!is_readable($ca))
   throw new DomainException('Select a provisioned TLS CA certificate path.');
 }
 $password=$input['password']??'';
 if(!is_string($password)||strlen($password)>1024)throw new DomainException('Invalid credential replacement.');
 if($password!==''){
  $iv=random_bytes(12);$tag='';
  $encrypted=openssl_encrypt($password,'aes-256-gcm',coop_secret_key(),OPENSSL_RAW_DATA,$iv,$tag,json_encode($next,JSON_THROW_ON_ERROR));
  if($encrypted===false)throw new RuntimeException('Unable to protect external credentials.');
  $next['secret']=base64_encode($iv.$tag.$encrypted);
 }elseif(isset($current['secret'])){
  if($next!==coop_connection_metadata($current))throw new DomainException('Enter the password explicitly when changing the connection identity.');
  $next['secret']=$current['secret'];
 }else throw new DomainException('Enter the dedicated reader password.');
 return $next;
}
function coop_cloud(array $settings): mysqli {
 if(!coop_local_source($settings)&&getenv('GENSTAT_COOP_CLOUD_ENABLED')!=='1')throw new DomainException('External connection is disabled by deployment.');
 if(!isset($settings['secret']))throw new DomainException('Configure a saved external connection first.');
 $settings=coop_connection_input($settings,$settings);
 $password=coop_connection_password($settings);
 mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
 $db=mysqli_init();
 try{
  $local=coop_local_source($settings);
  if(!$db->options(MYSQLI_OPT_CONNECT_TIMEOUT,3)||!$db->options(MYSQLI_OPT_READ_TIMEOUT,5)||
     !$db->options(MYSQLI_OPT_LOCAL_INFILE,0))throw new RuntimeException;
  $flags=0;
  if(!$local){
   if(!$db->ssl_set(null,null,$settings['ca'],null,null))throw new RuntimeException;
   $flags=MYSQLI_CLIENT_SSL|MYSQLI_CLIENT_SSL_VERIFY_SERVER_CERT;
  }
  $db->real_connect($settings['host'],$settings['username'],$password,$settings['database'],$settings['port'],null,$flags);
  if(!$local){
   $tls=$db->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetch_row();
   if(empty($tls[1]))throw new RuntimeException;
  }
  $db->set_charset('utf8mb4');
  $grants=[];
  foreach($db->query('SHOW GRANTS')->fetch_all(MYSQLI_NUM) as [$grant]){
   if(stripos($grant,'GRANT OPTION')!==false||!preg_match('/^GRANT (USAGE|SELECT) ON (.*?) TO /i',$grant,$match))throw new DomainException('Insufficient read-only permissions.');
   $object=str_replace('`','',$match[2]);
   if(strtoupper($match[1])==='USAGE'&&$object==='*.*')continue;
   if(strtoupper($match[1])!=='SELECT')throw new DomainException('Insufficient read-only permissions.');
   if(!in_array($object,[$settings['database'].'.master',$settings['database'].'.billhistory'],true))throw new DomainException('Insufficient read-only permissions.');
   $grants[$object]=true;
  }
  if(count($grants)!==2)throw new DomainException('Insufficient read-only permissions.');
  $db->query(stripos($db->server_info,'mariadb')!==false?'SET SESSION max_statement_time=4':'SET SESSION max_execution_time=4000');
  return $db;
  }catch(Throwable $error){
  $db->close();
  if($error instanceof DomainException)throw $error;
  $message=match($error->getCode()){
   1045=>'Database authentication failed.',
   1049=>'The selected database is unavailable.',
   1205,1969,3024=>'Database operation timed out.',
   default=>$local?'Local MySQL connection failed.':'External connection or verified TLS failed.'
  };
  throw new DomainException($message);
 }
}
function coop_query(mysqli $db,string $sql,array $params=[]): array {
 $statement=$db->prepare($sql);
 try{$statement->execute($params);return $statement->get_result()->fetch_all(MYSQLI_ASSOC);}
 finally{$statement->close();}
}
function coop_cloud_schema(mysqli $db,bool $identityOnly=false): void {
 $columns=[];
 $tables=$identityOnly?['master'=>['AcctCode','AcctNo']]:['master'=>['AcctCode','AcctNo'],'billhistory'=>['AcctNo','BillMonth']];
 $scope=$identityOnly?"TABLE_NAME='master'":"TABLE_NAME IN ('master','billhistory')";
 foreach(coop_query($db,"SELECT TABLE_NAME,COLUMN_NAME,DATA_TYPE,NUMERIC_SCALE,CHARACTER_MAXIMUM_LENGTH FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND $scope") as $row)
  $columns[$row['TABLE_NAME']][$row['COLUMN_NAME']]=$row;
 foreach($tables as $table=>$names)
  foreach($names as $name)if(!in_array($columns[$table][$name]['DATA_TYPE']??null,['char','varchar','binary','varbinary'],true)||(int)$columns[$table][$name]['CHARACTER_MAXIMUM_LENGTH']>128)
   throw new DomainException('COOP source identifier or billing-period schema requires review.');
 if($identityOnly)return;
 $kwh=$columns['billhistory']['KWH']??[];
 if(!in_array($kwh['DATA_TYPE']??null,['decimal','tinyint','smallint','mediumint','int','bigint'],true)||(int)$kwh['NUMERIC_SCALE']>12)
  throw new DomainException('COOP source KWH requires a reviewed exact decimal type.');
}
function coop_enrollment_source(?array $source=null): mysqli {
 try{$source??=coop_connection_settings();}
 catch(JsonException){throw new DomainException('COOP connection settings require configuration.');}
 if(!isset($source['secret']))throw new DomainException('Configure a saved COOP connection before enrolling accounts.');
 $db=coop_cloud($source);
 try{coop_cloud_schema($db,true);}
 catch(Throwable $error){$db->close();throw $error;}
 return $db;
}
function coop_master(mysqli $db,array $codes,?float $deadline=null): array {
 $result=[];
 foreach(array_chunk(array_values(array_unique($codes)),50) as $chunk){
  if($deadline!==null&&microtime(true)>$deadline)throw new DomainException('Cloud refresh exceeded its time budget.');
  $placeholders=implode(',',array_fill(0,count($chunk),'?'));
  foreach(coop_query($db,"SELECT AcctCode,AcctNo FROM master WHERE AcctCode IN ($placeholders) LIMIT 1001",$chunk) as $index=>$row){
   if($index>=1000)throw new DomainException('Cloud account lookup exceeded the review limit.');
   $result[$row['AcctCode']][]=$row;
  }
 }
 return $result;
}
function coop_bills(mysqli $db,array $identities,string $period,?float $deadline=null): array {
 $result=[];
 foreach(array_chunk(array_values(array_unique($identities)),50) as $chunk){
  if($deadline!==null&&microtime(true)>$deadline)throw new DomainException('Cloud refresh exceeded its time budget.');
  $placeholders=implode(',',array_fill(0,count($chunk),'?'));
  $rows=coop_query($db,"SELECT BINARY AcctNo AS identity,COUNT(*) AS records,COUNT(KWH) AS valid_records,SUM(KWH) AS kwh FROM billhistory WHERE BillMonth=? AND AcctNo IN ($placeholders) GROUP BY BINARY AcctNo LIMIT 1001",[$period,...$chunk]);
  if(count($rows)>1000)throw new DomainException('Cloud billing lookup exceeded the review limit.');
  foreach($rows as $row)$result[$row['identity']]=$row;
 }
 return $result;
}
