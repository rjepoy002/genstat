<?php
declare(strict_types=1);
require_once __DIR__.'/lib/query-settings.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict']);session_start();header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
$draftLock=null;
try{
 if($_SERVER['REQUEST_METHOD']!=='POST')throw new QuerySettingsError('Use a protected POST request.');
 $raw=file_get_contents('php://input');if(strlen($raw)>150000)throw new QuerySettingsError('Request too large.');$body=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
 if(empty($_SESSION['baseCsrf'])||!hash_equals($_SESSION['baseCsrf'],(string)($body['csrf']??'')))throw new QuerySettingsError('Session expired. Reload the report.');
 $l=base_report_layout();$s=$l['sheets'][0];if(!hash_equals($l['sha256'],(string)($body['hash']??'')))throw new QuerySettingsError('Source template changed. Reload before continuing.');
 $month=qs_month($s['name']);$action=$body['action']??'';
 $db=qs_db('writer');try{$records=qs_load($db);}finally{$db->close();}
 if($action==='metadata'){
  $area=qs_area_metadata();$rows=qs_rows($l);
  foreach($rows as $key=>&$row){$row['canonicalRowId']=qs_canonical_row_id($l,$key);$row['internalQueryKey']=qs_internal_query_key($l,$key,$records);$row['approvedSqlInputRow']=sql_replacement_row_approved($l,$key);$row['status']=isset($records[$key])?($records[$key]['enabled']?'Configured':'Disabled'):'Not Configured';$candidates=qs_targets($l,['rowKey'=>$key,'enabled'=>true]);
   foreach(['cells','protectedCells'] as $group)foreach($row[$group] as $ref=>&$cell){$cell['suggestedAreaId']=report_area_alias($cell['label'],$area['areas'],$area['areaMappings']);$cell['replacementEligible']=!empty($candidates[$ref]['formulaReplacement']);}unset($cell);
  }unset($row);
  $db=qs_db('writer');try{$owner=qs_owner_hash();$source=$l['sha256'];$stmt=$db->prepare('SELECT operation_id,template_row_key,draft_revision,record_hash FROM genstat_query_apply_operations WHERE owner_hash=? AND source_hash=? ORDER BY sequence_id DESC LIMIT 1');$stmt->bind_param('ss',$owner,$source);$stmt->execute();$recoverable=$stmt->get_result()->fetch_assoc();}finally{$db->close();}
  $draftPath=__DIR__.'/storage/base-report-'.$l['sha256'].'.json';$draftRevision=is_file($draftPath)?(int)json_decode(file_get_contents($draftPath),true,512,JSON_THROW_ON_ERROR)['revision']:0;
  if($recoverable&&((int)$recoverable['draft_revision']!==$draftRevision||qs_stable_hash($records[$recoverable['template_row_key']]??null)!==$recoverable['record_hash']))$recoverable=null;
  $out=['rows'=>array_values($rows),'records'=>(object)$records,'month'=>$month,'sheet'=>$s['name'],'historyAvailable'=>true,'recoverableOperation'=>$recoverable]+$area;
 }elseif(in_array($action,['inspect-result','preview-formula','auto-match','test'],true)){
  $input=qs_editor_input($l,$body['config']??[],$records);$key=$input['rowKey'];$config=$input+qs_calculation_settings($input);
  if(!empty($config['formulaEnabled'])&&qs_rows($l)[$key]['row']!==18)throw new QuerySettingsError('Report formulas support row 18 only.');
  $area=qs_area_metadata();$resultKey=qs_result_key($l,(string)($config['sql']??''));
  if($action==='inspect-result'){unset($_SESSION['queryResult']);$result=qs_execute((string)($config['sql']??''),$month);$_SESSION['queryResult']=['key'=>$resultKey,'rowKey'=>$key,'expires'=>time()+300,'result'=>$result];}
  else $result=qs_tested_result($l,$config);
  if($result['truncated'])throw new QuerySettingsError('Incomplete results cannot be used. Refine the SQL query.');
  $out=$result;
  if($action==='preview-formula'){$config['formulaEnabled']=true;$out['computedValues']=qs_formula_values($config,$result);}
  elseif($action==='auto-match')$out+=qs_auto_bindings($l,$config,$result,$area['areas'],$area['areaMappings']);
  elseif($action==='test'){
   $config=qs_normalize($l,$input);$matched=qs_match($l,$config,$result,$area['areas'],$area['areaMappings']);$token=null;
   if(!$matched['errors']){$token=bin2hex(random_bytes(24));}
   $values=$body['values']??[];$formulas=$body['formulas']??[];$replacements=$body['sqlReplacements']??[];$live=base_report_calculate($l,$values,$formulas,$replacements);$targetPreview=[];
   foreach($matched['changes'] as $change)$targetPreview[]=qs_review_target($l,$change,$live,$values,$formulas,$replacements);
   $out+=['validation'=>$matched,'targets'=>$targetPreview,'testToken'=>$token,'validationPassed'=>!$matched['errors']];
  }
 }elseif($action==='save'){
  throw new QuerySettingsError('Configuration is saved only by the confirmed Apply & Save operation.');
 }elseif($action==='history'){
  $key=$body['rowKey']??'';if(!isset(qs_rows($l)[$key]))throw new QuerySettingsError('Unknown report row.');$db=qs_db('writer');
  try{$stmt=$db->prepare('SELECT id,snapshot_json,created_at FROM genstat_query_history WHERE template_row_key=? ORDER BY id DESC LIMIT 20');$stmt->bind_param('s',$key);$stmt->execute();$history=[];$seenVersions=[];
   if(isset($records[$key])){$record=$records[$key];$seenVersions[(int)$record['revision']]=true;$history[]=['id'=>'current','created_at'=>$record['updated_at'],'config'=>qs_record_config($record),'revision'=>(int)$record['revision'],'current'=>true];}
   foreach($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $item){$snapshot=json_decode($item['snapshot_json'],true,512,JSON_THROW_ON_ERROR);if(isset($seenVersions[(int)$snapshot['revision']]))continue;$seenVersions[(int)$snapshot['revision']]=true;$history[]=['id'=>(string)$item['id'],'created_at'=>$snapshot['updated_at']??$item['created_at'],'config'=>qs_record_config($snapshot),'revision'=>(int)$snapshot['revision'],'current'=>false];}
   $out=['history'=>$history];
  }finally{$db->close();}
 }elseif($action==='disable'){
  if(($body['confirmed']??false)!==true)throw new QuerySettingsError('Confirm disabling this configuration.');
  $out=['record'=>qs_disable_configuration($l,(string)($body['rowKey']??''),(int)($body['mappingRevision']??-1)),'message'=>'Configuration disabled. Its SQL and history were retained; report values were not changed.'];unset($_SESSION['queryProposal']);
 }elseif(in_array($action,['refresh-preview','apply','recover'],true)){
  $values=$body['values']??[];$formulas=$body['formulas']??[];$replacements=$body['sqlReplacements']??[];base_report_validate($l,$values,$formulas);sql_replacement_validate($l,$replacements,$formulas);
  // Same lock as Save Draft. No draft file is replaced by Apply & Save.
  $path=__DIR__.'/storage/base-report-'.$l['sha256'].'.json';$draftLock=fopen($path.'.lock','c');if(!$draftLock||!flock($draftLock,$action==='apply'?LOCK_EX:LOCK_SH))throw new QuerySettingsError('Cannot lock the report draft.');
  $draft=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):['revision'=>0,'values'=>[],'formulas'=>[],'sqlReplacements'=>[]];
  if((int)($body['revision']??-1)!==(int)$draft['revision'])throw new QuerySettingsError('The saved draft changed. Reload before reviewing query values.');
  $overlayHash=qs_overlay_hash($values,$formulas,$replacements);
  if($action==='recover'){
   if(($body['confirmed']??false)!==true)throw new QuerySettingsError('Confirm recovery of the last committed Apply & Save result.');
   $db=qs_db('writer');try{$receipt=qs_receipt($db,(string)($body['operationId']??''));$records=qs_load($db);}finally{$db->close();}
   if(!$receipt)throw new QuerySettingsError('No committed result exists for this operation.');$out=qs_restore_receipt($l,$receipt,$records,$body,$draft,true);
  }elseif($action==='refresh-preview'){
   sql_replacement_submission($l,$replacements,$draft['sqlReplacements']??[]);$config=qs_normalize($l,qs_editor_input($l,$body['config']??[],$records));$key=$config['rowKey'];
   if((int)($body['mappingRevision']??-1)!==(int)($records[$key]['revision']??0))throw new QuerySettingsError('Configuration changed in another window. Reload settings.');
   $result=qs_tested_result($l,$config);$area=qs_area_metadata();$matched=qs_match($l,$config,$result,$area['areas'],$area['areaMappings']);$live=base_report_calculate($l,$values,$formulas,$replacements);$changes=[];$unchanged=[];
   foreach($matched['changes'] as $ref=>$change){$reviewed=qs_review_target($l,$change,$live,$values,$formulas,$replacements);if($reviewed['changeType']==='Unchanged')$unchanged[]=$reviewed;else $changes[$ref]=$reviewed;}
   $old=$records[$key]??null;$configChanged=!$old||qs_config_hash(qs_record_config($old))!==qs_config_hash($config)||$old['template_hash']!==$l['sha256'];$token=bin2hex(random_bytes(24));
   $_SESSION['queryProposal']=['token'=>$token,'expires'=>time()+300,'source'=>$l['sha256'],'overlayHash'=>$overlayHash,'recordHash'=>qs_stable_hash($old),'areaHash'=>qs_stable_hash($area),'revision'=>(int)$draft['revision'],'fingerprint'=>qs_fingerprint($config,$l),'changes'=>$changes,'issues'=>$matched['errors'],'configurationChanged'=>$configChanged];
   $out=['token'=>$token,'operationId'=>$token,'expiresAt'=>$_SESSION['queryProposal']['expires'],'changes'=>array_values($changes),'unchanged'=>$unchanged,'blockedTargets'=>$matched['blockedTargets'],'errors'=>$matched['errors'],'notes'=>$matched['notes'],'queriesExecuted'=>0,'testedRows'=>count($result['rows']),'month'=>$month,'configurationChanged'=>$configChanged];
  }else{
   if(($body['confirmed']??false)!==true)throw new QuerySettingsError('Confirm the reviewed changes before applying.');
   $operationId=(string)($body['operationId']??$body['token']??'');$db=qs_db('writer');
   try{$db->begin_transaction();$db->query('SELECT id FROM genstat_query_definitions FOR UPDATE');$db->query('SELECT id FROM genstat_row_query_mappings FOR UPDATE');$records=qs_load($db);$receipt=qs_receipt($db,$operationId);
    if($receipt){$committed=json_decode($receipt['response_json'],true,512,JSON_THROW_ON_ERROR);if(!empty($committed['requiresFormulaConfirmation'])&&($body['confirmFormulaReplacement']??false)!==true)throw new QuerySettingsError('Explicitly confirm replacing the reviewed formulas.');$out=qs_restore_receipt($l,$receipt,$records,$body,$draft);if(!empty($out['requiresFormulaConfirmation'])&&($body['confirmFormulaReplacement']??false)!==true)throw new QuerySettingsError('Explicitly confirm replacing the reviewed formulas.');$db->commit();}
    else{
     sql_replacement_submission($l,$replacements,$draft['sqlReplacements']??[]);$proposal=$_SESSION['queryProposal']??null;
     if(!$proposal||$proposal['expires']<time()||!hash_equals($proposal['token'],(string)($body['token']??''))||$operationId!==$proposal['token']||$proposal['source']!==$l['sha256']||$proposal['overlayHash']!==$overlayHash||$proposal['revision']!==(int)$draft['revision'])throw new QuerySettingsError('The preview is stale. Review proposed changes again.');
     $config=qs_normalize($l,qs_editor_input($l,$body['config']??[],$records));$key=$config['rowKey'];
     if(!hash_equals($proposal['fingerprint'],qs_fingerprint($config,$l))||$proposal['recordHash']!==qs_stable_hash($records[$key]??null))throw new QuerySettingsError('Configuration changed after review. Review proposed changes again.');
     if($proposal['issues'])throw new QuerySettingsError('Resolve all review errors before applying.');if(!$proposal['changes']&&!$proposal['configurationChanged'])throw new QuerySettingsError('No applicable changes found.');
     if($proposal['areaHash']!==qs_stable_hash(qs_area_metadata()))throw new QuerySettingsError('Area mappings changed. Review again.');
     $hasFormula=false;foreach($proposal['changes'] as $change)if($change['formulaReplacement'])$hasFormula=true;
     if($hasFormula&&($body['confirmFormulaReplacement']??false)!==true)throw new QuerySettingsError('Explicitly confirm replacing the reviewed formulas.');
     $expectedRevision=(int)($records[$key]['revision']??0);$sameConfig=isset($records[$key])&&qs_config_hash(qs_record_config($records[$key]))===qs_config_hash($config)&&$records[$key]['template_hash']===$l['sha256'];$newRevision=$expectedRevision+($sameConfig?0:1);
     foreach($proposal['changes'] as $ref=>$change){$target=qs_targets($l,$config)[$ref]??null;
      if(!$target||$change['rowKey']!==$key||($config['bindings'][$ref]['areaId']??null)!==$change['areaId']||!is_numeric($change['value'])||!is_finite((float)$change['value']))throw new QuerySettingsError('SQL target permission changed: '.$ref);
      if($change['formulaReplacement']){if(empty($target['formulaReplacement'])||!sql_replacement_row_approved($l,$key))throw new QuerySettingsError('Formula replacement is not permitted: '.$ref);$replacements[$ref]=sql_replacement_issue($l,$ref,$change['value'],$config,$newRevision,$change['existingFormula']);unset($formulas[$ref]);}else $values[$ref]=$change['value'];
     }
     // Validate the entire working result before touching configuration/history.
     $calculation=base_report_calculate($l,$values,$formulas,$replacements);$record=qs_save($l,$config,$expectedRevision,$db);
     $out=['operationId'=>$operationId,'record'=>$record,'values'=>(object)$values,'formulas'=>(object)$formulas,'sqlReplacements'=>(object)$replacements,'calculation'=>$calculation,'requiresFormulaConfirmation'=>$hasFormula,'draftPending'=>true,'message'=>'Configuration saved and reviewed values applied to the current report. Use Save Draft to persist report values across sessions.'];
     $owner=qs_owner_hash();$source=$l['sha256'];$revision=(int)$draft['revision'];$outputHash=qs_overlay_hash($values,$formulas,$replacements);$baselineHash=qs_overlay_hash($draft['values']??[],$draft['formulas']??[],(array)($draft['sqlReplacements']??[]));$configHash=qs_config_hash($config);$recordHash=qs_stable_hash($record);$areaHash=$proposal['areaHash'];$json=json_encode($out,JSON_THROW_ON_ERROR);$now=gmdate('Y-m-d H:i:s');
     $stmt=$db->prepare('INSERT INTO genstat_query_apply_operations(operation_id,owner_hash,template_row_key,source_hash,draft_revision,input_hash,output_hash,baseline_hash,config_hash,record_hash,area_hash,response_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->bind_param('ssssissssssss',$operationId,$owner,$key,$source,$revision,$overlayHash,$outputHash,$baselineHash,$configHash,$recordHash,$areaHash,$json,$now);$stmt->execute();$db->commit();
     foreach($replacements as $ref=>$replacement){$_SESSION['sqlReplacementGrants'][$replacement['signature']]=true;unset($_SESSION['baseUnlocked'][$l['sha256']][$ref]);}unset($_SESSION['queryProposal']);
    }
   }catch(Throwable $e){try{$db->rollback();}catch(Throwable $ignored){}error_log('GENSTAT Apply & Save could not be confirmed; operation '.(preg_match('/^[a-f0-9]{48}$/D',$operationId)?$operationId:'invalid').'; '.get_class($e));throw $e;}finally{$db->close();}
  }
 }else throw new QuerySettingsError('Unknown SQL settings operation.');
 echo json_encode($out,JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(QuerySettingsError $e){http_response_code(400);echo json_encode(['error'=>$e->getMessage()]);}
catch(Throwable $e){error_log('GENSTAT query settings failed: '.get_class($e).' code '.$e->getCode());http_response_code(400);echo json_encode(['error'=>in_array($action??'',['apply','recover'],true)?'Apply & Save could not be confirmed. Retry the same operation or recover the last committed result before starting a new review.':'The SQL operation could not complete. Check query syntax, result columns and local setup; no configuration or report values were saved.']);}
finally{if(is_resource($draftLock)){flock($draftLock,LOCK_UN);fclose($draftLock);}}
