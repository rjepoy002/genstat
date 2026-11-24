<?php
declare(strict_types=1);

require_once __DIR__.'/coop-phase1.php';
require_once __DIR__.'/coop-registered-accounts.php';

function coop_summary_targets(array $layout): array {
 $sheet=$layout['sheets'][0];
 $row=qs_area_input_rows($layout)[QS_COOP_ROW_KEY]??null;
 if(!$row||report_area_name(preg_replace('/^[a-z]\.\)\s*/i','',$row['label']))!=='coop consumption')
  throw new DomainException('COOP summary row mapping requires review.');
 // The labels guard the reference workbook. Allocation uses only the AreaIDs.
 $bindings=[
  'B'=>['main',[1]],'C'=>['aborlan',[7,8]],'D'=>['narra',[9]],'E'=>['quezon',[10]],
  'F'=>["brooke's point",[12,13,14]],'G'=>['roxas',[2]],'H'=>['taytay',[5]],'I'=>['balabac',[15]],
  'J'=>['araceli/dumaran',[4,20]],'K'=>['san vicente',[3]],'L'=>['el nido',[6]],
  'M'=>['cagayancillo',[19]],'N'=>['cuyo/magsaysay',[16,17]],'O'=>['agutaya',[18]],'P'=>['rizal',[11]],
 ];
 $headers=array_column(report_area_headers($layout),null,'column');
 $targets=[];
 foreach($bindings as $column=>[$label,$ids]){
  $ref=$column.$row['row'];
  if(($headers[$column]['normalized']??null)!==$label||!isset($row['cells'][$ref])||
     ($sheet['cells'][$ref]['classification']??null)!=='database_input'||
     ($sheet['cells'][$ref]['metadata']['effectiveFormula']??null)!==null)
   throw new DomainException('COOP summary area mapping requires review.');
  foreach($ids as $id)$targets[$id]=$ref;
 }
 return $targets;
}

function coop_summary_values(array $targets,array $accounts,array $previous,array $previousNumbers): array {
 $totals=array_fill_keys(array_values($targets),'0.00');$incomplete=[];
 $activeNumbers=[];$unknownNumbers=[];
 foreach($accounts as $account){
  $ref=$targets[$account['GenstatAreaID']]??null;
  if(!$ref)throw new DomainException('A registered area has no COOP summary mapping.');
  if($account['status']==='No active account')continue;
  $number=$account['AcctNo'];
  if($account['status']==='')$activeNumbers[$ref][]=$number;
  if($account['status']==='Unable to retrieve data'&&$number!==null)$unknownNumbers[$ref][]=$number;
  $amount=$account['kwh'];
  if($amount===null){$incomplete[$ref]=true;continue;}
  $scale=max(strlen(explode('.',$amount,2)[1]??''),strlen(explode('.',$totals[$ref],2)[1]??''));
  $totals[$ref]=bcadd($totals[$ref],$amount,$scale);
 }
 foreach($incomplete as $ref=>$_){
  $eligible=[...($activeNumbers[$ref]??[]),...($unknownNumbers[$ref]??[])];
  $canReuse=isset($previousNumbers[$ref])&&!array_diff($previousNumbers[$ref],$eligible);
  $totals[$ref]=$canReuse?($previous[$ref]??null):null;
  $activeNumbers[$ref]=$canReuse?$previousNumbers[$ref]:[];
 }
 return [$totals,array_keys($incomplete),$activeNumbers];
}

/** Period-local overlay shared by worksheet rendering, calculation and export. */
function base_report_sql(array $layout,bool $onlyTotal=false,bool $strict=false): array {
 $report=qs_period_preview($layout,$onlyTotal,$strict);
 $scope=base_report_scope($layout);
 $entry=$_SESSION['coopSummary'][$scope]??[];
 $cached=$entry['values']??[];
 $values=$cached;$warning=null;
 try{
  $targets=$layout['coopAreaTargets']??coop_summary_targets($layout);
  if(!$targets)throw new DomainException('COOP summary area mapping requires review.');
  $source=null;
  try{
   [$path,$key]=coop_secret_location();
   $connection=coop_read_settings($path,$key)['connection'];
   if($connection&&$connection['type']==='https')$source=coop_connection_id($connection,$key);
  }catch(DomainException|JsonException){$connection=null;}
  $db=qs_db('writer');
  try{[$accounts,,$billingFailed]=coop_registered_month($db,$connection,base_report_month($layout),true);}
  finally{$db->close();}
  if($source!==null){
   $source=hash('sha256',serialize([$source,array_column($accounts,'AcctCode'),array_column($accounts,'GenstatAreaID')]));
  }
  if(($entry['source']??null)!==$source){$cached=[];$entry=[];}
  [$values,$incomplete,$numbers]=coop_summary_values($targets,$accounts,$cached,$entry['numbers']??[]);
  if($billingFailed){
   $warning='COOP sync failed. Check the connection and billing source.';
   if($incomplete)$warning.=' Incomplete totals for '.implode(', ',$incomplete).'.';
   $warning.=' Last valid values are used where available.';
   if(!$accounts)$values=$cached;
  }elseif($incomplete)$warning='COOP sync incomplete for '.implode(', ',$incomplete).'. Check account and billing statuses. Last valid values are used where available.';
  $_SESSION['coopSummary'][$scope]=['values'=>$values,'numbers'=>$numbers,'source'=>$source];
 }catch(Throwable){
  $warning='COOP sync failed. Check the connection, registry, and report mapping. Last valid values are used where available.';
 }
 $available=array_filter($values,fn($value)=>$value!==null);
 $report['total']=$available+$report['total'];
 $report['sqlTargets']=array_fill_keys(array_keys($available),'coop-registered-accounts')+$report['sqlTargets'];
 return $report+['coopSyncWarning'=>$warning];
}
