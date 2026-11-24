<?php
declare(strict_types=1);

const COOP_HTTPS_DEFAULT_URL = 'https://paleco.net/genstat-api/coop.php';
const COOP_HTTPS_BATCH_SIZE = 100;
const COOP_HTTPS_RESPONSE_LIMIT = 524288;
const COOP_HTTPS_LOOKUP_NOTES = [
 'account_not_found'=>'Account not found',
 'no_active_account'=>'No active account',
 'multiple_active_account_matches'=>'Multiple active account matches',
 'retrieval_failure'=>'Unable to retrieve data',
];
const COOP_HTTPS_BILLING_NOTES = [
 'no_billing_record'=>'No billing record',
 'incomplete_billing_data'=>'Incomplete billing data',
 'retrieval_failure'=>'Unable to retrieve data',
];

function coop_https_url(mixed $url): string {
 if(!is_string($url)||strlen($url)>2048||preg_match('/[\x00-\x20\x7f\\\\]/',$url))
  throw new DomainException('Enter a valid HTTPS API URL.');
 $parts=parse_url($url);
 if(!$parts||($parts['scheme']??null)!=='https'||!isset($parts['host'])||
   filter_var($parts['host'],FILTER_VALIDATE_IP)||!filter_var($parts['host'],FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)||
   !str_contains($parts['host'],'.')||strtolower($parts['host'])==='localhost'||
   isset($parts['user'])||isset($parts['pass'])||isset($parts['fragment'])||isset($parts['query'])||
   ($parts['port']??443)!==443)
  throw new DomainException('Enter a valid HTTPS API URL.');
 $additional=json_decode(getenv('GENSTAT_COOP_HTTPS_ENDPOINTS')?:'[]',true);
 if(!is_array($additional)||!in_array($url,[COOP_HTTPS_DEFAULT_URL,...$additional],true))
  throw new DomainException('This HTTPS API destination is not approved.');
 return $url;
}

function coop_https_addresses(string $host): array {
 $records=@dns_get_record($host,DNS_A|DNS_AAAA)?:[];
 $addresses=[];
 foreach($records as $record){
  $address=$record['ip']??$record['ipv6']??null;
  if($address===null)continue;
  if(!coop_public_address($address))throw new DomainException('The HTTPS API hostname resolved to an unapproved address.');
  $addresses[]=$address;
 }
 if(!$addresses)throw new DomainException('Unable to resolve the HTTPS API hostname.');
 return array_values(array_unique($addresses));
}

function coop_https_request(array $connection,?array $body=null): array {
 $url=coop_https_url($connection['apiUrl']);
 if(!extension_loaded('curl'))throw new DomainException('Enable PHP cURL to use the HTTPS API.');
 $host=parse_url($url,PHP_URL_HOST);
 $addresses=coop_https_addresses($host);
 $key=$connection['apiKey'];
 if(!is_string($key)||!preg_match('/\A[!-~]{1,1024}\z/',$key))
  throw new DomainException('Enter a valid API key.');
 return coop_https_exchange($url,$host,443,$addresses,$key,$body);
}

/** HTTPS transport. The entry point validates and resolves an approved endpoint before calling this. */
function coop_https_exchange(string $url,string $host,int $port,array $addresses,string $key,?array $body): array {
 $pins=implode(',',array_map('coop_mysql_host',$addresses));
 $response='';$tooLarge=false;
 $handle=curl_init($url);
 $options=[
  CURLOPT_HTTPHEADER=>['X-API-Key: '.$key,'Accept: application/json','Content-Type: application/json'],
  CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROXY=>'',
  CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
  CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>8,CURLOPT_RESOLVE=>[$host.':'.$port.':'.$pins],
  CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk)use(&$response,&$tooLarge): int {
   if(strlen($response)+strlen($chunk)>COOP_HTTPS_RESPONSE_LIMIT){$tooLarge=true;return 0;}
   $response.=$chunk;
   return strlen($chunk);
  },
 ];
 $ca=ini_get('curl.cainfo');
 if($ca&&is_readable($ca))$options[CURLOPT_CAINFO]=$ca;
 if($body!==null){
  $options[CURLOPT_POST]=true;
  $options[CURLOPT_POSTFIELDS]=json_encode($body,JSON_THROW_ON_ERROR);
 }
 try{
  if(!@curl_setopt_array($handle,$options))throw new DomainException('Unable to configure the HTTPS API connection.');
  $ok=curl_exec($handle);
  $errno=curl_errno($handle);
  $status=(int)curl_getinfo($handle,CURLINFO_RESPONSE_CODE);
 }finally{curl_close($handle);}
 if($ok===false)throw new DomainException(match(true){
  $tooLarge=>'Invalid API response. The response is too large.',
  $errno===28=>'API request timed out.',
  in_array($errno,[51,60,77,83,90,91],true)=>'HTTPS certificate verification failed.',
  default=>'Unable to connect to the HTTPS API.',
 });
 return coop_https_response($status,$response);
}

function coop_https_response(int $status,string $response): array {
 if($status!==200)throw new DomainException(match($status){
  401,403=>'Invalid API key or access denied.',
  404=>'API endpoint not found.',
  503=>'Hosted database connection failed.',
  default=>$status>=300&&$status<400?'The HTTPS API redirected the request.':'Invalid API response.',
 });
 try{$payload=json_decode($response,false,32,JSON_THROW_ON_ERROR);}
 catch(JsonException){throw new DomainException('Invalid API response.');}
 if(!$payload instanceof stdClass||!is_bool($payload->success??null))throw new DomainException('Invalid API response.');
 return (array)$payload;
}

function coop_https_health(array $connection,?callable $request=null): array {
 $data=($request??'coop_https_request')($connection);
 if(($data['success']??null)!==true||($data['database_connected']??null)!==true)
  throw new DomainException('Hosted database connection failed.');
 $version=$data['api_version']??null;
 if(!is_string($version)||!preg_match('/\A1\.[1-9][0-9]{0,3}\z/',$version))
  throw new DomainException('Unsupported API version.');
 return ['message'=>'Connection successful.','apiVersion'=>$version,'databaseConnected'=>true];
}

function coop_https_item(array $item,bool $monthly): array {
 $status=$item['status']??null;
 $number=$item['AcctNo']??null;$name=$item['AccountName']??null;
 $amount=$item['KWH']??null;
 $known=is_string($number)&&trim($number)!==''&&strlen($number)<=128&&!preg_match('/[\x00-\x1f\x7f]/',$number);
 if(!is_string($status)||!array_key_exists('AcctNo',$item)||!array_key_exists('AccountName',$item)||
   ($name!==null&&(!is_string($name)||mb_strlen($name,'UTF-8')>255))||
   ($monthly&&!array_key_exists('KWH',$item)))
  throw new DomainException('Invalid API response.');
 if(isset(COOP_HTTPS_LOOKUP_NOTES[$status])&&($status!=='retrieval_failure'||!$monthly||$number===null)){
  if($number!==null||$name!==null||$amount!==null)throw new DomainException('Invalid API response.');
  return ['status'=>COOP_HTTPS_LOOKUP_NOTES[$status],'kwh'=>null,'billingStatus'=>''];
 }
 if(!$known||($monthly
   ?($status!=='ok'&&!isset(COOP_HTTPS_BILLING_NOTES[$status]))
   :$status!=='found'))
  throw new DomainException('Invalid API response.');
 if($status==='ok'){
  if(!is_string($amount)||!preg_match('/\A[0-9]+(?:\.[0-9]{1,60})?\z/',$amount))
   throw new DomainException('Invalid API response.');
  if(preg_match('/\A0+(?:\.0+)?\z/',$amount))$amount='0.00';
 }elseif($amount!==null)throw new DomainException('Invalid API response.');
 return ['AcctNo'=>$number,'Name'=>$name,'kwh'=>$amount,'billingStatus'=>COOP_HTTPS_BILLING_NOTES[$status]??''];
}

function coop_https_lookup(array $connection,string $code,?callable $request=null): array {
 $payload=($request??'coop_https_request')($connection,['action'=>'account_lookup','AcctCode'=>$code]);
 $item=$payload['data']??null;
 if($item instanceof stdClass)$item=(array)$item;
 if(($payload['success']??null)!==true||!is_array($item)||($item['AcctCode']??null)!==$code)
  throw new DomainException('Invalid API response.');
 return coop_https_item($item,false);
}

function coop_https_month(array $connection,array $codes,string $month,?callable $request=null): array {
 $matches=[];$failed=false;
 foreach(array_chunk($codes,COOP_HTTPS_BATCH_SIZE) as $batch){
  try{
   $payload=($request??'coop_https_request')($connection,[
    'action'=>'monthly_consumption','ReportMonth'=>$month,'accounts'=>$batch,
   ]);
   $items=$payload['data']??null;
   if(($payload['success']??null)!==true||($payload['ReportMonth']??null)!==$month||
     !is_array($items)||!array_is_list($items)||count($items)!==count($batch))
    throw new DomainException('Invalid API response.');
   $accepted=[];$expected=array_fill_keys($batch,true);
   foreach($items as $item){
    if($item instanceof stdClass)$item=(array)$item;
    if(!is_array($item))throw new DomainException('Invalid API response.');
    $code=$item['AcctCode']??null;
    if(!is_string($code)||!isset($expected[$code])||isset($accepted[$code]))
     throw new DomainException('Invalid API response.');
    $accepted[$code]=coop_https_item($item,true);
   }
   $matches+=$accepted;
   $failed=$failed||in_array('Unable to retrieve data',array_column($accepted,'status'),true)||
    in_array('Unable to retrieve data',array_column($accepted,'billingStatus'),true);
  }catch(DomainException){
   $failed=true;
   $matches+=array_fill_keys($batch,['status'=>'Unable to retrieve data']);
  }
 }
 return [$matches,$failed];
}
