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
final class FormulaPermissionException extends RuntimeException {}
