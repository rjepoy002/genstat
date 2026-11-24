<?php
declare(strict_types=1);
require_once __DIR__.'/query-settings.php';
require_once __DIR__.'/files.php';
require_once __DIR__.'/coop-account-preview.php';
require_once __DIR__.'/coop-https-client.php';

const COOP_PREVIEW_LIMIT = 500;

function coop_defaults(): array {
 return ['type'=>'local','host'=>'127.0.0.1','port'=>3306,'database'=>'','username'=>'root','password'=>''];
}

function coop_private_path(string $path): string {
 $directory=$path!==''?realpath(dirname($path)):false;
 if(!$directory||is_link($path)||is_link($path.'.lock')||is_dir($path))
  throw new DomainException('COOP storage is unavailable. Configure an accessible private settings file on the server.');
 foreach([dirname(__DIR__),$_SERVER['DOCUMENT_ROOT']??''] as $root){
  $root=$root!==''?realpath($root):false;
  if($root&&str_starts_with(strtolower($directory.DIRECTORY_SEPARATOR),strtolower($root.DIRECTORY_SEPARATOR)))
   throw new DomainException('COOP storage is inside a web root. Configure a private settings directory on the server.');
 }
 return $directory.DIRECTORY_SEPARATOR.basename($path);
}

function coop_windows_storage(string $installation): string {
 $windows=getenv('SystemRoot')?:'C:\Windows';
 $powershell=$windows.'/System32/WindowsPowerShell/v1.0/powershell.exe';
 $source=@file_get_contents(__DIR__.'/../tools/coop-storage-acl.ps1');
 if($source===false)throw new DomainException('The COOP Windows permission helper is missing. Restore the server installation or configure private storage manually.');
 $script=base64_encode(mb_convert_encoding($source,'UTF-16LE','UTF-8'));
 $output=@tmpfile();
 if(!$output)throw new DomainException('Apache could not prepare the Windows COOP permission check. Check its temporary directory permissions.');
 $process=is_file($powershell)?@proc_open(
  [$powershell,'-NoLogo','-NoProfile','-NonInteractive','-EncodedCommand',$script],
  [0=>['pipe','r'],1=>$output,2=>['file','NUL','a']],$pipes,null,null,['bypass_shell'=>true]
 ):false;
 if(!is_resource($process)){
  fclose($output);
  throw new DomainException('Windows could not check COOP storage permissions. Configure private COOP storage on the server.');
 }
 try{
  fwrite($pipes[0],substr(hash('sha256',strtolower($installation)),0,24)."\n");
  fclose($pipes[0]);
  $deadline=microtime(true)+8;
  do{
   $status=proc_get_status($process);
   if(!$status['running'])break;
   if(microtime(true)>=$deadline){
    proc_terminate($process);
    throw new DomainException('The Windows COOP storage permission check timed out. Check the Apache account and try again.');
   }
   usleep(20000);
  }while(true);
  if($status['exitcode']===2)
   throw new DomainException('Windows rejected unsafe COOP storage ownership, permissions, or a redirected path. Ask an administrator to secure the directory.');
  rewind($output);
  $directory=trim(stream_get_contents($output));
  if($status['exitcode']!==0||!is_dir($directory))
   throw new DomainException('Apache could not prepare private COOP storage. Check Windows permissions or configure private storage manually.');
  return $directory;
 }finally{proc_close($process);fclose($output);}
}

function coop_auto_secret_location(string $installation): array {
 $directory=coop_windows_storage($installation);
 $path=coop_private_path($directory.'/settings.json');
 $keyPath=$directory.'/key';
 $lock=@fopen($path.'.lock','c');
 if(!$lock)throw new DomainException('Apache cannot write to the private COOP settings directory.');
 try{
  if(!flock($lock,LOCK_EX))throw new DomainException('Could not lock private COOP storage.');
  clearstatcache();
  $probePath=$directory.'/write-probe-'.bin2hex(random_bytes(12));
  $probe=@fopen($probePath,'x+b');
  if(!$probe)throw new DomainException('Apache cannot create files in private COOP storage. Check Windows permissions.');
  fclose($probe);
  if(!@unlink($probePath))throw new DomainException('Apache cannot replace files in private COOP storage. Check Windows permissions.');
  if(!is_file($keyPath)){
   if(file_exists($path))throw new DomainException('The COOP encryption key is missing. Restore the original key to reopen the saved settings.');
   try{atomic_file_write($keyPath,base64_encode(random_bytes(32)));}
   catch(RuntimeException){throw new DomainException('Apache could not create the private COOP encryption key. Check Windows permissions.');}
  }
  $encoded=@file_get_contents($keyPath);
  $key=is_string($encoded)?base64_decode(trim($encoded),true):false;
  if($key===false||strlen($key)!==32)
   throw new DomainException('The COOP encryption key cannot be read or is invalid. Restore the original key or check Windows permissions.');
  if(!is_writable($directory)||!is_writable($keyPath)||(is_file($path)&&!is_writable($path)))
   throw new DomainException('Apache cannot update the private COOP settings. Check Windows permissions.');
  return [$path,$key];
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function coop_secret_location(): array {
 $path=getenv('GENSTAT_COOP_PHASE1_FILE')?:'';
 $encoded=getenv('GENSTAT_COOP_PHASE1_KEY')?:'';
 if($path!==''||$encoded!==''){
  $key=base64_decode($encoded,true);
  if($path===''||$key===false||strlen($key)!==32)
   throw new DomainException('Set both the private COOP settings file and its original 32-byte encryption key on the server.');
  $path=coop_private_path($path);
  if(!is_writable(dirname($path))||(is_file($path)&&!is_writable($path)))
   throw new DomainException('Apache cannot update the configured COOP settings file.');
  return [$path,$key];
 }
 $web=realpath($_SERVER['DOCUMENT_ROOT']??'');
 $installation=$web?dirname($web):'';
 if(PHP_OS_FAMILY!=='Windows'||!$web||strcasecmp(basename($web),'htdocs')!==0||
   !is_file($installation.'/apache/bin/httpd.exe')||!is_file($installation.'/php/php.exe'))
  throw new DomainException('Automatic COOP storage requires a Windows XAMPP web root. Configure a private settings file and encryption key on this server.');
 return coop_auto_secret_location($installation);
}

function coop_read_settings(string $path,string $key): array {
 if(!is_file($path))return ['revision'=>0,'connection'=>null,'query'=>'','queryConnection'=>null];
 $raw=base64_decode(file_get_contents($path),true);
 if(!$raw||strlen($raw)<29)throw new DomainException('COOP settings could not be read.');
 $payload=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));
 if($payload===false)throw new DomainException('The COOP settings could not be decrypted. Restore the original encryption key.');
 return json_decode($payload,true,32,JSON_THROW_ON_ERROR);
}

function coop_write_settings(string $path,string $key,array $state): void {
 $nonce=random_bytes(12);
 $tag='';
 $encrypted=openssl_encrypt(json_encode($state,JSON_THROW_ON_ERROR),'aes-256-gcm',$key,OPENSSL_RAW_DATA,$nonce,$tag);
 if($encrypted===false)throw new DomainException('COOP settings could not be encrypted.');
 atomic_file_write($path,base64_encode($nonce.$tag.$encrypted));
}

function coop_valid_host(string $host): bool {
 return strlen($host)<=253&&(filter_var($host,FILTER_VALIDATE_IP)!==false||
  (preg_match('/[a-z]/i',$host)&&!str_ends_with($host,'.')&&filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)!==false));
}

function coop_connection_input(array $input,?array $saved): array {
 $type=$input['type']??null;
 if($type==='https'){
  $apiUrl=coop_https_url($input['apiUrl']??null);
  $apiKey=$input['apiKey']??'';
  if($apiKey===''&&($saved['apiUrl']??null)===$apiUrl)$apiKey=$saved['apiKey']??'';
  if($apiKey==='')throw new DomainException('API key required.');
  if(!is_string($apiKey)||!preg_match('/\A[!-~]{1,1024}\z/',$apiKey))
   throw new DomainException('Enter a valid API key.');
  return compact('type','apiUrl','apiKey');
 }
 $host=$input['host']??null;$port=filter_var($input['port']??null,FILTER_VALIDATE_INT);
 $database=$input['database']??null;$username=$input['username']??null;$password=$input['password']??'';
 if(!is_string($host)||!coop_valid_host($host))
  throw new DomainException('Enter a valid database hostname or IP address without a URL scheme, path, or query string.');
 if(!in_array($type,['local','lan','hosted'],true)||!$port||$port<1||$port>65535||
   !is_string($database)||!preg_match('/^[A-Za-z0-9_]{1,64}$/D',$database)||
   in_array(strtolower($database),['mysql','information_schema','performance_schema','sys'],true)||
   !is_string($username)||!preg_match('/^[A-Za-z0-9_.-]{1,80}$/D',$username)||!is_string($password)||strlen($password)>1024)
  throw new DomainException('Enter a valid host, port, database, and username.');
 $explicitEmpty=($input['emptyPassword']??false)===true;
 if($explicitEmpty&&$password!=='')throw new DomainException('Clear the password field when selecting Use no password.');
 if($password===''&&!$explicitEmpty){
  if(!$saved||$saved['host']!==$host||$saved['port']!==$port||$saved['username']!==$username||
    ($saved['type']!==$type&&in_array('hosted',[$saved['type'],$type],true)))
   throw new DomainException('Enter the database password or select Use no password.');
  $password=$saved['password'];
 }
 return compact('type','host','port','database','username','password');
}

function coop_connection_profiles(array $state): array {
 $profiles=$state['connectionProfiles']??[];
 if(isset($state['connection']))$profiles[$state['connection']['type']]=$state['connection'];
 return $profiles;
}

function coop_connection_public(array $connection): array {
 $connection['hasPassword']=($connection['password']??'')!=='';
 $connection['hasApiKey']=($connection['apiKey']??'')!=='';
 unset($connection['password'],$connection['apiKey']);
 return $connection;
}

function coop_test_connection(array $connection): array {
 if($connection['type']==='https')return coop_https_health($connection);
 coop_pdo($connection);
 return ['message'=>'Connection successful.'];
}

function coop_connection_id(array $connection,string $key): string {
 unset($connection['lastSuccessfulTest']);
 return hash_hmac('sha256',json_encode($connection,JSON_THROW_ON_ERROR),$key);
}

function coop_public_address(string $ip): bool {
 if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_GLOBAL_RANGE))return false;
 $bytes=inet_pton($ip);
 if(strlen($bytes)===4)return ord($bytes[0])<224;
 // Limit IPv6 to global unicast and exclude 6to4 and Teredo tunnels.
 return (ord($bytes[0])&0xe0)===0x20&&substr($bytes,0,2)!=="\x20\x02"&&
  substr($bytes,0,4)!=="\x20\x01\x00\x00";
}

function coop_mysql_host(string $host): string {
 return str_contains($host,':')?'['.$host.']':$host;
}

function coop_approved_hosted_addresses(string $host,int $port,array $endpoints): array {
 $literal=filter_var($host,FILTER_VALIDATE_IP)!==false;
 if($literal)$host=inet_ntop(inet_pton($host));
 $approved=$endpoints[coop_mysql_host(strtolower($host)).':'.$port]??null;
 if(!is_array($approved)||!array_is_list($approved)||!$approved)
  throw new DomainException('This hosted MySQL host and port are not approved in the GENSTAT server configuration.');
 $addresses=[];
 foreach($approved as $ip){
  if(!is_string($ip)||!coop_public_address($ip))
   throw new DomainException('Hosted MySQL approval must contain only public destination IP addresses.');
  $addresses[]=inet_ntop(inet_pton($ip));
 }
 $addresses=array_values(array_unique($addresses));
 if($literal&&$addresses!==[$host])
  throw new DomainException('The hosted MySQL IP address does not match its approved destination.');
 return $addresses;
}

function coop_hosted_system_pins(string $host): array {
 $file=getenv('SystemRoot').'/System32/drivers/etc/hosts';
 $pins=[];
 foreach(@file($file,FILE_IGNORE_NEW_LINES)?:[] as $line){
  $fields=preg_split('/\s+/',trim(explode('#',$line,2)[0]),-1,PREG_SPLIT_NO_EMPTY);
  if(in_array(strtolower($host),array_map('strtolower',array_slice($fields,1)),true)&&filter_var($fields[0],FILTER_VALIDATE_IP))
   $pins[]=inet_ntop(inet_pton($fields[0]));
 }
 return array_values(array_unique($pins));
}

function coop_hosted_destination(string $host,int $port): string {
 $override=getenv('GENSTAT_COOP_HOSTED_ENDPOINTS');
 $endpoints=$override===false||$override===''?require __DIR__.'/../config/coop-hosted-endpoints.php':json_decode($override,true);
 if(!is_array($endpoints))throw new DomainException('The hosted MySQL endpoint configuration is invalid.');
 $approved=coop_approved_hosted_addresses($host,$port,$endpoints);
 if(filter_var($host,FILTER_VALIDATE_IP))return $approved[0];
 $pins=PHP_OS_FAMILY==='Windows'?coop_hosted_system_pins($host):[];
 $addresses=coop_hosted_resolved_addresses($host,$port);
 if(!$addresses)throw new DomainException('Hosted MySQL DNS resolution failed. Check the approved database hostname.');
 foreach($addresses as $ip){
  if(!coop_public_address($ip)||!in_array($ip,$approved,true))
   throw new DomainException('The hosted MySQL hostname resolved to an unapproved destination.');
 }
 // Passing the approved numeric address to PDO avoids a second DNS lookup.
 // mysqlnd verifies the certificate against that address by default.
 if(!$pins)return $addresses[0];
 if(!function_exists('socket_addrinfo_lookup')||array_diff($pins,$approved)||array_diff($addresses,$pins))
  throw new DomainException('Hosted MySQL Windows hosts-file pins require PHP sockets and must match the approved destination.');
 // An administrator-pinned Windows mapping permits verification against a DNS name.
 return $host;
}

function coop_hosted_resolved_addresses(string $host,int $port): array {
 if(!function_exists('socket_addrinfo_lookup'))return @gethostbynamel($host)?:[];
 $addresses=[];
 foreach(@socket_addrinfo_lookup($host,(string)$port,['ai_socktype'=>SOCK_STREAM])?:[] as $record){
  $addr=socket_addrinfo_explain($record)['ai_addr'];
  $ip=$addr['sin_addr']??$addr['sin6_addr']??'';
  $addresses[]=filter_var($ip,FILTER_VALIDATE_IP)?inet_ntop(inet_pton($ip)):$ip;
 }
 return array_values(array_unique($addresses));
}

function coop_pdo(array $connection): PDO {
 $host=$connection['host'];
 if(!coop_valid_host($host))throw new DomainException('Invalid database hostname or IP address.');
 $hosted=$connection['type']==='hosted';
 $localDevelopment=$connection['type']==='local';
 if($hosted){
  $target=coop_hosted_destination($host,$connection['port']);
 }else{
  if(strcasecmp($host,'localhost')===0)$host='127.0.0.1';
  $target=filter_var($host,FILTER_VALIDATE_IP)?$host:gethostbyname($host);
  if(!filter_var($target,FILTER_VALIDATE_IP))throw new DomainException('Database DNS resolution failed. Check the hostname.');
  $target=inet_ntop(inet_pton($target));
  $loopback=$target==='::1'||(filter_var($target,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)&&str_starts_with($target,'127.'));
  $private=filter_var($target,FILTER_VALIDATE_IP,FILTER_FLAG_IPV4)&&preg_match('/^(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/',$target);
  if($localDevelopment&&!$loopback)throw new DomainException('Local XAMPP requires a verified loopback address.');
  if(!$localDevelopment&&!$loopback&&!$private)
   throw new DomainException('Use localhost or a hostname or IPv4 address on your private LAN.');
 }
 if(strcasecmp($connection['username'],'root')===0&&!$localDevelopment)
  throw new DomainException('Use a dedicated read-only account for remote connections.');
 return coop_open_pdo($connection,$target,$hosted,$localDevelopment);
}

function coop_open_pdo(array $connection,string $target,bool $hosted,bool $localDevelopment): PDO {
 $options=[
  PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>3,PDO::ATTR_EMULATE_PREPARES=>false,
  PDO::ATTR_STRINGIFY_FETCHES=>true,PDO::MYSQL_ATTR_MULTI_STATEMENTS=>false
 ];
 if($hosted){
  if(!extension_loaded('mysqlnd')||!extension_loaded('openssl')||!defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT'))
   throw new DomainException('Hosted MySQL requires a TLS-enabled PHP PDO MySQL driver with mysqlnd and OpenSSL.');
  // mysqlnd requests TLS when a nonempty SSL option is provided.
  $options[PDO::MYSQL_ATTR_SSL_CIPHER]='HIGH:!aNULL:!eNULL';
  $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]=true;
  $ca=getenv('GENSTAT_COOP_HOSTED_CA_FILE');
  if($ca!==false&&$ca!==''){
   try{$ca=coop_private_path($ca);}
   catch(DomainException){throw new DomainException('The hosted MySQL CA must be a readable PEM file outside the web root.');}
   if(!is_file($ca)||!is_readable($ca))throw new DomainException('The hosted MySQL CA certificate file is unavailable.');
   $options[PDO::MYSQL_ATTR_SSL_CA]=$ca;
  }
 }
 try{
  $pdo=@new PDO('mysql:host='.coop_mysql_host($target).';port='.$connection['port'].';dbname='.$connection['database'].';charset=utf8mb4',
    $connection['username'],$connection['password'],$options);
 }catch(PDOException $e){
  $code=(int)($e->errorInfo[1]??0);
  $detail=$e->getMessage();
  $message=match(true){
   in_array($code,[1045,1698],true)=>'Database authentication failed. Check the credentials and account permissions.',
   $code===1044=>'Database access denied. Grant the account SELECT access on the configured database.',
   $code===1049=>'The configured database does not exist.',
   $hosted&&($code===2026||preg_match('/\b(?:SSL|TLS|certificate)\b/i',$detail))=>'Hosted MySQL TLS verification failed. Check the server certificate, hostname, and trusted CA.',
   $code===2005||preg_match('/getaddrinfo|no such host/i',$detail)=>'Database DNS resolution failed. Check the hostname.',
   preg_match('/timed out|timeout|10060/i',$detail)===1=>'Database connection timed out.',
   preg_match('/refused|10061|actively refused/i',$detail)===1=>'Database connection refused. Check the MySQL port and remote database access.',
   $hosted&&in_array($code,[2006,2013],true)=>'Hosted MySQL closed the connection before verified TLS could be established. Check that the port serves MySQL with TLS enabled.',
   in_array($code,[2002,2003],true)=>'Database unavailable. Check the host, port, and MySQL service.',
   default=>'Database connection failed.'
  };
  throw new DomainException($message);
 }
 try{
  if($hosted){
   $cipher=$pdo->query("SHOW SESSION STATUS LIKE 'Ssl_cipher'")->fetchColumn(1);
   if(!$cipher)throw new DomainException('Hosted MySQL did not negotiate TLS.');
  }
  $pdo->query('SELECT 1')->fetchColumn();
  $pdo->exec("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_BACKSLASH_ESCAPES'");
  $version=(string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
  $pdo->exec(stripos($version,'MariaDB')!==false?'SET SESSION max_statement_time=3':'SET SESSION max_execution_time=3000');
  if($localDevelopment)return $pdo;
  $readGrant=false;
  foreach($pdo->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN) as $grant){
   if(preg_match('/^GRANT USAGE ON \*\.\* TO /i',$grant)&&!preg_match('/WITH\s+GRANT\s+OPTION/i',$grant))continue;
   if(preg_match('/^GRANT SELECT ON ([\w`]+)\.([\w`]+|\*) TO /i',$grant,$parts)&&
     strcasecmp(str_replace('`','',$parts[1]),$connection['database'])===0&&!preg_match('/WITH\s+GRANT\s+OPTION/i',$grant)){$readGrant=true;continue;}
   throw new DomainException('Connection reached MySQL. Use an account with SELECT-only grants on this database, without GRANT OPTION.');
  }
  if(!$readGrant)throw new DomainException('Grant SELECT on the COOP source table or database before saving this connection.');
 }catch(PDOException $e){
  if(in_array((int)($e->errorInfo[1]??0),[1044,1142,1227],true))
   throw new DomainException('The database account has insufficient privileges for read-only COOP queries.');
  throw new DomainException('Database connection could not be prepared for bounded read-only queries.');
 }
 return $pdo;
}

function coop_preview(array $connection,string $query,string $month,?array $detail=null): array {
 $pdo=coop_pdo($connection);
 try{
  $metadata=$pdo->prepare('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = ?');
  $metadata->execute([$connection['database']]);
  $tables=array_map('strtolower',$metadata->fetchAll(PDO::FETCH_COLUMN));
  $parsed=qs_parse_scoped($query,$tables,$connection['database']);
  if(!$parsed['tables'])throw new DomainException('Select consumption data from an accessible source table or view.');
  $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
  $pdo->exec('START TRANSACTION READ ONLY');
  $pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY,false);
  $statement=$pdo->prepare('SELECT * FROM ('.$parsed['prepared'].') AS coop_preview LIMIT '.(COOP_PREVIEW_LIMIT+1));
  $params=array_fill(0,count($parsed['params']),$month);
  $statement->execute($params);
  $columns=[];$columnMeta=[];
  for($i=0;$i<$statement->columnCount();$i++){
   $meta=$statement->getColumnMeta($i);
   $columns[]=$meta['name'];
   $columnMeta[strtolower($meta['name'])]=$meta;
  }
  $kwh=array_search('kwh',array_map('strtolower',$columns),true);
  if($kwh===false||count($columns)>20||count(array_unique(array_map('strtolower',$columns)))!==count($columns))
   throw new DomainException('Return at most 20 uniquely named columns, including a numeric column aliased as KWH.');
  if($detail!==null||isset($columnMeta['areaid'],$columnMeta['areaname'],$columnMeta['accountid'])){
   $statement->closeCursor();
   $result=coop_account_preview($pdo,$parsed['prepared'],$params,$columnMeta,$month,$detail);
   $pdo->exec('ROLLBACK');
   return $result;
  }
  $rows=[];$total='0';$scale=0;$bytes=0;
  while(($row=$statement->fetch(PDO::FETCH_NUM))!==false){
   if(count($rows)===COOP_PREVIEW_LIMIT)throw new DomainException('Preview exceeds 500 rows. Narrow the monthly query or aggregate at the source.');
   $amount=$row[$kwh];
   if(!is_string($amount)||!preg_match('/^[+-]?\d{1,65}(?:\.(\d{1,60}))?$/D',$amount,$parts))
    throw new DomainException('KWH must be a non-null decimal. For floating-point sources, cast KWH to DECIMAL at the precision you intend.');
   foreach($row as $value){
    $length=strlen($value??'');$bytes+=$length;
    if($length>4096||$bytes>400000)throw new DomainException('Preview exceeds the data size limit. Select fewer columns or narrower values.');
   }
   $scale=max($scale,strlen($parts[1]??''));
   $total=bcadd($total,$amount,$scale);$rows[]=$row;
  }
  $statement->closeCursor();
  $pdo->exec('ROLLBACK');
  return ['month'=>$month,'monthLabel'=>base_report_month_label($month),'columns'=>$columns,'rows'=>$rows,'count'=>count($rows),'total'=>$total,'retrievedAt'=>gmdate('c')];
 }catch(PDOException $e){
  throw new DomainException(in_array((int)($e->errorInfo[1]??0),[1317,1969,3024],true)
   ?'The COOP query timed out.' : 'The COOP database rejected the query. Check the source table, column names, and permissions.');
 }
}

function coop_require_test(?array $test,array $state,string $fingerprint): void {
 if(!$test||$test[0]!==$state['revision']||!hash_equals($test[1],$fingerprint)||$test[2]<time())
  throw new DomainException('Test these exact settings again before saving.');
}

function coop_save(string $path,string $key,array $state,mixed $revision,string $message): array {
 $lock=fopen($path.'.lock','c');
 if(!$lock)throw new DomainException('Could not open the COOP settings lock.');
 try{
  if(!flock($lock,LOCK_EX))throw new DomainException('Could not lock COOP settings.');
  $current=coop_read_settings($path,$key);
  if($current['revision']!==$revision||$state['revision']!==$revision)
   throw new DomainException('COOP settings changed in another window. Reopen Settings and try again.',409);
  $state['revision']++;
  coop_write_settings($path,$key,$state);
  return ['message'=>$message,'revision'=>$state['revision']];
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
