<?php
declare(strict_types=1);

require_once __DIR__.'/query-settings.php';

const SETTINGS_IDLE_SECONDS=900;
const SETTINGS_LOCKOUT_SECONDS=300;
const SETTINGS_MAX_ATTEMPTS=5;

final class SettingsSecurityError extends RuntimeException {}

function settings_https(): bool {
 return in_array(strtolower($_SERVER['HTTPS']??''),['on','1'],true);
}

function settings_require_transport(): void {
 $host=strtolower($_SERVER['HTTP_HOST']??'');
 $local=preg_match('/\A(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]{1,5})?\z/',$host)
  &&in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true);
 if(!settings_https()&&!$local)throw new SettingsSecurityError('Settings require HTTPS.',403);
}

function settings_session_start(): void {
 ini_set('session.use_strict_mode','1');
 session_set_cookie_params(['secure'=>settings_https(),'httponly'=>true,'samesite'=>'Strict']);
 session_start();
}

function settings_audit(string $event,?string $identifier=null): void {
 $ip=$_SERVER['REMOTE_ADDR']??'';
 $entry=['time'=>gmdate('c'),'event'=>$event,'ip'=>filter_var($ip,FILTER_VALIDATE_IP)?$ip:null];
 if($identifier!==null)$entry['configuration']=$identifier;
 error_log('GENSTAT_SETTINGS '.json_encode($entry,JSON_INVALID_UTF8_SUBSTITUTE));
}

function settings_revoke(string $event='authorization_revoked'): void {
 if(!isset($_SESSION['settingsAuthorization']))return;
 if($_SESSION['settingsAuthorization']['expires']<=time())$event='authorization_expired';
 unset($_SESSION['settingsAuthorization'],$_SESSION['queryTest'],$_SESSION['queryResult'],
  $_SESSION['coopPhase1ConnectionTest'],$_SESSION['coopPhase1QueryTest']);
 settings_audit($event);
}

function settings_state(mysqli $db,bool $lock=false): ?array {
 return $db->execute_query('SELECT PINHash,FailedAttempts,LockedUntil,PINVersion FROM genstat_settings_security WHERE ID=?'.
  ($lock?' FOR UPDATE':''),[1])->fetch_assoc();
}

function settings_require_authorized(?string $token,bool $touch=true): array {
 settings_require_transport();
 $grant=$_SESSION['settingsAuthorization']??null;
 if(!$grant||$token===null||!hash_equals($grant['token'],$token))
  throw new SettingsSecurityError('Settings have been locked for security.',403);
 $now=time();
 if($grant['expires']<=$now){
  settings_revoke('authorization_expired');
  throw new SettingsSecurityError('Settings have been locked for security.',403);
 }
 $db=qs_db('writer');
 try{$state=settings_state($db);}
 finally{$db->close();}
 if(!$state||(string)$state['PINVersion']!==$grant['version']){
  settings_revoke('authorization_expired');
  throw new SettingsSecurityError('Settings have been locked for security.',403);
 }
 if($touch)$_SESSION['settingsAuthorization']['expires']=$now+SETTINGS_IDLE_SECONDS;
 return $_SESSION['settingsAuthorization'];
}

function settings_pin(mixed $value): bool {
 return is_string($value)&&preg_match('/\A[0-9]{6}\z/',$value)===1;
}

function settings_validate_new_pin(mixed $pin): void {
 if(!settings_pin($pin))throw new SettingsSecurityError('Enter exactly six digits.',400);
 if(preg_match('/\A([0-9])\1{5}\z/',$pin)||in_array($pin,['123456','654321','012345','543210','121212','112233','123123'],true))
  throw new SettingsSecurityError('Choose a less common security PIN.',400);
}

function settings_attempt(string $pin,?string $newPin=null,?string $version=null): array {
 $db=qs_db('writer');
 $db->begin_transaction();
 try{
  $state=settings_state($db,true);
  if(!$state)throw new SettingsSecurityError('Settings are locked. Ask a server administrator to set up the security PIN.',503);
  if($version!==null&&(string)$state['PINVersion']!==$version)
   throw new SettingsSecurityError('Settings have been locked for security.',403);
  $now=time();
  $until=(int)$state['LockedUntil'];
  if($until>$now){$db->commit();return ['lockedFor'=>$until-$now,'remaining'=>0];}
  $attempts=$until>0?0:(int)$state['FailedAttempts'];
  if(!password_verify($pin,$state['PINHash'])){
   $remaining=max(0,SETTINGS_MAX_ATTEMPTS-(++$attempts));
   $until=$remaining===0?$now+SETTINGS_LOCKOUT_SECONDS:0;
   $db->execute_query('UPDATE genstat_settings_security SET FailedAttempts=?,LockedUntil=? WHERE ID=?',[$attempts,$until,1]);
   $db->commit();
   settings_audit('pin_verification_failed');
   if($until)settings_audit('temporary_lockout');
   return ['lockedFor'=>$until?SETTINGS_LOCKOUT_SECONDS:0,'remaining'=>$remaining];
  }
  if($newPin!==null){
   if($pin===$newPin)throw new SettingsSecurityError('The new PIN must differ from the current PIN.',400);
   $db->execute_query('UPDATE genstat_settings_security SET PINHash=?,FailedAttempts=0,LockedUntil=0,PINVersion=PINVersion+1 WHERE ID=?',
    [password_hash($newPin,PASSWORD_DEFAULT),1]);
  }else $db->execute_query('UPDATE genstat_settings_security SET FailedAttempts=0,LockedUntil=0 WHERE ID=?',[1]);
  $db->commit();
  settings_audit($newPin===null?'pin_verification_succeeded':'pin_changed');
  return ['version'=>(string)$state['PINVersion']];
 }catch(Throwable $error){$db->rollback();throw $error;}
 finally{$db->close();}
}
