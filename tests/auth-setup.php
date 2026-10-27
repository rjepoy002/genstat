<?php
declare(strict_types=1);
define('ROOT',sys_get_temp_dir().'/genstat-auth-'.bin2hex(random_bytes(8)));
require __DIR__.'/../lib/formula-auth.php';
mkdir(ROOT);mkdir(ROOT.'/storage');$_SESSION=[];
function check_auth(bool $ok,string $message): void{if(!$ok)throw new RuntimeException('FAIL: '.$message);echo 'PASS: '.$message.PHP_EOL;}
$path=ROOT.'/storage/formula-auth.json';
try{
 check_auth(formula_auth_status()['setupState']==='missing'&&!formula_auth_status()['unlocked'],'Missing setup is reported and remains locked');
 file_put_contents($path,'{invalid');check_auth(formula_auth_status()['setupState']==='invalid'&&!formula_auth_status()['configured'],'Malformed configuration produces actionable invalid status');
 file_put_contents($path,json_encode(['identity'=>'test','passwordHash'=>'not-a-password-hash']));check_auth(!formula_auth_status()['configured'],'Bogus password hashes cannot configure formula access');
 $hash=password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT);file_put_contents($path,json_encode(['identity'=>'test-editor','passwordHash'=>$hash]));
 check_auth(formula_auth_status()['configured']&&!formula_auth_status()['unlocked'],'Valid configuration becomes ready without granting a session');
 $_SESSION['formulaGrant']=['expiresAt'=>time()+600,'credentialVersion'=>hash('sha256',$hash)];check_auth(formula_auth_status()['unlocked'],'A matching server session is recognized');
 $_SESSION['formulaGrant']['expiresAt']=time()-1;check_auth(!formula_auth_status()['unlocked'],'Expired server authorization relocks access');
}finally{if(is_file($path))unlink($path);rmdir(ROOT.'/storage');rmdir(ROOT);}
