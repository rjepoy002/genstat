<?php
declare(strict_types=1);

// Use only as the router for PHP's directly bound localhost development server.
if(PHP_SAPI!=='cli-server'||getenv('GENSTAT_COOP_DEV_MODE')!=='1'||
   !in_array($_SERVER['SERVER_NAME']??null,['127.0.0.1','::1'],true)||
   !in_array($_SERVER['REMOTE_ADDR']??null,['127.0.0.1','::1'],true)){
 http_response_code(403);exit('Local development listener required.');
}
require_once __DIR__.'/../lib/coop-report.php';
if(coop_forwarding_headers($_SERVER)||!coop_local_authority($_SERVER)){
 http_response_code(403);exit('Direct localhost access required.');
}
$_SERVER['GENSTAT_COOP_DEV_DIRECT']='cli-router';
$target=parse_url($_SERVER['REQUEST_URI']??'',PHP_URL_PATH);
$route=is_string($target)?rawurldecode($target):'';
$root=dirname(__DIR__);
$pages=['/'=>'index.php','/index.php'=>'index.php','/base-report.php'=>'base-report.php',
 '/query-settings-api.php'=>'query-settings-api.php','/coop-consumption-api.php'=>'coop-consumption-api.php'];
if(isset($pages[$route])){
 require $root.DIRECTORY_SEPARATOR.$pages[$route];
 return true;
}
if(preg_match('~^/assets/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_.-]+\.(?:css|js|png|jpg|jpeg|webp|svg|woff2|ico)$~D',$route)){
 $assetRoot=realpath($root.'/assets');
 $file=realpath($root.$route);
 if($assetRoot&&$file&&is_file($file)&&str_starts_with($file,$assetRoot.DIRECTORY_SEPARATOR))return false;
}
http_response_code(404);
echo 'Not found.';
return true;
