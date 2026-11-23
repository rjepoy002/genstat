<?php
declare(strict_types=1);
ini_set('display_errors','0');
require_once __DIR__.'/lib/query-settings.php';
require_once __DIR__.'/lib/files.php';
require_once __DIR__.'/lib/coop-report.php';
require_once __DIR__.'/lib/coop-consumption.php';
require_once __DIR__.'/lib/coop-cloud.php';
require_once __DIR__.'/lib/coop-repository.php';

session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict','secure'=>($_SERVER['HTTPS']??'')==='on']);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
try{
 if($_SERVER['REQUEST_METHOD']!=='POST'||(int)($_SERVER['CONTENT_LENGTH']??0)>524288)throw new RuntimeException('Invalid COOP request.',400);
 $body=json_decode(file_get_contents('php://input'),true,32,JSON_THROW_ON_ERROR);
 $action=$body['action']??null;
 if(!is_string($action)||!in_array($action,['view','enroll','deactivate','reassign','refresh','local-preview','apply','connection-view','connection-save','connection-test','connection-remove'],true))
  throw new DomainException('Unknown COOP action.');
 $actor=coop_actor($action);
 $month=$body['month']??null;
 if(!base_report_valid_month($month)||empty($_SESSION['baseCsrf'])||!is_string($body['csrf']??null)||!hash_equals(base_report_csrf($month),$body['csrf']))
  throw new RuntimeException('Reload GENSTAT to renew this session.',403);
 qs_select_month(qs_available_months(),$month);
 $layout=base_report_layout();$layout['reportMonth']=$month;
 if(!is_string($body['hash']??null)||!hash_equals($layout['sha256'],$body['hash']))throw new DomainException('The workbook changed. Reopen the report.');
 $targets=coop_targets($layout);
 $reviewed=getenv('GENSTAT_COOP_REVIEWED_TEMPLATE')===$layout['sha256'];
 $remoteEnabled=getenv('GENSTAT_COOP_CLOUD_ENABLED')==='1';
 $localEnabled=coop_development_active()&&trim(getenv('GENSTAT_COOP_LOCAL_PORTS')?:'')!=='';
 $sourceReady=$remoteEnabled;
 if($localEnabled||$remoteEnabled){
  try{
   $source=coop_connection_settings();
   if(isset($source['secret'])&&coop_local_source($source))$sourceReady=coop_local_port_allowed((int)$source['port']);
  }catch(Throwable){}
 }
 $ready=$reviewed&&$sourceReady&&getenv('GENSTAT_COOP_SCHEMA_REVIEWED')==='1';
 $db=qs_db('writer');
 $operation=bin2hex(random_bytes(16));
 try{
  if($action==='view'){
    $enrollmentReady=false;
    $localPreviewReady=false;
    try{
     coop_require_enrollment_storage($db);
     $source=coop_connection_settings();
     $connection=coop_enrollment_source($source);
     try{
      $enrollmentReady=true;
      if(coop_local_preview_allowed($source)){
       coop_cloud_schema($connection);
       $localPreviewReady=true;
      }
     }finally{$connection->close();}
    }catch(DomainException|mysqli_sql_exception|JsonException){}
   $accounts=coop_effective($db,$month);
   if(count($accounts)>500)throw new DomainException('This period exceeds the enrollment review limit.');
    $accounts=array_map(fn($row)=>array_intersect_key($row,array_flip(['id','area_key','acct_code','effective_from','effective_to','status'])), $accounts);
   $history=coop_query($db,'SELECT r.id,r.status,r.account_count,r.created_at,a.applied_at FROM coop_runs r LEFT JOIN coop_applications a ON a.run_id=r.id WHERE r.period=? ORDER BY r.created_at DESC,r.id DESC LIMIT 30',[$month]);
   $success=coop_query($db,"SELECT MAX(created_at) AS refreshed_at FROM coop_runs WHERE period=? AND status='complete'",[$month])[0]['refreshed_at'];
    $out=['monthLabel'=>base_report_month_label($month),'areas'=>$targets,'accounts'=>$accounts,'history'=>$history,'lastSuccess'=>$success,'enrollmentReady'=>$enrollmentReady,'localPreviewReady'=>$localPreviewReady,'ready'=>$ready,'owned'=>coop_owned(),'conflict'=>coop_conflict($layout,$targets,qs_load($db))];
  }elseif($action==='connection-view'){
   $current=coop_connection_defaults();$storageReady=false;$attention=false;
   try{
    $current=coop_connection_settings();
    coop_secret_key();
    $storageReady=true;
    if(isset($current['secret']))coop_connection_password(coop_connection_input($current,$current));
   }catch(Throwable){$attention=true;}
   $lastAttempt=coop_query($db,"SELECT created_at FROM coop_audit WHERE action='connection-tested' ORDER BY id DESC LIMIT 1")[0]??null;
   $attemptStatus=isset($current['version'], $current['secret'])?(
    coop_query($db,"SELECT JSON_UNQUOTE(JSON_EXTRACT(details,'$.status')) AS status FROM coop_audit WHERE action='connection-tested' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.version'))=? ORDER BY id DESC LIMIT 1",[$current['version']])[0]['status']??null
   ):null;
   $lastSuccess=coop_query($db,"SELECT created_at FROM coop_audit WHERE action='connection-tested' AND JSON_UNQUOTE(JSON_EXTRACT(details,'$.status'))='success' ORDER BY id DESC LIMIT 1")[0]['created_at']??null;
   $configured=isset($current['secret']);
   if($storageReady)$_SESSION['coopConnectionViewedVersion']=coop_connection_version($current);
   $out=['monthLabel'=>base_report_month_label($month),'connection'=>coop_connection_metadata($current),'configured'=>$configured,
    'status'=>$attention?'Configuration Requires Attention':(!$configured?'Not Configured':($attemptStatus==='failed'?'Connection Failed':(isset($current['testedAt'])||$attemptStatus==='success'?'Connection Successful':"Configured \u{2014} Not Tested"))),
    'lastAttempt'=>$lastAttempt['created_at']??null,'lastSuccess'=>$lastSuccess,
     'sourceType'=>coop_loopback_host($current['host'])?'Local':'Remote',
     'editable'=>$storageReady&&($_SERVER['HTTPS']??'')==='on','testEnabled'=>$localEnabled||$remoteEnabled];
  }elseif(in_array($action,['connection-save','connection-test','connection-remove'],true)){
   if(($_SERVER['HTTPS']??'')!=='on')throw new RuntimeException('Use HTTPS for external connection administration.',403);
   if($action==='connection-test'){
    unset($_SESSION['coopConnectionTest']);
    $testedVersion=null;
    try{
     $current=coop_connection_settings();
     $candidate=coop_connection_input($body['connection']??[],$current);
     $receipt=coop_connection_test_receipt($actor,$candidate,$current);
     if(isset($current['version'],$current['secret'])&&coop_connection_metadata($candidate)===coop_connection_metadata($current)){
      try{if(hash_equals($receipt['fingerprint'],coop_connection_fingerprint($current)))$testedVersion=$current['version'];}
      catch(DomainException){}
     }
     $cloud=coop_cloud($candidate);
     try{coop_cloud_schema($cloud);coop_query($cloud,'SELECT 1');}finally{$cloud->close();}
     coop_require_connection_test($receipt,$actor,$candidate,coop_connection_settings());
    }catch(Throwable $error){
     coop_audit($db,$operation,'connection-tested',$actor,$month,['status'=>'failed','version'=>$testedVersion]);
     throw $error instanceof DomainException?$error:new DomainException('Connection test failed. Verify the approved settings and remote reader.');
    }
    coop_audit($db,$operation,'connection-tested',$actor,$month,['status'=>'success','version'=>$testedVersion]);
    $_SESSION['coopConnectionTest']=$receipt;
    $out=['message'=>coop_local_source($candidate)?'Local connection and read probe succeeded.':'Verified TLS connection and read probe succeeded.','expiresAt'=>$receipt['expires']];
   }else{
    $secretLock=coop_connection_lock(LOCK_EX);
    try{
     $current=coop_connection_settings();
     $version=bin2hex(random_bytes(16));
     if($action==='connection-remove'){
      if(($body['confirmed']??null)!==true)throw new DomainException('Confirm removal of the saved connection.');
      if(!isset($current['secret']))throw new DomainException('There is no saved connection to remove.');
      if(($_SESSION['coopConnectionViewedVersion']??null)!==coop_connection_version($current))
       throw new DomainException('The connection changed. Reopen Settings before removing it.');
      $settings=['version'=>$version];
      $event='connection-removed';
     }else{
      $candidate=coop_connection_input($body['connection']??[],$current);
      coop_require_connection_test($_SESSION['coopConnectionTest']??null,$actor,$candidate,$current);
      $settings=$candidate+['version'=>$version,'testedAt'=>$_SESSION['coopConnectionTest']['testedAt']];
      $event='connection-changed';
     }
     coop_audit($db,$operation,$event.'-requested',$actor,$month,['version'=>$version]);
     atomic_file_write(coop_secret_path(),json_bytes($settings));
     unset($_SESSION['coopConnectionTest']);
     $_SESSION['coopConnectionViewedVersion']=$version;
     coop_audit($db,$operation,$event,$actor,$month,['version'=>$version]);
    }finally{flock($secretLock,LOCK_UN);fclose($secretLock);}
    $out=['message'=>$action==='connection-remove'?'Connection removed.':'Connection settings saved.'];
   }
  }elseif(in_array($action,['enroll','deactivate','reassign'],true)){
   coop_validate_enrollment_input($action,$body,$targets);
   coop_require_enrollment_storage($db);
   $resolved=null;
   if($action==='enroll'){
    $code=coop_identifier($body['acctCode']??null);$cloud=coop_enrollment_source();
    try{$resolved=coop_identity(coop_master($cloud,[$code])[$code]??[],$code);}
    finally{$cloud->close();}
   }
   coop_change_enrollment($db,$action,$body,$targets,$actor,$resolved);
   $out=['message'=>'Enrollment updated. Refresh the preview before applying.'];
  }else{
   $provisional=$action==='local-preview';
   if($provisional){
    $localSource=coop_connection_settings();
    if(!coop_local_preview_allowed($localSource))
     throw new DomainException('Local preview requires verified localhost HTTPS and an approved saved loopback connection.');
    coop_require_enrollment_storage($db);
   }elseif(!$ready)throw new DomainException('COOP billing requires a configured source and schema and workbook review.');
   $draft=base_report_read_draft($layout);
   if(($body['revision']??null)!==$draft['revision'])throw new DomainException('Save Draft revision changed. Reopen the report.');
   $values=$body['values']??[];$formulas=$body['formulas']??[];$replacements=$body['sqlReplacements']??[];
   $preview=$body['preview']??[];$total=$body['total']??[];
   if(!is_string($body['previewToken']??null)||!hash_equals(qs_period_preview_token($layout,$preview,$total),$body['previewToken']))
    throw new DomainException('The report preview changed. Reopen the report.');
   base_report_validate($layout,$values,$formulas);
   sql_replacement_submission($layout,$replacements,$draft['sqlReplacements']);
   sql_replacement_validate($layout,$replacements,$formulas);
   $workingHash=qs_stable_hash([$values,$formulas,$replacements,$preview,$total]);
   if($action==='refresh'||$provisional){
    $accounts=coop_effective($db,$month);
    if(count($accounts)>500)throw new DomainException('This period exceeds the enrollment review limit.');
    $settings=$provisional?$localSource:coop_connection_settings();
    $version=qs_stable_hash([$accounts,coop_connection_snapshot($settings),$targets]);
    try{
     if(!$accounts&&!isset($settings['secret']))throw new DomainException('Configure a saved external connection first.');
     $master=$bills=[];$deadline=microtime(true)+18;
     if($accounts){
      $cloud=coop_cloud($settings);
      try{
       $cloud->begin_transaction(MYSQLI_TRANS_START_READ_ONLY|MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
       coop_cloud_schema($cloud);
       $master=coop_master($cloud,array_column($accounts,'acct_code'),$deadline);
       $ids=[];
       foreach($accounts as $account){
        $code=$account['acct_code'];
        try{$identity=coop_identity($master[$code]??[],$code);}
        catch(DomainException){continue;}
        if($identity===$account['canonical_acct_no'])$ids[]=$identity;
       }
       $bills=coop_bills($cloud,$ids,$month,$deadline);
       if(microtime(true)>$deadline)throw new DomainException('Cloud refresh exceeded its time budget.');
       $cloud->rollback();
      }finally{$cloud->close();}
     }
     $current=$values+$preview;
     if($month===BASE_REPORT_LEGACY_MONTH)foreach($targets as $target)$current[$target['ref']]??=$layout['sheets'][0]['cells'][$target['ref']]['value'];
     $result=coop_aggregate($accounts,$master,$bills,$targets,$current,$provisional);
     if(!$accounts&&$provisional)$result['error']='No enrolled accounts for this reporting period.';
     $status=$result['complete']?'complete':'incomplete';
    }catch(Throwable $error){
     $statuses=array_map(fn($account)=>['id'=>(string)$account['id'],'area'=>$targets[$account['area_key']]['label']??'Mapping review required','count'=>0,'kwh'=>null,'status'=>$provisional?'Retrieval failed':'Connection Failed'],$accounts);
     $message=$error instanceof DomainException?$error->getMessage():'Refresh failed. Check the verified COOP connection, schema and query limits.';
     if($provisional&&$error instanceof mysqli_sql_exception&&in_array($error->getCode(),[1205,1969,3024],true))
      $message='The COOP source query timed out. Refresh to try again.';
     $result=['complete'=>false,'accounts'=>$statuses,'areas'=>[],'total'=>null,'error'=>$message];
     $status='failed';
    }
    if($provisional)$result['provisional']=true;
    $expires=time()+900;
    $db->begin_transaction();
    try{
     coop_query_write($db,'INSERT INTO coop_runs(id,actor,period,template_hash,enrollment_hash,draft_revision,working_hash,status,account_count,result_json,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
      [$operation,$actor,$month,$layout['sha256'],$version,$draft['revision'],$workingHash,$status,count($accounts),json_encode($result,JSON_THROW_ON_ERROR),$expires]);
     coop_audit($db,$operation,'consumption-refreshed',$actor,$month,['status'=>$status,'provisional'=>$provisional,'accounts'=>count($accounts),'areas'=>array_values(array_unique(array_column($accounts,'area_key')))]);
     coop_audit($db,$operation,$status==='complete'?'preview-generated':'preview-rejected',$actor,$month,['status'=>$status,'accounts'=>count($accounts),'areas'=>array_values(array_unique(array_column($accounts,'area_key')))]);
     $db->commit();
    }catch(Throwable $error){$db->rollback();throw $error;}
    $out=$result+['id'=>$operation,'expiresAt'=>$expires,'accountCount'=>count($accounts)];
   }else{
    $id=$body['run']??null;
    if(!coop_owned()||($body['confirmed']??null)!==true||!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id))
     throw new DomainException('Enable COOP ownership and confirm a reviewed preview before applying.');
    $lock=fopen(base_report_draft_path($layout).'.lock','c');
    if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Cannot lock the report draft.');
    $connectionLock=null;
    try{
     $connectionLock=coop_connection_lock(LOCK_SH);
     if(base_report_read_draft($layout)['revision']!==$draft['revision'])throw new DomainException('The report was saved in another window.');
     $db->begin_transaction();
     try{
      coop_query($db,'SELECT id FROM coop_enrollment_state WHERE id=1 FOR UPDATE');
      $run=coop_query($db,'SELECT * FROM coop_runs WHERE id=? FOR UPDATE',[$id])[0]??null;
      $result=$run?json_decode($run['result_json'],true,512,JSON_THROW_ON_ERROR):null;
      if(($result['provisional']??false)===true)throw new DomainException('A provisional local preview cannot be applied.');
      if(!$run||$run['actor']!==$actor||$run['period']!==$month||$run['status']!=='complete'||$run['template_hash']!==$layout['sha256']||
         (int)$run['expires_at']<time()||(int)$run['draft_revision']!==$draft['revision']||
         $run['enrollment_hash']!==qs_stable_hash([coop_effective($db,$month,true),coop_connection_snapshot(coop_connection_settings()),$targets]))
       throw new DomainException('The preview is stale. Refresh and review it again.');
      if(coop_conflict($layout,$targets,qs_load($db)))throw new DomainException('An active SQL configuration owns the COOP Consumption row. Resolve the conflict in SQL Settings.');
      $receipt=coop_query($db,'SELECT * FROM coop_applications WHERE run_id=?',[$id])[0]??null;
      $appliedAt=$receipt['applied_at']??gmdate('Y-m-d H:i:s');
      if($run['working_hash']!==$workingHash&&($receipt['result_hash']??null)!==$workingHash)
       throw new DomainException('The working report changed. Refresh and review the preview again.');
      $sources=coop_verified_sources($layout,$body['coopSources']??[]);
      $sqlSources=qs_verified_value_sources($layout,$values,$body['sqlSources']??[]);
      $overrides=[];
      foreach($result['areas'] as $key=>$area){
       if($area['kwh']===null)continue;
       $ref=$targets[$key]['ref']??null;
       if($ref===null||$ref!==$area['ref']||isset($formulas[$ref])||isset($replacements[$ref])||array_key_exists($ref,$total)||array_key_exists($ref,$preview))
        throw new DomainException('A COOP destination is protected or has another source.');
       if(isset($sources[$ref])&&($values[$ref]??null)!==$sources[$ref]['value'])
        $overrides[$ref]=['run'=>$sources[$ref]['run'],'before'=>$sources[$ref]['value'],'after'=>$values[$ref]??null];
       $values[$ref]=$area['kwh'];unset($sqlSources[$ref]);
       $sources[$ref]=coop_source($layout,$ref,$area['kwh'],$id,$appliedAt);
      }
      $calculation=base_report_calculate($layout,$values+$preview,$formulas,$replacements,$total);
      $out=['month'=>$month,'values'=>(object)$values,'sqlSources'=>(object)$sqlSources,'coopSources'=>(object)$sources,'calculation'=>$calculation,
       'message'=>'COOP values applied. Use Save Draft to keep them.'];
      json_encode($out,JSON_THROW_ON_ERROR);
      if(!$receipt){
       coop_query_write($db,'INSERT INTO coop_applications(run_id,actor,draft_revision,result_hash,applied_at) VALUES(?,?,?,?,?)',[$id,$actor,$draft['revision'],qs_stable_hash([$values,$formulas,$replacements,$preview,$total]),$appliedAt]);
       $changes=[];
       foreach($result['areas'] as $key=>$area)if($area['kwh']!==null)$changes[$key]=['cell'=>$area['ref'],'before'=>$area['current'],'after'=>$area['kwh']];
       if($overrides)coop_audit($db,$id,'manual-coop-override',$actor,$month,$overrides);
       coop_audit($db,$id,'consumption-applied',$actor,$month,['sourceModule'=>'coop-consumption','accounts'=>(int)$run['account_count'],'areas'=>$changes]);
      }
      $db->commit();
     }catch(Throwable $error){
      $db->rollback();
      if($error instanceof DomainException)coop_audit($db,$operation,'apply-rejected',$actor,$month,['run'=>$id,'status'=>'rejected']);
      throw $error;
     }
    }finally{if($connectionLock){flock($connectionLock,LOCK_UN);fclose($connectionLock);}flock($lock,LOCK_UN);fclose($lock);}
   }
  }
 }finally{$db->close();}
 $out['developmentMode']=$actor===COOP_LOCAL_DEV_ACTOR;
 echo json_encode($out,JSON_THROW_ON_ERROR);
}catch(Throwable $error){
 $status=$error->getCode()===403?403:400;
 $message=$error instanceof DomainException||$status===403?$error->getMessage():'The COOP operation could not be completed. Check local deployment setup and try again.';
 if($error instanceof QuerySettingsError||($error instanceof mysqli_sql_exception&&in_array($error->getCode(),[1040,1042,1044,1045,1049,1142,1143,1146,1227,2002,2003,2005,2006,2013,2054],true))){
  $status=503;
  $message='COOP database setup is incomplete. Verify the local database migration and permissions.';
 }
 http_response_code($status);
 echo json_encode(['error'=>$message],JSON_INVALID_UTF8_SUBSTITUTE);
}
