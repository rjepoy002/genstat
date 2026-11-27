<?php
declare(strict_types=1);

final class MonthlySnapshotError extends RuntimeException {}

function monthly_snapshot_path(string $month): string {
 if(!base_report_valid_month($month))throw new MonthlySnapshotError('Invalid snapshot month.');
 return dirname(__DIR__).'/storage/monthly-snapshot-'.$month.'.json';
}

function monthly_snapshot_months(): array {
 $months=[];
 foreach(glob(dirname(__DIR__).'/storage/monthly-snapshot-*.json')?:[] as $path)
  if(preg_match('/^monthly-snapshot-(\d{6})\.json$/D',basename($path),$match)&&base_report_valid_month($match[1]))$months[]=$match[1];
 return $months;
}

function monthly_snapshot_read(string $month,?array $layout=null): ?array {
 $path=monthly_snapshot_path($month);
 if(!file_exists($path)&&!is_link($path))return null;
 try{
  $raw=file_get_contents($path);
  $envelope=json_decode($raw===false?'':$raw,true,512,JSON_THROW_ON_ERROR);
  $snapshot=$envelope['snapshot']??null;
  if(!is_array($snapshot)||!hash_equals(hash('sha256',json_bytes($snapshot)),(string)($envelope['sha256']??''))||
   ($snapshot['version']??null)!==1||($snapshot['month']??null)!==$month||
   !is_int($snapshot['draftRevision']??null)||!is_int($snapshot['revision']??null)||$snapshot['revision']<1||!is_string($snapshot['savedAt']??null))
   throw new RuntimeException('Invalid snapshot envelope.');
  foreach(['values','formulas','sqlReplacements','sqlSources','formulaDefinitions','calculation','context'] as $key)
   if(!is_array($snapshot[$key]??null))throw new RuntimeException('Incomplete snapshot.');
  if($layout!==null&&($snapshot['templateHash']??null)!==$layout['sha256'])
   throw new MonthlySnapshotError('This saved month requires its original report template.');
  return $snapshot;
 }catch(MonthlySnapshotError $e){throw $e;}
 catch(Throwable $e){throw new MonthlySnapshotError('The saved monthly snapshot is unreadable or damaged.',0,$e);}
}

function monthly_snapshot_save(array $layout,int $revision,array $state): array {
 $month=base_report_month($layout);$path=monthly_snapshot_path($month);
 $lock=fopen($path.'.lock','c');
 if(!$lock||!flock($lock,LOCK_EX))throw new MonthlySnapshotError('Cannot lock the monthly snapshot.');
 try{
  $previous=monthly_snapshot_read($month,$layout);
  if(($previous['revision']??0)!==$revision)throw new MonthlySnapshotError('Another window saved this month. Reopen it before saving.');
  $snapshot=['version'=>1,'month'=>$month,'templateHash'=>$layout['sha256'],
   'templateSheet'=>$layout['sheets'][0]['name'],'revision'=>$revision+1,'savedAt'=>gmdate('c')]+$state;
  // Normalize the checksum to the decoded JSON representation, including object-shaped maps.
  $snapshot=json_decode(json_bytes($snapshot),true,512,JSON_THROW_ON_ERROR);
  atomic_file_write($path,json_bytes(['sha256'=>hash('sha256',json_bytes($snapshot)),'snapshot'=>$snapshot]));
  return $snapshot;
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function monthly_snapshot_draft(array $layout,?array $snapshot): array {
 return $snapshot
  ?array_intersect_key($snapshot,array_flip(['values','formulas','sqlReplacements','sqlSources']))+['revision'=>$snapshot['draftRevision']]
  :base_report_read_draft($layout);
}

function monthly_snapshot_context(array $layout,array $sql,?array $recovery=null): array {
 return ['preview'=>$sql['values'],'total'=>$sql['total'],'sqlTargets'=>$sql['sqlTargets'],
  'configuredRows'=>$sql['configuredRows'],'coopSyncWarning'=>$sql['coopSyncWarning'],
  'manual'=>base_sql_manual_state($layout),'recovery'=>$recovery??base_report_recovery_state($layout)];
}

function monthly_snapshot_layout(array $layout,array $context): array {
 return array_replace($layout,['sqlManualTargets'=>$context['sqlTargets'],
  'sqlManualState'=>$context['manual'],'recoveryState'=>$context['recovery']]);
}

function monthly_snapshot_calculation(array $calculation): array {
 $calculation['recoveredSales']=(object)$calculation['recoveredSales'];
 $calculation['errors']=(object)$calculation['errors'];
 return $calculation;
}

function monthly_snapshot_available(?string $requested,?string $previous,bool $workingAction=false): array {
 $saved=monthly_snapshot_months();
 $catalog=dirname(__DIR__).'/storage/reporting-month-catalog.json';
 $cached=is_file($catalog)?json_decode((string)file_get_contents($catalog),true):[];
 $months=qs_sorted_months([...$saved,...(is_array($cached)?$cached:[])]);
 $target=$requested??(in_array($previous,$months,true)?$previous:($months[0]??null));
 if(in_array($target,$saved,true))return $months;
 if($workingAction&&$requested===$previous)return qs_sorted_months([...$months,$requested]);
 try{$live=qs_available_months();}
 catch(QuerySettingsError $e){
  if($requested===null&&$saved)return qs_sorted_months($saved);
  throw $e;
 }
 if($live!==$cached){
  try{atomic_file_write($catalog,json_bytes($live));}
  catch(RuntimeException $e){error_log('GENSTAT reporting month catalog could not be cached.');}
 }
 return qs_sorted_months([...$live,...$saved]);
}

function monthly_snapshot_load(array $layout,array $draft,?array $snapshot): array {
 if($snapshot)return [$snapshot['context'],monthly_snapshot_calculation($snapshot['calculation'])];
 $sql=base_report_sql($layout);
 $context=monthly_snapshot_context($layout,$sql);
 $layout=monthly_snapshot_layout($layout,$context);
 $calculation=base_report_calculate($layout,$draft['values']+$context['preview'],$draft['formulas'],$draft['sqlReplacements'],$context['total']);
 $calculation['warnings']=array_merge($calculation['warnings'],$sql['sqlWarnings']);
 return [$context,$calculation];
}

function monthly_snapshot_start_working(array $layout,int $revision,array $context): string {
 $id=bin2hex(random_bytes(16));
 $layout['monthlyWorkingId']=$id;
 monthly_snapshot_update_working($layout,$revision,$context);
 $_SESSION['baseMonthlyWorking']=array_slice($_SESSION['baseMonthlyWorking'],-8,null,true);
 return $id;
}

function monthly_snapshot_get_working(array $layout,$id): ?array {
 if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id))return null;
 $working=$_SESSION['baseMonthlyWorking'][$id]??null;
 return ($working['scope']??null)===base_report_scope($layout)?$working:null;
}

function monthly_snapshot_update_working(array $layout,int $revision,array $context): void {
 if(!isset($layout['monthlyWorkingId']))return;
 $_SESSION['baseMonthlyWorking'][$layout['monthlyWorkingId']]=[
  'scope'=>base_report_scope($layout),'revision'=>$revision,'context'=>$context,
 ];
}
