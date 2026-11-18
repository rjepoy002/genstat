<?php
declare(strict_types=1);
require_once __DIR__.'/lib/query-settings.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict']);session_start();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$draftLock=null;
try{
 if($_SERVER['REQUEST_METHOD']!=='POST')throw new QuerySettingsError('Use a protected POST request.');
 $raw=file_get_contents('php://input');if(strlen($raw)>150000)throw new QuerySettingsError('Request too large.');$body=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
 if(empty($_SESSION['baseCsrf'])||!base_report_valid_month($body['month']??null)||!is_string($body['csrf']??null)||!hash_equals(base_report_csrf($body['month']),$body['csrf']))throw new QuerySettingsError('Session expired. Reload the report.');
 $month=qs_select_month(qs_available_months(),$body['month']);$l=base_report_layout();$l['reportMonth']=$month;$s=$l['sheets'][0];if(!hash_equals($l['sha256'],(string)($body['hash']??'')))throw new QuerySettingsError('Source template changed. Reload before continuing.');
 $action=$body['action']??'';$draft=base_report_read_draft($l);if(($body['revision']??null)!==$draft['revision'])throw new QuerySettingsError('The saved draft changed. Reload before continuing.');
 $db=qs_db('writer');try{$records=qs_load($db);}finally{$db->close();}
 if($action==='metadata'){
  $area=qs_area_metadata();$rows=qs_rows($l);
  foreach($rows as $key=>&$row){$row['canonicalRowId']=qs_canonical_row_id($l,$key);$row['internalQueryKey']=qs_internal_query_key($l,$key,$records);$row['approvedSqlInputRow']=sql_replacement_row_approved($l,$key);$row['status']=isset($records[$key])?($records[$key]['enabled']?'Configured':'Disabled'):'Not Configured';$candidates=qs_targets($l,['rowKey'=>$key,'enabled'=>true]);
   foreach(['cells','protectedCells'] as $group)foreach($row[$group] as $ref=>&$cell){$cell['suggestedAreaId']=report_area_alias($cell['label'],$area['areas'],$area['areaMappings']);$cell['replacementEligible']=!empty($candidates[$ref]['formulaReplacement']);}unset($cell);
  }unset($row);
  $db=qs_db('writer');try{$owner=qs_owner_hash($l);$source=$l['sha256'];$stmt=$db->prepare('SELECT operation_id,template_row_key,draft_revision,record_hash FROM genstat_query_apply_operations WHERE owner_hash=? AND source_hash=? ORDER BY sequence_id DESC LIMIT 1');$stmt->bind_param('ss',$owner,$source);$stmt->execute();$recoverable=$stmt->get_result()->fetch_assoc();}finally{$db->close();}
  $draftRevision=$draft['revision'];
  if($recoverable&&((int)$recoverable['draft_revision']!==$draftRevision||qs_stable_hash($records[$recoverable['template_row_key']]??null)!==$recoverable['record_hash']))$recoverable=null;
  $out=['rows'=>array_values($rows),'records'=>(object)$records,'month'=>$month,'monthLabel'=>base_report_month_label($month),'sheet'=>$s['name'],'historyAvailable'=>true,'recoverableOperation'=>$recoverable]+$area;
 }elseif(in_array($action,['inspect-result','preview-formula','auto-match','test'],true)){
  if(in_array($action,['inspect-result','test'],true))unset($_SESSION['queryTest']);
  $input=qs_editor_input($l,$body['config']??[],$records);$key=$input['rowKey'];$config=$input+qs_calculation_settings($input);
  $area=qs_area_metadata();$resultKey=qs_result_key($l,(string)($config['sql']??''));
  if($action==='inspect-result'){unset($_SESSION['queryResult']);$result=qs_execute((string)($config['sql']??''),$month);$_SESSION['queryResult']=['key'=>$resultKey,'rowKey'=>$key,'expires'=>time()+300,'result'=>$result];}
  else $result=qs_tested_result($l,$config);
  if($result['truncated'])throw new QuerySettingsError('Incomplete results cannot be used. Refine the SQL query.');
  $out=$result;
  if($action==='preview-formula'){
   qs_validate_result_month($result,$month);
   $config['formulaEnabled']=true;
   $out['computedValues']=qs_formula_values($config,$result);
  }
  elseif($action==='auto-match')$out+=qs_auto_bindings($l,$config,$result,$area['areas'],$area['areaMappings']);
  elseif($action==='test'){
   qs_validate_result_month($result,$month);
   $config=qs_normalize($l,$input);$matched=qs_match($l,$config,$result,$area['areas'],$area['areaMappings']);$token=null;
   if(!$matched['changes']&&!$matched['errors'])$matched['errors'][]='No eligible SQL values to apply.';
   $expires=null;
   if(!$matched['errors']){
    $token=bin2hex(random_bytes(24));$expires=time()+300;
    $_SESSION['queryTest']=['token'=>$token,'expires'=>$expires,'fingerprint'=>qs_fingerprint($config,$l),
     'recordHash'=>qs_stable_hash($records[$key]??null),'areaHash'=>qs_stable_hash($area),'revision'=>$draft['revision']];
   }
   $out+=['validation'=>['errors'=>$matched['errors'],'notes'=>$matched['notes']],
    'testToken'=>$token,'expiresAt'=>$expires,'validationPassed'=>$token!==null];
  }
 }elseif($action==='save'){
  throw new QuerySettingsError('Configuration is saved only by Apply & Save.');
 }elseif($action==='history'){
  $key=$body['rowKey']??'';if(!isset(qs_rows($l)[$key]))throw new QuerySettingsError('Unknown report row.');$db=qs_db('writer');
  try{$stmt=$db->prepare('SELECT id,snapshot_json,created_at FROM genstat_query_history WHERE template_row_key=? ORDER BY id DESC LIMIT 20');$stmt->bind_param('s',$key);$stmt->execute();$history=[];$seenVersions=[];
   if(isset($records[$key])){$record=$records[$key];$seenVersions[(int)$record['revision']]=true;$history[]=['id'=>'current','created_at'=>$record['updated_at'],'config'=>qs_record_config($record),'revision'=>(int)$record['revision'],'current'=>true];}
   foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $item){$snapshot=json_decode($item['snapshot_json'],true,512,JSON_THROW_ON_ERROR);if(isset($seenVersions[(int)$snapshot['revision']]))continue;$seenVersions[(int)$snapshot['revision']]=true;$history[]=['id'=>(string)$item['id'],'created_at'=>$snapshot['updated_at']??$item['created_at'],'config'=>qs_record_config($snapshot),'revision'=>(int)$snapshot['revision'],'current'=>false];}
   $out=['history'=>$history];
  }finally{$db->close();}
 }elseif($action==='disable'){
  if(($body['confirmed']??false)!==true)throw new QuerySettingsError('Confirm disabling this configuration.');
  $out=['record'=>qs_disable_configuration($l,(string)($body['rowKey']??''),(int)($body['mappingRevision']??-1)),'message'=>'Configuration disabled. Its SQL and history were retained; report values were not changed.'];unset($_SESSION['queryTest']);
 }elseif(in_array($action,['apply','recover'],true)){
  $values=$body['values']??[];$formulas=$body['formulas']??[];$replacements=$body['sqlReplacements']??[];base_report_validate($l,$values,$formulas);sql_replacement_validate($l,$replacements,$formulas);
  // Same lock as Save Draft. No draft file is replaced by Apply & Save.
  $path=base_report_draft_path($l);$draftLock=fopen($path.'.lock','c');if(!$draftLock||!flock($draftLock,$action==='apply'?LOCK_EX:LOCK_SH))throw new QuerySettingsError('Cannot lock the report draft.');
  $draft=base_report_read_draft($l);
  if((int)($body['revision']??-1)!==(int)$draft['revision'])throw new QuerySettingsError('The saved draft changed. Reload before applying query values.');
  $overlayHash=qs_overlay_hash($values,$formulas,$replacements);
  if($action==='recover'){
   if(($body['confirmed']??false)!==true)throw new QuerySettingsError('Confirm recovery of the last committed Apply & Save result.');
   $db=qs_db('writer');try{$receipt=qs_receipt($db,(string)($body['operationId']??''));$records=qs_load($db);}finally{$db->close();}
   if(!$receipt)throw new QuerySettingsError('No committed result exists for this operation.');$out=qs_restore_receipt($l,$receipt,$records,$body,$draft,true);
  }else{
   $operationId=(string)($body['operationId']??$body['token']??'');$db=qs_db('writer');
   try{$db->begin_transaction();$db->query('SELECT id FROM genstat_query_definitions FOR UPDATE');$db->query('SELECT id FROM genstat_row_query_mappings FOR UPDATE');$records=qs_load($db);$receipt=qs_receipt($db,$operationId);
    if($receipt){$out=qs_restore_receipt($l,$receipt,$records,$body,$draft);$db->commit();}
    else{
     sql_replacement_submission($l,$replacements,$draft['sqlReplacements']??[]);
     $tested=$_SESSION['queryTest']??null;
     if(!$tested||$tested['expires']<=time()||!hash_equals($tested['token'],$operationId)||$tested['revision']!==$draft['revision'])
      throw new QuerySettingsError('Test Query again. The test is missing or expired.');
     $config=qs_normalize($l,qs_editor_input($l,$body['config']??[],$records));$key=$config['rowKey'];
     if(!hash_equals($tested['fingerprint'],qs_fingerprint($config,$l))||$tested['recordHash']!==qs_stable_hash($records[$key]??null))
      throw new QuerySettingsError('The reporting period or settings changed. Test Query again.');
     $area=qs_area_metadata();$areaHash=qs_stable_hash($area);
     if($tested['areaHash']!==$areaHash)throw new QuerySettingsError('Area mappings changed. Test Query again.');
     $result=qs_execute($config['sql'],$month);
     qs_validate_result_month($result,$month);
     $matched=qs_match($l,$config,$result,$area['areas'],$area['areaMappings']);
     if($matched['errors']||!$matched['changes'])throw new QuerySettingsError($matched['errors'][0]??'No eligible SQL values to apply.');
     $expectedRevision=(int)($records[$key]['revision']??0);$sameConfig=isset($records[$key])&&qs_config_hash(qs_record_config($records[$key]))===qs_config_hash($config)&&$records[$key]['template_hash']===$l['sha256'];$newRevision=$expectedRevision+($sameConfig?0:1);
     $sqlSources=qs_verified_value_sources($l,$values,$body['sqlSources']??[]);
     foreach($matched['changes'] as $ref=>$change){$target=qs_targets($l,$config)[$ref]??null;
      if(!$target||$change['rowKey']!==$key||($config['bindings'][$ref]['areaId']??null)!==$change['areaId']||!is_numeric($change['value'])||!is_finite((float)$change['value']))throw new QuerySettingsError('SQL target permission changed: '.$ref);
      if($change['formulaReplacement']){if(empty($target['formulaReplacement'])||!sql_replacement_row_approved($l,$key))throw new QuerySettingsError('Formula replacement is not permitted: '.$ref);$replacements[$ref]=sql_replacement_issue($l,$ref,$change['value'],$config,$newRevision,$formulas[$ref]??null);unset($formulas[$ref]);}
      else{$values[$ref]=$change['value'];$sqlSources[$ref]=qs_value_source($l,$ref,$change['value']);}
     }
     $preview=$body['preview']??[];$total=$body['total']??[];
     if(!is_array($preview)||!is_array($total)||(($preview||$total)&&
       (!is_string($body['previewToken']??null)||!hash_equals(qs_period_preview_token($l,$preview,$total),$body['previewToken']))))
      throw new QuerySettingsError('The report preview changed. Reload the reporting period.');
     if(sql_replacement_row_approved($l,$key))$total=array_column($matched['changes'],'value','ref');
     // Validate the entire working result before touching configuration/history.
     $calculation=base_report_calculate($l,$values+$preview,$formulas,$replacements,$total);$record=qs_save($l,$config,$expectedRevision,$db);
     $out=['operationId'=>$operationId,'month'=>$month,'record'=>$record,'values'=>(object)$values,'formulas'=>(object)$formulas,'sqlReplacements'=>(object)$replacements,'sqlSources'=>(object)$sqlSources,'calculation'=>$calculation,
      'previewContext'=>['sqlTargets'=>(object)qs_enabled_value_targets($l,array_replace($records,[$key=>$record])),'preview'=>(object)$preview,'total'=>(object)$total,'previewToken'=>qs_period_preview_token($l,$preview,$total)],
      'draftPending'=>true,'message'=>'Configuration saved and report updated. Use Save Draft to keep the applied values across sessions.'];
     $owner=qs_owner_hash($l);$source=$l['sha256'];$revision=(int)$draft['revision'];$outputHash=qs_overlay_hash($values,$formulas,$replacements);$baselineHash=qs_overlay_hash($draft['values']??[],$draft['formulas']??[],(array)($draft['sqlReplacements']??[]));$configHash=qs_config_hash($config);$recordHash=qs_stable_hash($record);$json=json_encode($out,JSON_THROW_ON_ERROR);$now=gmdate('Y-m-d H:i:s');
     $stmt=$db->prepare('INSERT INTO genstat_query_apply_operations(operation_id,owner_hash,template_row_key,source_hash,draft_revision,input_hash,output_hash,baseline_hash,config_hash,record_hash,area_hash,response_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->bind_param('ssssissssssss',$operationId,$owner,$key,$source,$revision,$overlayHash,$outputHash,$baselineHash,$configHash,$recordHash,$areaHash,$json,$now);$stmt->execute();$db->commit();
     foreach($replacements as $ref=>$replacement){$_SESSION['sqlReplacementGrants'][$replacement['signature']]=true;unset($_SESSION['baseUnlocked'][base_report_scope($l)][$ref]);}unset($_SESSION['queryTest']);
    }
   }catch(Throwable $e){try{$db->rollback();}catch(Throwable $ignored){}error_log('GENSTAT Apply & Save could not be confirmed; operation '.(preg_match('/^[a-f0-9]{48}$/D',$operationId)?$operationId:'invalid').'; '.get_class($e));throw $e;}finally{$db->close();}
  }
 }else throw new QuerySettingsError('Unknown SQL settings operation.');
 echo json_encode($out,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(QuerySettingsError $e){http_response_code($e->getCode()===503?503:400);echo json_encode(['error'=>$e->getMessage()]);}
catch(Throwable $e){error_log('GENSTAT query settings failed: '.get_class($e).' code '.$e->getCode());http_response_code(400);echo json_encode(['error'=>in_array($action??'',['apply','recover'],true)?'Apply & Save could not be confirmed. Retry the same operation or recover the last committed result before running a new test.':'The SQL operation could not complete. Check query syntax, result columns and local setup; no configuration or report values were saved.']);}
finally{if(is_resource($draftLock)){flock($draftLock,LOCK_UN);fclose($draftLock);}}
