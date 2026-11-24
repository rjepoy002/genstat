<?php
declare(strict_types=1);
ini_set('display_errors','0');
require_once __DIR__.'/lib/coop-phase1.php';
require_once __DIR__.'/lib/coop-registered-accounts.php';
require_once __DIR__.'/lib/settings-security.php';

settings_session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try{
 if($_SERVER['REQUEST_METHOD']!=='POST')throw new DomainException('Use a protected POST request.');
 $raw=file_get_contents('php://input',false,null,0,100001);
 if(strlen($raw)>100000)throw new DomainException('Request too large.');
 $body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
 $month=$body['month']??null;
 if(empty($_SESSION['baseCsrf'])||!base_report_valid_month($month)||!is_string($body['csrf']??null)||
   !hash_equals(base_report_csrf($month),$body['csrf']))throw new DomainException('Session expired. Reload the report.',403);
 settings_require_authorized($_SERVER['HTTP_X_SETTINGS_AUTHORIZATION']??null);
 qs_select_month(qs_available_months(),$month);
 $action=$body['action']??null;
 $registryAction=in_array($action,['accounts-list','accounts-add','accounts-transfer','accounts-remove'],true);
 try{
  [$path,$key]=coop_secret_location();
  $state=coop_read_settings($path,$key);
 }catch(DomainException|JsonException $error){
  if(!$registryAction)throw $error;
  $state=['connection'=>null];
 }

 if($registryAction){
  if(($_SESSION['baseReportMonth']??null)!==$month)
   throw new DomainException('The reporting month changed. Reopen COOP Settings.',409);
  $result=coop_registered_request($action,$body,$month,$state['connection']);
 }elseif($action==='metadata'){
  $connection=$state['connection']??coop_defaults();
  $profiles=array_map('coop_connection_public',coop_connection_profiles($state));
  $public=coop_connection_public($connection);
  $result=['connection'=>$public,'hasPassword'=>$public['hasPassword'],'profiles'=>(object)$profiles,'saved'=>isset($state['connection']),
   'query'=>$state['query'],'queryReady'=>isset($state['connection'])&&$state['queryConnection']===coop_connection_id($state['connection'],$key),
   'revision'=>$state['revision'],'monthLabel'=>base_report_month_label($month)];
 }elseif($action==='test-connection'||$action==='save-connection'){
  if(!is_array($body['connection']??null))throw new DomainException('Enter the COOP connection settings.');
  $profiles=coop_connection_profiles($state);
  $type=$body['connection']['type']??null;
  if($action==='test-connection')unset($_SESSION['coopPhase1ConnectionTest']);
  $candidate=coop_connection_input($body['connection'],is_string($type)?($profiles[$type]??null):null);
  $fingerprint=coop_connection_id($candidate,$key);
  if($action==='test-connection'){
   $result=coop_test_connection($candidate);
   $_SESSION['coopPhase1ConnectionTest']=[$state['revision'],$fingerprint,time()+300];
  }else{
   coop_require_test($_SESSION['coopPhase1ConnectionTest']??null,$state,$fingerprint);
   if(isset($state['connection'])&&($body['confirmed']??false)!==true)throw new DomainException('Confirm before replacing the saved connection.');
   $verified=coop_test_connection($candidate);
   if($type==='https')$candidate['lastSuccessfulTest']=gmdate('c');
   $previousType=$state['connection']['type']??null;
   if($previousType!==null&&$previousType!=='https')
    $state['directQueries'][$previousType]=['query'=>$state['query'],'queryConnection'=>$state['queryConnection']];
   if($previousType!==$type&&$type!=='https'){
    $savedQuery=$state['directQueries'][$type]??['query'=>$state['query'],'queryConnection'=>null];
    $state['query']=$savedQuery['query'];$state['queryConnection']=$savedQuery['queryConnection'];
   }
   $profiles[$type]=$candidate;
   $state['connectionProfiles']=$profiles;
   $state['connection']=$candidate;
   $result=coop_save($path,$key,$state,$body['revision']??null,'Connection saved.')+$verified;
   if($previousType==='https'||$type==='https')unset($_SESSION['coopSummary']);
   unset($_SESSION['coopPhase1ConnectionTest'],$_SESSION['coopPhase1QueryTest']);
  }
 }elseif(in_array($action,['test-query','save-query','preview','account-details'],true)){
  if(!isset($state['connection']))throw new DomainException('Test and save the COOP connection first.');
  if($state['connection']['type']==='https')
   throw new DomainException('Consumption data is retrieved through the configured HTTPS API.');
  $id=coop_connection_id($state['connection'],$key);
  if(in_array($action,['preview','account-details'],true)){
   $source=$state['query'];
   if($action==='account-details'&&isset($body['query'])){
    $source=$body['query'];
    if(!is_string($source)||strlen($source)>40000)throw new DomainException('Enter a valid tested query.');
    coop_require_test($_SESSION['coopPhase1QueryTest']??null,$state,hash('sha256',$id."\0".$month."\0".$source));
   }elseif($state['queryConnection']!==$id||$source===''){
    throw new DomainException('Test and save a query for this connection before previewing.');
   }
   $result=coop_preview($state['connection'],$source,$month,$action==='account-details'?coop_account_detail_input($body):null);
  }else{
   $query=$body['query']??null;
   if(!is_string($query)||!trim($query)||strlen($query)>40000)throw new DomainException('Enter one SQL query up to 40,000 bytes.');
   $fingerprint=hash('sha256',$id."\0".$month."\0".$query);
   if($action==='test-query'){
    unset($_SESSION['coopPhase1QueryTest']);
    $result=coop_preview($state['connection'],$query,$month);
    $_SESSION['coopPhase1QueryTest']=[$state['revision'],$fingerprint,time()+300];
   }else{
    coop_require_test($_SESSION['coopPhase1QueryTest']??null,$state,$fingerprint);
    if($state['query']!==''&&($body['confirmed']??false)!==true)throw new DomainException('Confirm before replacing the saved query.');
    $verified=coop_preview($state['connection'],$query,$month);
    $state['query']=$query;$state['queryConnection']=$id;
    $result=coop_save($path,$key,$state,$body['revision']??null,'Query saved.')+['preview'=>$verified];
    unset($_SESSION['coopPhase1QueryTest']);
   }
  }
 }else throw new DomainException('Unknown COOP settings action.');
 if(in_array($action,['save-connection','save-query','accounts-add','accounts-transfer','accounts-remove'],true))
  settings_audit('configuration_changed','coop:'.$action);
 echo json_encode($result,JSON_INVALID_UTF8_SUBSTITUTE|JSON_THROW_ON_ERROR);
}catch(SettingsSecurityError $e){http_response_code($e->getCode());echo json_encode(['error'=>$e->getMessage()]);}
catch(DomainException|QuerySettingsError $e){
 $status=$e->getCode();
 http_response_code(in_array($status,[403,409,503],true)?$status:400);
 echo json_encode(['error'=>$e->getMessage()],JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable){
 http_response_code(500);
 echo json_encode(['error'=>'COOP settings could not complete the request. Check the server configuration and try again.']);
}
