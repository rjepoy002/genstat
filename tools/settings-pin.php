<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
ini_set('display_errors','0');
ini_set('zend.exception_ignore_args','1');
require_once __DIR__.'/../lib/settings-security.php';

// PIN input is accepted only from a hidden prompt wrapper over stdin.
if($argc!==2||!in_array($argv[1],['setup','reset'],true)||stream_isatty(STDIN)){
 fwrite(STDERR,"Usage: pipe two matching PIN lines from a hidden prompt into php tools/settings-pin.php setup|reset.\n");
 exit(2);
}
$mode=$argv[1];
try{
 $pin=rtrim((string)fgets(STDIN,16),"\r\n");
 $confirmation=rtrim((string)fgets(STDIN,16),"\r\n");
 settings_validate_new_pin($pin);
 if($pin!==$confirmation)throw new SettingsSecurityError('The PIN entries do not match.',400);
 unset($confirmation);
 $db=qs_db('writer');
 try{
  $db->begin_transaction();
  $state=settings_state($db,true);
  if($mode==='setup'&&$state)throw new SettingsSecurityError('A PIN already exists. Use reset for administrator recovery.',400);
  if($mode==='reset'&&!$state)throw new SettingsSecurityError('No PIN exists. Use setup first.',400);
  if($state&&password_verify($pin,$state['PINHash']))throw new SettingsSecurityError('Choose a different PIN.',400);
  $hash=password_hash($pin,PASSWORD_DEFAULT);
  unset($pin);
  if($state)$db->execute_query('UPDATE genstat_settings_security SET PINHash=?,FailedAttempts=0,LockedUntil=0,PINVersion=PINVersion+1 WHERE ID=?',[$hash,1]);
  else $db->execute_query('INSERT INTO genstat_settings_security (ID,PINHash) VALUES (?,?)',[1,$hash]);
  unset($hash);
  $db->commit();
 }catch(Throwable $error){$db->rollback();throw $error;}
 finally{$db->close();}
 settings_audit($mode==='setup'?'administrator_pin_initialized':'administrator_pin_reset');
 fwrite(STDOUT,$mode==='setup'?"Settings PIN configured.\n":"Settings PIN reset. Previous Settings authorizations are invalid.\n");
}catch(SettingsSecurityError $error){
 fwrite(STDERR,$error->getMessage()."\n");exit(1);
}catch(Throwable){
 fwrite(STDERR,"PIN setup could not complete. Check the security migration and database permissions.\n");exit(1);
}
