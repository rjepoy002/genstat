<?php
declare(strict_types=1);

function formula_authority_state(): array {
 $path=ROOT.'/storage/formula-auth.json';
 if(!is_file($path))return ['state'=>'missing','authority'=>null,'message'=>'A local administrator must complete formula editing setup.'];
 try{
  $raw=@file_get_contents($path);if($raw===false)throw new RuntimeException('Cannot read configuration.');
  $a=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
  if(!is_array($a)||!is_string($a['passwordHash']??null)||!is_string($a['identity']??null)||trim($a['identity'])===''||(password_get_info($a['passwordHash'])['algo']??null)===null)throw new RuntimeException('Invalid administrator configuration.');
  return ['state'=>'ready','authority'=>$a,'message'=>'Administrator setup is complete.'];
 }catch(Throwable $e){return ['state'=>'invalid','authority'=>null,'message'=>'Administrator configuration is unreadable or invalid. A local administrator must repair it using the setup command.'];}
}
function formula_authority(): ?array {return formula_authority_state()['authority'];}
function formula_auth_status(): array {
 $setup=formula_authority_state();$a=$setup['authority'];$grant=$_SESSION['formulaGrant']??null;
 $unlocked=$a!==null&&is_array($grant)&&($grant['expiresAt']??0)>time()&&hash_equals(hash('sha256',$a['passwordHash']),$grant['credentialVersion']??'');
 if(!$unlocked)unset($_SESSION['formulaGrant']);
 return ['configured'=>$a!==null,'setupState'=>$setup['state'],'setupMessage'=>$setup['message'],'unlocked'=>$unlocked,'expiresAt'=>$unlocked?$grant['expiresAt']:null,'identity'=>$unlocked?$a['identity']:null];
}
function require_formula_authorization(): string {
 $s=formula_auth_status();if(!$s['unlocked'])throw new FormulaPermissionException('Protected formula changes require an active authorized formula-editing session.');return $s['identity'];
}
final class FormulaPermissionException extends RuntimeException {}
function unlock_formulas(string $password): array {
 $authority=formula_authority();if(!$authority)throw new FormulaPermissionException('Formula editing is not configured. A local administrator must run php tools/formula-admin.php setup.');
 // A server-side, cross-session attempt counter prevents session resets from
 // bypassing the password throttle. No secret is logged or sent back.
 $path=ROOT.'/storage/formula-auth-attempts.json';$lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Cannot lock authorization attempts.');
 try{
  $state=is_file($path)?json_decode(file_get_contents($path),true):[];
  if(($state['until']??0)>time())throw new FormulaPermissionException('Too many unlock attempts. Try again in 5 minutes.');
  if(strlen($password)>256||!password_verify($password,$authority['passwordHash'])){
   $count=(($state['startedAt']??0)>time()-300?($state['count']??0):0)+1;
   atomic_file_write($path,json_bytes(['count'=>$count,'startedAt'=>$state['startedAt']??time(),'until'=>$count>=5?time()+300:0]));
   throw new FormulaPermissionException('Formula editing password was not accepted.');
  }
  atomic_file_write($path,json_bytes(['count'=>0,'startedAt'=>time(),'until'=>0]));
  session_regenerate_id(true);$_SESSION['formulaGrant']=['expiresAt'=>time()+600,'credentialVersion'=>hash('sha256',$authority['passwordHash'])];
  return formula_auth_status();
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
