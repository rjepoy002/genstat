<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit('CLI only.');}
require_once __DIR__.'/../lib/files.php';
const ROOT=__DIR__.'/..';
require_once __DIR__.'/../lib/formula-auth.php';
try{
 if(($argv[1]??'')==='status'){$state=formula_authority_state();echo 'Formula editing setup: '.$state['state'].PHP_EOL.$state['message'].PHP_EOL;if($state['authority'])echo 'Editor identity: '.$state['authority']['identity'].PHP_EOL;exit($state['state']==='invalid'?1:0);}
 if(($argv[1]??'')!=='setup')throw new InvalidArgumentException('Usage: php tools/formula-admin.php status | setup');
 echo "Editor identity: ";$name=trim(fgets(STDIN)?:'');
 echo "Password (12+ characters; input is visible in this CLI): ";$password=rtrim(fgets(STDIN)?:'',"\r\n");
 echo "Confirm password: ";$confirm=rtrim(fgets(STDIN)?:'',"\r\n");
 if($name===''||strlen($name)>80||strlen($password)<12||strlen($password)>256||!hash_equals($password,$confirm))throw new InvalidArgumentException('Invalid identity, password length or confirmation. No configuration changed.');
 $path=dirname(__DIR__).'/storage/formula-auth.json';
 if(is_file($path))atomic_file_write($path.'.backup-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(3)),file_get_contents($path));
 atomic_file_write($path,json_bytes(['identity'=>$name,'passwordHash'=>password_hash($password,PASSWORD_DEFAULT)]));
 echo "Authorization configured. Existing unlock sessions are revoked. No formula has been changed.\n";
}catch(Throwable $e){fwrite(STDERR,$e->getMessage().PHP_EOL);exit(1);}
