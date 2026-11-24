<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/coop-phase1.php';

function check(bool $condition, string $message): void {
 if(!$condition)throw new RuntimeException($message);
}
function rejects(callable $action,string $message): void {
 try{$action();}
 catch(DomainException $error){
  check(str_contains($error->getMessage(),$message),'Unexpected storage failure: '.$error->getMessage());
  return;
 }
 throw new RuntimeException('Expected storage refusal: '.$message);
}

if(PHP_OS_FAMILY!=='Windows'){echo "Skipped Windows automatic COOP storage\n";exit;}
putenv('GENSTAT_COOP_PHASE1_FILE');
putenv('GENSTAT_COOP_PHASE1_KEY');
if(($argv[1]??'')==='reload'){
 $_SERVER['DOCUMENT_ROOT']=$argv[2];
 [, $key]=coop_secret_location();
 echo hash('sha256',$key);
 exit;
}

$fixture=sys_get_temp_dir().'/genstat-coop-'.bin2hex(random_bytes(12));
mkdir($fixture.'/apache/bin',0777,true);
mkdir($fixture.'/php');
mkdir($fixture.'/htdocs');
touch($fixture.'/apache/bin/httpd.exe');
touch($fixture.'/php/php.exe');
$_SERVER['DOCUMENT_ROOT']=$fixture.'/htdocs';
$path=null;
try{
 [$path,$key]=coop_secret_location();
 $keyPath=dirname($path).'/key';
 check(strlen($key)===32 && !str_starts_with(realpath(dirname($path)),realpath($fixture)),'Expected private key outside the fixture');
 $state=['revision'=>1,'connection'=>coop_defaults(),'query'=>'','queryConnection'=>null];
 coop_write_settings($path,$key,$state);
 $cipher=file_get_contents($path);
 $reload=proc_open([PHP_BINARY,__FILE__,'reload',$_SERVER['DOCUMENT_ROOT']], [1=>['pipe','w']],$pipes);
 check(is_resource($reload),'Could not start restart probe');
 $fingerprint=stream_get_contents($pipes[1]);
 fclose($pipes[1]);
 check(proc_close($reload)===0 && $fingerprint===hash('sha256',$key),'Key changed across PHP processes');
 putenv('GENSTAT_COOP_PHASE1_FILE='.$path);
 rejects('coop_secret_location','Set both');
 putenv('GENSTAT_COOP_PHASE1_KEY='.base64_encode($key));
 [$explicitPath,$explicitKey]=coop_secret_location();
 check(coop_read_settings($explicitPath,$explicitKey)===$state,'Explicit encrypted settings were not reopened');
 rejects(fn()=>coop_read_settings($path,random_bytes(32)),'could not be decrypted');
 putenv('GENSTAT_COOP_PHASE1_FILE');
 putenv('GENSTAT_COOP_PHASE1_KEY');
 rename($keyPath,$keyPath.'.original');
 try{
  rejects('coop_secret_location','key is missing');
  check(!is_file($keyPath),'A replacement key was created for existing ciphertext');
  file_put_contents($keyPath,'invalid');
  rejects('coop_secret_location','is invalid');
 }finally{
  if(is_file($keyPath))unlink($keyPath);
  rename($keyPath.'.original',$keyPath);
 }
 exec('icacls '.escapeshellarg(dirname($path)).' /grant *S-1-5-32-545:(RX) 2>NUL',$output,$exit);
 check($exit===0,'Could not set the unsafe fixture ACL');
 try{rejects('coop_secret_location','rejected unsafe COOP storage');}
 finally{exec('icacls '.escapeshellarg(dirname($path)).' /remove:g *S-1-5-32-545 2>NUL',$output,$exit);}
 check($exit===0 && file_get_contents($path)===$cipher && coop_read_settings($path,$key)===$state,'Storage failure changed saved settings');
 echo "Automatic initialization, persistent key, legacy settings and safe refusals passed\n";
}finally{
 if($path){
  foreach([$path,$path.'.lock',dirname($path).'/key'] as $file)if(is_file($file))unlink($file);
  rmdir(dirname($path));
 }
 unlink($fixture.'/apache/bin/httpd.exe');
 unlink($fixture.'/php/php.exe');
 foreach(['/apache/bin','/apache','/php','/htdocs',''] as $directory)rmdir($fixture.$directory);
}
