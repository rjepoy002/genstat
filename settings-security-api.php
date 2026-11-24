<?php
declare(strict_types=1);
ini_set('display_errors','0');
ini_set('zend.exception_ignore_args','1');
require_once __DIR__.'/lib/settings-security.php';
settings_session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

try{
 settings_require_transport();
 if($_SERVER['REQUEST_METHOD']!=='POST')throw new SettingsSecurityError('Use a protected POST request.',405);
 $raw=file_get_contents('php://input',false,null,0,4097);
 if($raw===false||strlen($raw)>4096)throw new SettingsSecurityError('Invalid request.',400);
 $body=json_decode($raw,true,8,JSON_THROW_ON_ERROR);
 $month=$body['month']??null;$csrf=$body['csrf']??null;
 if(empty($_SESSION['baseCsrf'])||!base_report_valid_month($month)||!is_string($csrf)||
   !hash_equals(base_report_csrf($month),$csrf))throw new SettingsSecurityError('Session expired. Reload the report.',403);
 $action=$body['action']??null;$token=$body['authorization']??null;
 if(!is_string($token))$token=null;
 if($action==='state'){
  $db=qs_db('writer');
  try{
   $db->execute_query('UPDATE genstat_settings_security SET FailedAttempts=0,LockedUntil=0 WHERE ID=? AND LockedUntil>0 AND LockedUntil<=?',[1,time()]);
   $state=settings_state($db);
  }finally{$db->close();}
  $out=['configured'=>$state!==null,'lockedFor'=>max(0,(int)($state['LockedUntil']??0)-time()),
   'remaining'=>max(0,SETTINGS_MAX_ATTEMPTS-(int)($state['FailedAttempts']??0))];
 }elseif($action==='revoke'){
  $grant=$_SESSION['settingsAuthorization']??null;
  if($grant&&$token!==null&&hash_equals($grant['token'],$token))settings_revoke();
  $out=['ok'=>true];
 }elseif(in_array($action,['status','activity','appearance'],true)){
  $grant=settings_require_authorized($token,$action!=='status');
  if($action==='appearance')settings_audit('configuration_changed','appearance');
  $out=['expires'=>$grant['expires']];
 }elseif($action==='verify'||$action==='change'){
  $pin=$body['pin']??null;
  if(!settings_pin($pin))throw new SettingsSecurityError('Enter exactly six digits.',400);
  $newPin=null;$version=null;
  if($action==='change'){
   $grant=settings_require_authorized($token);
   $newPin=$body['newPin']??null;
   settings_validate_new_pin($newPin);
   if($newPin!==($body['confirmation']??null)||($body['confirmed']??false)!==true)
    throw new SettingsSecurityError('Confirm the new PIN before saving.',400);
   $version=$grant['version'];
  }
  $result=settings_attempt($pin,$newPin,$version);
  if(!isset($result['version'])){
   http_response_code($result['lockedFor']?429:401);
   $out=['error'=>$result['lockedFor']?'Too many attempts. Settings verification is temporarily locked.':
    'Incorrect security PIN. Please try again.']+$result;
  }elseif($action==='change'){
   settings_revoke();
   $out=['message'=>'Security PIN changed successfully.'];
  }else{
   settings_revoke();
   // Apply & Save receipts remain bound to the original server-side session lineage.
   $_SESSION['queryReceiptOwnerSession']??=session_id();
   session_regenerate_id(true);
   $_SESSION['settingsAuthorization']=[
    'token'=>bin2hex(random_bytes(32)),'version'=>$result['version'],'expires'=>time()+SETTINGS_IDLE_SECONDS,
   ];
   $out=array_intersect_key($_SESSION['settingsAuthorization'],['token'=>true,'expires'=>true]);
  }
 }else throw new SettingsSecurityError('Invalid request.',400);
 echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(SettingsSecurityError $error){
 http_response_code($error->getCode());
 echo json_encode(['error'=>$error->getMessage()]);
}catch(JsonException){
 http_response_code(400);echo json_encode(['error'=>'Invalid request.']);
}catch(Throwable){
 http_response_code(503);
 echo json_encode(['error'=>'Settings are locked. Ask a server administrator to check the security PIN setup.']);
}
