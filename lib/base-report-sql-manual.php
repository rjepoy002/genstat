<?php
declare(strict_types=1);

function base_sql_manual_path(array $layout): string {
 return base_report_draft_path($layout).'.sql-manual.json';
}
function base_sql_manual_state(array $layout,?array $draft=null): array {
 $path=base_sql_manual_path($layout);
 $snapshot=($draft??base_report_read_draft($layout))['sqlManualRestore']??null;
 $sidecar=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):null;
 // The draft rename publishes a restore and its override removals together.
 // Regular edits continue in the sidecar under the published generation.
 if($snapshot!==null&&($sidecar['generation']??null)!==$snapshot['generation'])return $snapshot;
 return $sidecar??['latest'=>[],'overrides'=>[],'audit'=>[]];
}
function base_sql_manual_lock(array $layout,callable $operation): array {
 $path=base_sql_manual_path($layout);
 $lock=fopen($path.'.lock','c');
 if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Cannot lock SQL override storage.');
 try{return $operation();}finally{flock($lock,LOCK_UN);fclose($lock);}
}
function base_sql_manual_write(array $layout,callable $edit): array {
 return base_sql_manual_lock($layout,function()use($layout,$edit){
  $before=base_sql_manual_state($layout);$after=$edit($before);
  if($after!==$before)atomic_file_write(base_sql_manual_path($layout),json_bytes($after));
  return $after;
 });
}
function base_sql_manual_number($value): bool {
 return (is_string($value)||is_int($value)||is_float($value))
  &&strlen((string)$value)<=32767&&is_numeric($value)&&is_finite((float)$value);
}
function base_sql_manual_cache(array $layout,array $values,array $targets,?array $coopHistory=null,?array $beforeFetch=null): array {
 $latest=[];
 foreach(array_intersect_key($values,$targets) as $ref=>$value)
  if(base_sql_manual_number($value))$latest[$ref]=['value'=>(string)$value,'source'=>$targets[$ref]];
 $state=base_sql_manual_state($layout);
 $changed=array_filter($latest,fn($entry,$ref)=>($state['latest'][$ref]??null)!==$entry,ARRAY_FILTER_USE_BOTH);
 if(!$changed&&($coopHistory===null||($state['coopHistory']??null)===$coopHistory))return $state;
 return base_sql_manual_write($layout,function($state)use($latest,$coopHistory,$beforeFetch){
  if($beforeFetch!==null&&($beforeFetch['generation']??null)!==($state['generation']??null))return $state;
  $state['latest']=$latest+$state['latest'];
  if($coopHistory!==null)$state['coopHistory']=$coopHistory;
  return $state;
 });
}
function base_sql_manual_latest(array $layout,array $state): array {
 $latest=[];
 foreach($state['latest'] as $ref=>$entry)
  if(!isset($layout['sqlManualTargets'])||($layout['sqlManualTargets'][$ref]??null)===$entry['source'])
   $latest[$ref]=$entry['value'];
 return $latest;
}
function base_sql_manual_effective(array $layout,array $total): array {
 if(!isset($layout['reportMonth']))return $total;
 $state=$layout['sqlManualState']??base_sql_manual_state($layout);
 $latest=base_sql_manual_latest($layout,$state);
 foreach($state['overrides'] as $ref=>$entry)$latest[$ref]=$entry['value'];
 return $latest+$total;
}
function base_sql_manual_public(array $layout,?array $state=null): array {
 $state??=base_sql_manual_state($layout);
 return ['sqlManualOverrides'=>(object)$state['overrides'],'sqlLatest'=>(object)base_sql_manual_latest($layout,$state)];
}
function base_sql_manual_targets(array $layout): array {
 $db=qs_db('writer');
 try{$targets=qs_enabled_value_targets($layout,qs_load($db));}finally{$db->close();}
 return array_fill_keys(array_values($layout['coopAreaTargets']??[]),'coop-registered-accounts')+$targets;
}
function base_sql_manual_edit(array $layout,string $action,string $ref,array $body,?string $source,$proof,callable $calculate): array {
 $scope=base_report_scope($layout);
 $history=$_SESSION['baseSqlManualUndo'][$scope]??[];
 $token=$action==='undo-sql-manual'?($body['editToken']??null):bin2hex(random_bytes(16));
 $undo=is_string($token)?($history[$token]??null):null;
 $actor=$_SERVER['REMOTE_USER']??$_SESSION['username']??null;
 if(!is_string($actor)||$actor==='')$actor=null;
 $command=null;$calculation=null;
 $edit=function($state)use($layout,$action,$ref,$body,$source,$proof,$undo,$actor,$calculate,&$command,&$calculation){
  if(!isset($layout['monthlySnapshotWorking'])&&base_report_read_draft($layout)['revision']!==$body['revision'])
   throw new RuntimeException('Another window saved this report. Reload before editing.');
  $before=$state['overrides'][$ref]??null;
  $latest=$state['latest'][$ref]['value']??null;
  $undoEpoch=$state['undoEpoch'][$ref]??null;
  $now=gmdate('c');
  if($action==='undo-sql-manual'){
   if(!$undo||$undo['ref']!==$ref||$undo['after']!==$before||($undo['epoch']??null)!==$undoEpoch)
    throw new RuntimeException('This SQL cell edit can no longer be undone.');
   $after=$undo['before'];
   $event='UNDONE';
  }elseif($action==='restore-sql-value'){
   if(!$before)throw new RuntimeException('Select a manually overridden SQL cell.');
   if(($body['overrideId']??null)!==$before['id'])throw new RuntimeException('The manual value changed. Review it before restoring SQL.');
   if(!base_sql_manual_number($latest)||$source!==($state['latest'][$ref]['source']??null))
    throw new RuntimeException('No valid SQL value is available. The manual value was kept.');
   $after=null;
   $event='RESTORED';
  }else{
   if(($body['overrideId']??null)!==($before['id']??null))throw new RuntimeException('The manual value changed in another window. Reload before editing.');
   $value=$body['content']??null;
   if(!is_string($value)||!base_sql_manual_number(trim($value)))throw new RuntimeException('Enter a valid numeric value.');
   $value=trim($value);
   if(!$before&&(!$source||($source!==($state['latest'][$ref]['source']??null)&&!base_sql_manual_number($proof))))
    throw new RuntimeException('Select a cell with a valid SQL value.');
   if($source&&$source!==($state['latest'][$ref]['source']??null)&&base_sql_manual_number($proof)){
    $latest=(string)$proof;
    $state['latest'][$ref]=['value'=>$latest,'source'=>$source];
   }
   if((float)$value===(float)($before['value']??$latest))return $state;
   $meta=$layout['sheets'][0]['cells'][$ref]['metadata'];
   $after=[
    'id'=>bin2hex(random_bytes(16)),'cell'=>$ref,'worksheet'=>$layout['sheets'][0]['name'],'period'=>base_report_month($layout),
    'row'=>trim($layout['sheets'][0]['cells']['A'.$meta['row']]['value']??''),
    'area'=>report_area_headers($layout)[$meta['column']]['label']??col_name($meta['column']),
    'value'=>$value,'originalSql'=>$before['originalSql']??$latest,
    'createdAt'=>$before['createdAt']??$now,'modifiedAt'=>$now,'modifiedBy'=>$actor,
   ];
   $event=$before?'UPDATED':'CREATED';
  }
  if($after===null)unset($state['overrides'][$ref]);else $state['overrides'][$ref]=$after;
  $location=$before??$after;
  $state['audit'][]=array_intersect_key($location,array_fill_keys(['cell','worksheet','period','row','area'],true))+[
   'action'=>'SQL_MANUAL_OVERRIDE_'.$event,'timestamp'=>$now,'editor'=>$actor,
   'oldSource'=>$before?'MAN':'SQL','newSource'=>$after?'MAN':'SQL',
   'oldValue'=>$before['value']??$latest,'newValue'=>$after['value']??$latest,
   'originalSql'=>$location['originalSql'],'latestSql'=>$latest,
  ];
  $command=['ref'=>$ref,'before'=>$before,'after'=>$after,'epoch'=>$undoEpoch];
  $calculation=$calculate($state);
  json_encode($calculation,JSON_THROW_ON_ERROR);
  return $state;
 };
 if(isset($layout['monthlySnapshotWorking'])){
  $state=$edit($layout['sqlManualState']);
 }else{
  $state=base_sql_manual_write($layout,$edit);
 }
 $working=monthly_snapshot_get_working($layout,$layout['monthlyWorkingId']??null);
 if($working!==null)monthly_snapshot_update_working($layout,$working['revision'],array_replace($working['context'],['manual'=>$state]));
 if($command){
  if($undo)unset($history[$token]);else $history[$token]=$command;
  $_SESSION['baseSqlManualUndo'][$scope]=array_slice($history,-50,null,true);
 }
 return base_sql_manual_public($layout,$state)+['changed'=>$command!==null,'editToken'=>$command&&!$undo?$token:null,
  'calculation'=>$calculation??$calculate($state)];
}
