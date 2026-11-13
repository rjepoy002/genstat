<?php
declare(strict_types=1);
require __DIR__.'/lib/app.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict']);session_start();header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
try {
 $action=$_GET['action']??'open';
 if(in_array($action,['resync-preview','resync'],true)){
  require_once __DIR__.'/lib/workbook-resync.php';$p=period($_GET['period']??'2026-06');
  if($action==='resync-preview'&&$_SERVER['REQUEST_METHOD']==='GET')$result=resync_preview($p);
  elseif($action==='resync'&&$_SERVER['REQUEST_METHOD']==='POST'){
   if(($_SESSION['csrf']??'')===''||!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))throw new InvalidArgumentException('Session expired. Reload the page.');
   if((int)($_SERVER['CONTENT_LENGTH']??0)>65536)throw new InvalidArgumentException('Request too large.');
   $body=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
   $result=resync_confirm($p,is_string($body['token']??null)?$body['token']:'');
  }else throw new InvalidArgumentException('Unsupported Resync request.');
  header('Content-Type: application/json');echo json_encode($result,JSON_THROW_ON_ERROR);exit;
 }
 if($_SERVER['REQUEST_METHOD']==='GET'&&in_array($_GET['action']??'',['template-status','sync'],true)){
  $view=layout(($_GET['action']??'')==='sync');header('Content-Type: application/json');echo json_encode(['hash'=>$view['sha256'],'status'=>$GLOBALS['templateRefresh']],JSON_THROW_ON_ERROR);exit;
 }
 assert_template();$action=$_GET['action']??'open';$p=period($_GET['period']??'2026-06');
 $clientHash=$_SERVER['HTTP_X_TEMPLATE_HASH']??null;
 if(($clientHash!==null||$_SERVER['REQUEST_METHOD']==='POST')&&!hash_equals(layout()['sha256'],$clientHash??''))throw new DomainException('Template version changed or is missing. Reload the page before reopening or saving this report.');
 $clientLayout=$_SERVER['HTTP_X_LAYOUT_HASH']??null;
 if(($clientLayout!==null||$_SERVER['REQUEST_METHOD']==='POST')&&!hash_equals(hash_file('sha256',ROOT.'/config/layout.json'),$clientLayout??''))throw new DomainException('Worksheet layout or input rules changed. Reload the page before reopening or saving this report.');
 if($_SERVER['REQUEST_METHOD']==='POST'){
 if(($_SESSION['csrf']??'')===''||!hash_equals($_SESSION['csrf'],$_SERVER['HTTP_X_CSRF_TOKEN']??''))throw new InvalidArgumentException('Session expired. Reload the page.');
 if((int)($_SERVER['CONTENT_LENGTH']??0)>65536)throw new InvalidArgumentException('Request too large.');
 $body=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
 if(!is_array($body))throw new InvalidArgumentException('Expected a JSON object.');
 if($action==='synchronize'){require_once __DIR__.'/tools/build-layout.php';require_once __DIR__.'/lib/sync-mapping.php';$result=synchronize_draft($p);}
 elseif($action==='unlock'){$result=confirm_formula_cell(is_string($body['cell']??null)?$body['cell']:'',($body['history']??false)===true);}
 elseif($action==='lock'){unset($_SESSION['formulaCells']);$result=formula_auth_status();}
 elseif($action==='save'){
  if(!is_array($body['values']??null)||!is_int($body['revision']??null)||(isset($body['formulas'])&&!is_array($body['formulas'])))throw new InvalidArgumentException('Invalid draft request.');
  $result=formula_api_payload(save_draft($p,$body['values'],$body['revision'],$body['formulas']??null));
 }else throw new InvalidArgumentException('Unknown action.');
 }elseif($_SERVER['REQUEST_METHOD']==='GET'&&$action==='auth'){$result=formula_auth_status();
 }elseif($_SERVER['REQUEST_METHOD']==='GET'&&$action==='open'){$result=formula_api_payload(read_draft($p));
 }elseif($_SERVER['REQUEST_METHOD']==='GET'&&$action==='export'){$d=read_draft($p);$file=export_workbook((array)$d['values'],(array)($d['formulas']??[]));header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="PALECO-GenStat-'.$p.'.xlsx"');header('Content-Length: '.strlen($file));echo $file;exit;
 }else{throw new InvalidArgumentException('Unsupported request.');}
 header('Content-Type: application/json');echo json_encode($result,JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code($e instanceof FormulaPermissionException?403:($e instanceof DomainException?409:($e instanceof InvalidArgumentException||$e instanceof JsonException?400:500)));header('Content-Type: application/json');echo json_encode(['error'=>$e->getMessage()]);}

