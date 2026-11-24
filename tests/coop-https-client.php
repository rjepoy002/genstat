<?php
declare(strict_types=1);

require __DIR__.'/../lib/coop-phase1.php';

function check(bool $condition,string $message): void {
 if(!$condition)throw new RuntimeException($message);
}

function rejects(callable $operation,string $message): void {
 try{$operation();}
 catch(DomainException $error){check(str_contains($error->getMessage(),$message),$error->getMessage());return;}
 throw new RuntimeException('Expected rejection: '.$message);
}

putenv('GENSTAT_COOP_HTTPS_ENDPOINTS=[]');
$connection=['type'=>'https','apiUrl'=>COOP_HTTPS_DEFAULT_URL,'apiKey'=>'fixture-key'];
check(coop_https_url(COOP_HTTPS_DEFAULT_URL)===COOP_HTTPS_DEFAULT_URL,'Default endpoint rejected.');
foreach(['http://paleco.net/genstat-api/coop.php','https://localhost/','https://127.0.0.1/',
 'https://user@paleco.net/genstat-api/coop.php','https://paleco.net:8443/genstat-api/coop.php',
 COOP_HTTPS_DEFAULT_URL.'#fragment',COOP_HTTPS_DEFAULT_URL.'?key=fixture'] as $url)
 rejects(fn()=>coop_https_url($url),'valid HTTPS API URL');
rejects(fn()=>coop_https_url('https://unapproved.example/coop.php'),'not approved');
putenv('GENSTAT_COOP_HTTPS_ENDPOINTS=["https://approved.example/coop.php"]');
check(coop_https_url('https://approved.example/coop.php')==='https://approved.example/coop.php','Approved override rejected.');
foreach(['127.0.0.1','::1','10.0.0.1','169.254.169.254','100.64.0.1','fe80::1'] as $ip)
 check(!coop_public_address($ip),'Non-public IP accepted.');
rejects(fn()=>coop_https_response(302,'{}'),'redirected');
rejects(fn()=>coop_https_response(200,'{'),'Invalid API response');
check(coop_connection_input(['type'=>'https','apiUrl'=>COOP_HTTPS_DEFAULT_URL,'apiKey'=>''],$connection)===$connection,'Saved key was lost.');
rejects(fn()=>coop_connection_input(['type'=>'https','apiUrl'=>'https://approved.example/coop.php'],$connection),'API key required');
rejects(fn()=>coop_connection_input(['type'=>'https','apiUrl'=>COOP_HTTPS_DEFAULT_URL,'apiKey'=>"X\r\nHost: rejected"],$connection),'valid API key');
check(!array_key_exists('apiKey',coop_connection_public($connection)),'Public metadata exposed the API key.');

$calls=[];
$codes=array_map(fn($value)=>'ACCT-'.$value,range(1,201));
$request=function(array $connection,array $body)use(&$calls): array {
 $calls[]=$body;
 if(count($calls)===2)throw new DomainException('API request timed out.');
 return ['success'=>true,'ReportMonth'=>$body['ReportMonth'],'data'=>array_map(
  fn($code)=>['AcctCode'=>$code,'AcctNo'=>'000'.$code,'AccountName'=>null,'status'=>'ok','KWH'=>'0.000000'],
  array_reverse($body['accounts'])
 )];
};
[$items,$failed]=coop_https_month($connection,$codes,'202607',$request);
check(array_map(fn($call)=>count($call['accounts']),$calls)===[100,100,1],'Wrong batch lengths.');
check(count($items)===201&&$failed,'Partial failure lost account entries.');
check($items['ACCT-1']['kwh']==='0.00'&&$items['ACCT-201']['kwh']==='0.00','Successful batches lost zero values.');
check($items['ACCT-101']['status']==='Unable to retrieve data','Failed batch was not marked unavailable.');

$one=['AcctCode'=>'A','AcctNo'=>'0001','AccountName'=>'Test','status'=>'ok','KWH'=>'123.456789'];
$two=['AcctCode'=>'B','AcctNo'=>'0002','AccountName'=>null,'status'=>'no_billing_record','KWH'=>null];
foreach([
 ['ReportMonth'=>'202608','data'=>[$one,$two]],
 ['ReportMonth'=>'202607','data'=>[$one,$one]],
 ['ReportMonth'=>'202607','data'=>[$one,array_replace($two,['AcctCode'=>'OTHER'])]],
 ['ReportMonth'=>'202607','data'=>[$one,array_replace($two,['KWH'=>'0.00'])]],
 ['ReportMonth'=>'202607','data'=>[array_replace($one,['KWH'=>123.4]),$two]],
] as $payload){
 [$items,$failed]=coop_https_month($connection,['A','B'],'202607',fn()=>['success'=>true]+$payload);
 check($failed&&array_column($items,'status')===['Unable to retrieve data','Unable to retrieve data'],'Malformed batch was used.');
}
[$items,$failed]=coop_https_month($connection,['A','B'],'202607',fn()=>['success'=>true,'ReportMonth'=>'202607','data'=>[$one,$two]]);
check(!$failed&&$items['A']['kwh']==='123.456789'&&$items['B']['kwh']===null&&$items['B']['billingStatus']==='No billing record','Monthly precision or status was lost.');
[$items,$failed]=coop_https_month($connection,['A','B'],'202607',fn()=>[
 'success'=>true,'ReportMonth'=>'202607','data'=>[$one,array_replace($two,['status'=>'retrieval_failure'])],
]);
check($failed&&$items['A']['kwh']==='123.456789'&&$items['B']['billingStatus']==='Unable to retrieve data','A per-account failure displaced valid KWH.');
echo "Verified HTTPS URL policy, response validation, secret handling and 100-account batching.\n";
