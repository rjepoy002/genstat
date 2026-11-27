<?php
declare(strict_types=1);

function base_report_restore(array $layout,array $draft,array $current,bool $undo,?string $undoToken,bool $workingOnly=false): string {
 $path=base_report_draft_path($layout);$scope=base_report_scope($layout);
 $initialManual=$workingOnly?$layout['sqlManualState']:base_sql_manual_state($layout,$draft);
 $operation=$_SESSION['baseRestoreOperation'][$scope]??null;
 if($undo&&(!$operation||$draft['revision']!==$operation['revision']||
    $undoToken!==$operation['token']||qs_stable_hash($current)!==$operation['restoredHash']))
  throw new RuntimeException('The report changed after restoration. Undo Restore cannot replace newer edits.');
 $sql=$undo?null:base_report_sql($layout,false,true);
 $lock=fopen($path.'.lock','c');
 if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Cannot lock the report draft.');
 try{
  $result=base_sql_manual_lock($layout,function()use($layout,$draft,$current,$undo,$operation,$sql,$path,$initialManual,$workingOnly){
   $latest=$workingOnly?$draft:base_report_read_draft($layout);
   if($latest['revision']!==$draft['revision'])throw new RuntimeException('Another window saved this report. Reload before restoring.');
   $manual=$workingOnly?$initialManual:base_sql_manual_state($layout,$latest);
   if($manual['overrides']!==$initialManual['overrides'])
    throw new RuntimeException('A manual SQL value changed in another window. Reload before restoring.');
   $next=$current;$working=$layout;
   $affected=[];
   if($undo){
    $next['formulas']=$operation['formulas'];
   }else{
    $next['formulas']=$next['sqlReplacements']=$next['preview']=[];
    $next['total']=$sql['total'];
    $working['sqlManualTargets']=$sql['sqlTargets'];
    if($sql['coopHistory']===null){
     $existingCoop=array_filter($layout['sqlManualTargets']??[],fn($source)=>$source==='coop-registered-accounts');
     $next['total']+=array_intersect_key($current['total'],$existingCoop);
     $working['sqlManualTargets']+=$existingCoop;
    }
    foreach($sql['matchedRows'] as $row)foreach($row['changes'] as $ref=>$change){
     if(isset($affected[$ref]))throw new RuntimeException('Multiple saved configurations target '.$ref.'.');
     $affected[$ref]=$change['value'];
     if($change['formulaReplacement']){
      $prior=$current['sqlReplacements'][$ref]??null;
      $next['sqlReplacements'][$ref]=$prior&&$prior['mappingRevision']===$row['revision']&&$prior['queryKey']===$row['config']['queryKey']&&$prior['value']===$change['value']
       ?$prior:sql_replacement_issue($layout,$ref,$change['value'],$row['config'],$row['revision']);
     }else{
      $next['values'][$ref]=$change['value'];
      $next['sqlSources'][$ref]=qs_value_source($layout,$ref,$change['value']);
     }
    }
    foreach($sql['sqlTargets'] as $ref=>$source){
     if($source==='coop-registered-accounts'){
      if(isset($affected[$ref]))throw new RuntimeException('Multiple saved configurations target '.$ref.'.');
      $affected[$ref]=$sql['total'][$ref]??null;
      unset($next['values'][$ref],$next['sqlSources'][$ref]);
     }elseif(!array_key_exists($ref,$affected)){
      throw new RuntimeException('A mapped SQL value is missing for '.$ref.'.');
     }
     if(!base_sql_manual_number($affected[$ref]))throw new RuntimeException('A valid SQL value is required for '.$ref.'.');
    }
    $actor=$_SERVER['REMOTE_USER']??$_SESSION['username']??null;
    $manual['generation']=bin2hex(random_bytes(16));
    foreach($affected as $ref=>$value){
     $manual['latest'][$ref]=['value'=>$value,'source'=>$sql['sqlTargets'][$ref]];
     $manual['undoEpoch'][$ref]=$manual['generation'];
     if(!isset($manual['overrides'][$ref]))continue;
     $prior=$manual['overrides'][$ref];
     $manual['audit'][]=array_intersect_key($prior,array_fill_keys(['cell','worksheet','period','row','area'],true))+[
      'action'=>'SQL_MANUAL_OVERRIDE_RESTORED','reason'=>'Restore Report Formulas & SQL Values',
      'timestamp'=>gmdate('c'),'editor'=>is_string($actor)&&$actor!==''?$actor:null,
      'oldSource'=>'MAN','newSource'=>'SQL','oldValue'=>$prior['value'],'newValue'=>$value,
      'originalSql'=>$prior['originalSql'],'latestSql'=>$value,
     ];
     unset($manual['overrides'][$ref]);
    }
    if($sql['coopHistory']!==null)$manual['coopHistory']=$sql['coopHistory'];
    $latest['sqlManualRestore']=$manual;
   }
   $working['sqlManualState']=$manual;
   $calculation=base_report_calculate($working,$next['values']+$next['preview'],$next['formulas'],$next['sqlReplacements'],$next['total']);
   if(!$undo&&$calculation['errors']){
    $beforeLayout=array_replace($layout,['sqlManualState'=>$initialManual]);
    $previous=base_report_calculate($beforeLayout,$current['values']+$current['preview'],$current['formulas'],$current['sqlReplacements'],$current['total']);
    $newErrors=array_diff_assoc($calculation['errors'],$previous['errors']);
    if($newErrors)throw new RuntimeException('Restored calculation failed at '.array_key_first($newErrors).': '.reset($newErrors));
   }
   // Undo Restore can recover prior formula edits, while restored SQL cells
   // keep their fresh values and their removed overrides stay removed.
   $undoFormulas=array_diff_key($current['formulas'],$affected);
   $token=!$undo&&$undoFormulas?bin2hex(random_bytes(16)):null;
   $revision=$latest['revision']+(int)!$workingOnly;
   $events=base_report_formula_events($layout,$revision,$current['formulas']+array_column($current['sqlReplacements'],'value','ref'),
    $next['formulas']+array_column($next['sqlReplacements'],'value','ref'),$undo?'Undo report restoration':'Restore report formulas and SQL values');
   $saved=array_replace($latest,[
    'values'=>$next['values'],'formulas'=>$next['formulas'],'sqlReplacements'=>(object)$next['sqlReplacements'],
    'sqlSources'=>(object)$next['sqlSources'],'revision'=>$revision,'sourceHash'=>$layout['sha256'],
    'reportMonth'=>base_report_month($layout),'savedAt'=>gmdate('c'),
    'restorationAudit'=>array_merge($latest['restorationAudit']??[],$events),
   ]);
   $response=array_map(fn($map)=>(object)$map,$next)+base_sql_manual_public($working,$manual)+[
    'month'=>base_report_month($layout),'revision'=>$revision,'calculation'=>$calculation,
    'previewToken'=>qs_period_preview_token($layout,$next['preview'],$next['total']),
    'undoToken'=>$token,'formulaAudit'=>base_report_formula_audit($layout,$saved),'restoredSqlCells'=>array_keys($affected),
   ];
   if(!$undo)$response+=['sqlTargets'=>(object)$working['sqlManualTargets'],'configuredRows'=>$sql['configuredRows'],'coopSyncWarning'=>null];
   $json=json_encode($response,JSON_THROW_ON_ERROR);
   $recovery=$token===null?null:['token'=>$token,'formulas'=>$undoFormulas,'revision'=>$revision,'restoredHash'=>qs_stable_hash($next)];
   if(!$workingOnly)atomic_file_write($path,json_bytes($saved));
   $state=monthly_snapshot_get_working($layout,$layout['monthlyWorkingId']??null);
   $context=$state['context']??null;
   if($context!==null){
    $context=array_replace($context,['manual'=>$manual,'preview'=>$next['preview'],'total'=>$next['total']]);
    if($sql!==null)$context=array_replace($context,['sqlTargets'=>$working['sqlManualTargets'],
     'configuredRows'=>$sql['configuredRows'],'coopSyncWarning'=>null]);
   }
   return ['json'=>$json,'affected'=>$affected,'recovery'=>$recovery,'targets'=>$working['sqlManualTargets']??[],'context'=>$context,'workingRevision'=>$state['revision']??null,'formulas'=>$next['formulas'],'replacements'=>$next['sqlReplacements']];
  });
 }finally{flock($lock,LOCK_UN);fclose($lock);}
 if($sql!==null){
  $_SESSION['baseSqlManualTargets'][$scope]=$result['targets'];
  if($sql['coopHistory']!==null)$_SESSION['coopSummary'][$scope]=$sql['coopHistory'];
 }
 $_SESSION['baseSqlManualUndo'][$scope]=array_filter($_SESSION['baseSqlManualUndo'][$scope]??[],
  fn($entry)=>!isset($result['affected'][$entry['ref']]));
 unset($_SESSION['baseManualFormulaUndo'][$scope],$_SESSION['baseRestoredFormulaGrants'][$scope],$_SESSION['sqlRestoreGrants'][$scope]);
 if($result['context']!==null)monthly_snapshot_update_working($layout,$result['workingRevision'],$result['context']);
 if($workingOnly){
  foreach($result['replacements'] as $record)$_SESSION['sqlReplacementGrants'][$record['signature']]=true;
  foreach(array_diff_key($draft['sqlReplacements'],$result['replacements']) as $ref=>$record)
   $_SESSION['sqlRestoreGrants'][$scope][$ref]=true;
  foreach(array_keys($draft['formulas']+$result['formulas']) as $ref)
   $_SESSION['baseRestoredFormulaGrants'][$scope][$ref]=$result['formulas'][$ref]??null;
 }
 if($result['recovery']===null)unset($_SESSION['baseRestoreOperation'][$scope]);
 else $_SESSION['baseRestoreOperation'][$scope]=$result['recovery'];
 return $result['json'];
}
