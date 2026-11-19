<?php
declare(strict_types=1);
require_once __DIR__.'/lib/query-settings.php';
require_once __DIR__.'/lib/files.php';
require_once __DIR__.'/lib/base-report-view.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict']);session_start();$_SESSION['baseCsrf']??=bin2hex(random_bytes(32));
function bh($v): string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
try{
 $post=$_SERVER['REQUEST_METHOD']==='POST';
 if($post){
  header('Content-Type: application/json');$body=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
  if(!base_report_valid_month($body['month']??null)||!is_string($body['csrf']??null)||!hash_equals(base_report_csrf($body['month']),$body['csrf']))throw new RuntimeException('Invalid session token.');
 }
 $months=qs_available_months();
 $loading=$post&&($body['action']??null)==='load-period';
 if($loading&&!base_report_valid_month($body['targetMonth']??null))throw new QuerySettingsError('Select an available reporting month.');
 $month=qs_select_month($months,$post?($loading?$body['targetMonth']:$body['month']):($_GET['month']??null),$_SESSION['baseReportMonth']??null);
 $l=base_report_layout();$l['reportMonth']=$month;$s=$l['sheets'][0];$report=$l['report'];$path=base_report_draft_path($l);$scope=base_report_scope($l);
 $draft=base_report_read_draft($l);
 if($post){
  if(!hash_equals($l['sha256'],(string)($body['hash']??'')))throw new RuntimeException('Source changed. Reload before editing.');
  if($loading){
   $preview=qs_period_preview($l);
   $calculation=base_report_calculate($l,$draft['values']+$preview['values'],$draft['formulas'],$draft['sqlReplacements'],$preview['total']);
   $response=['month'=>$month,'csrf'=>base_report_csrf($month),'revision'=>$draft['revision'],'values'=>(object)$draft['values'],'formulas'=>(object)$draft['formulas'],'sqlReplacements'=>(object)$draft['sqlReplacements'],'sqlSources'=>(object)qs_verified_value_sources($l,$draft['values'],$draft['sqlSources']),'sqlTargets'=>(object)$preview['sqlTargets'],'preview'=>(object)$preview['values'],'total'=>(object)$preview['total'],'previewToken'=>qs_period_preview_token($l,$preview['values'],$preview['total']),'calculation'=>$calculation,'formulaAudit'=>base_report_formula_audit($l)];
   $json=json_encode($response,JSON_THROW_ON_ERROR);
   unset($_SESSION['baseWorksheetUnlock']);
   $_SESSION['baseReportMonth']=$month;
   echo $json;exit;
  }
  if(($body['revision']??null)!==$draft['revision'])throw new RuntimeException('Another window saved this report. Reload before editing.');
  if(!in_array($body['action']??null,['calculate','unlock','lock-formulas','edit-formula','undo-formula','restore-report','undo-restore','save','export'],true))throw new RuntimeException('Unknown report action.');
  $values=$body['values']??[];$formulas=$body['formulas']??[];$replacements=$body['sqlReplacements']??[];base_report_validate($l,$values,$formulas);sql_replacement_validate($l,$replacements,$formulas);sql_replacement_submission($l,$replacements,$draft['sqlReplacements']);
  $reportSql=null;
  $preview=$body['preview']??[];
  $total=$body['total']??[];
  if(!is_array($preview)||!is_array($total))throw new RuntimeException('Invalid report preview.');
  if(isset($body['previewToken'])){
   if(!is_string($body['previewToken'])||!hash_equals(qs_period_preview_token($l,$preview,$total),$body['previewToken']))throw new RuntimeException('The report preview changed. Reload the reporting period.');
  }else{
   if($preview||$total)throw new RuntimeException('Missing report preview authorization.');
   $reportSql=qs_period_preview($l,true);$total=$reportSql['total'];
  }
  if($body['action']==='calculate'&&($body['refreshTotal']??false)===true){$reportSql??=qs_period_preview($l,true);$total=$reportSql['total'];}
  $approved=$_SESSION['baseRestoredFormulaGrants'][$scope]??[];
  foreach(array_keys($formulas+$draft['formulas']) as $ref){
   $current=$formulas[$ref]??null;
   if($current!==($draft['formulas'][$ref]??null)&&(!array_key_exists($ref,$approved)||$approved[$ref]!==$current)
      &&!($current===null&&isset($replacements[$ref])))throw new RuntimeException('Confirm formula unlocking before editing '.$ref);
  }
  if(in_array($body['action'],['unlock','lock-formulas'],true)){
   $unlocked=$body['action']==='unlock';
   $_SESSION['baseWorksheetUnlock']=$unlocked?$scope:null;
   echo json_encode(['unlocked'=>$unlocked],JSON_THROW_ON_ERROR);exit;
  }
  if(in_array($body['action'],['edit-formula','undo-formula'],true)){
   $undo=$body['action']==='undo-formula';$ref=$body['ref']??null;
   if(!is_string($ref)||!base_report_formula_editable($l,$ref))throw new RuntimeException('Select an editable Excel formula cell.');
   if(isset($replacements[$ref])||array_key_exists($ref,$total)||array_key_exists($ref,$preview))throw new RuntimeException('This cell is managed by SQL Query Settings.');
   $before=$formulas[$ref]??null;
   $history=$_SESSION['baseManualFormulaUndo'][$scope]??[];
   if($undo){
    $token=$body['editToken']??null;
    $entry=is_string($token)?($history[$token]??null):null;
    if(!$entry||$entry['cell']!==$ref||$entry['after']!==$before)throw new RuntimeException('This formula edit can no longer be undone.');
    $after=$entry['before'];
   }else{
    if(($_SESSION['baseWorksheetUnlock']??null)!==$scope)throw new RuntimeException('Unlock Formulas before editing this worksheet.');
    if(!is_string($body['content']??null))throw new RuntimeException('Enter a number or supported Excel formula.');
    $after=trim($body['content']);
    if($after===$s['cells'][$ref]['metadata']['effectiveFormula'])$after=null;
    $token=bin2hex(random_bytes(16));
   }
   $next=$formulas;
   if($after===null)unset($next[$ref]);else $next[$ref]=$after;
   $calculation=base_report_calculate($l,$values+$preview,$next,$replacements,$total);
   if(!$undo&&$calculation['cells'][$ref]['error']!==null)throw new RuntimeException($ref.': '.$calculation['cells'][$ref]['error']);
   json_encode($calculation,JSON_THROW_ON_ERROR);
   $audit=base_report_formula_audit($l);
   if($before!==$after)$audit=base_report_append_formula_audit($l,$draft['revision'],$formulas,$next,$undo?'Undo manual formula edit':'Manual formula edit');
   $json=json_encode(['formulas'=>(object)$next,'calculation'=>$calculation,'changed'=>$before!==$after,'editToken'=>!$undo&&$before!==$after?$token:null,'formulaAudit'=>$audit],JSON_THROW_ON_ERROR);
   if($undo)unset($history[$token]);
   elseif($before!==$after)$history[$token]=['cell'=>$ref,'before'=>$before,'after'=>$after];
   $_SESSION['baseManualFormulaUndo'][$scope]=array_slice($history,-50,null,true);
   $_SESSION['baseRestoredFormulaGrants'][$scope][$ref]=$after;
   echo $json;exit;
  }
  if(in_array($body['action'],['restore-report','undo-restore'],true)){
   $undo=$body['action']==='undo-restore';
   $current=['values'=>$values,'formulas'=>$formulas,'sqlReplacements'=>$replacements,'sqlSources'=>qs_verified_value_sources($l,$values,$body['sqlSources']??[]),'preview'=>$preview,'total'=>$total];
   if($undo){
    $operation=$_SESSION['baseRestoreOperation'][$scope]??null;
    if(!$operation||($body['undoToken']??null)!==$operation['token']||qs_stable_hash($current)!==$operation['restoredHash'])throw new RuntimeException('The report changed after restoration. Undo Restore cannot replace newer edits.');
    $next=$operation['previous'];
    $token=null;
   }else{
    // Each report period uses the validated reference workbook. Removing all
    // overlays exposes its exact formulas, including expanded shared formulas.
    $next=$current;
    $next['formulas']=$next['sqlReplacements']=$next['preview']=[];
    $sql=qs_period_preview($l,false,true);
    $next['total']=$sql['total'];
    $targets=[];
    foreach($sql['matchedRows'] as $row)foreach($row['changes'] as $ref=>$change){
     if(isset($targets[$ref]))throw new RuntimeException('Multiple saved configurations target '.$ref.'.');
     $targets[$ref]=true;
     if($change['formulaReplacement']){
      $prior=$replacements[$ref]??null;
      $next['sqlReplacements'][$ref]=$prior&&$prior['mappingRevision']===$row['revision']&&$prior['queryKey']===$row['config']['queryKey']&&$prior['value']===$change['value']
       ?$prior:sql_replacement_issue($l,$ref,$change['value'],$row['config'],$row['revision']);
     }else{
      $next['values'][$ref]=$change['value'];
      $next['sqlSources'][$ref]=qs_value_source($l,$ref,$change['value']);
     }
    }
    $token=qs_stable_hash($current)===qs_stable_hash($next)?null:bin2hex(random_bytes(16));
   }
   $calculation=base_report_calculate($l,$next['values']+$next['preview'],$next['formulas'],$next['sqlReplacements'],$next['total']);
   if(!$undo&&$calculation['errors']){
    $previous=base_report_calculate($l,$values+$preview,$formulas,$replacements,$total);
    $newErrors=array_diff_assoc($calculation['errors'],$previous['errors']);
    if($newErrors)throw new RuntimeException('Restored calculation failed at '.array_key_first($newErrors).': '.reset($newErrors));
   }
   if(base_report_read_draft($l)['revision']!==$draft['revision'])throw new RuntimeException('Another window saved this report. Reload before editing.');
   $response=array_map(fn($map)=>(object)$map,$next)+['month'=>$month,'calculation'=>$calculation,'previewToken'=>qs_period_preview_token($l,$next['preview'],$next['total']),'undoToken'=>$token,'draftChanged'=>qs_overlay_hash($next['values'],$next['formulas'],$next['sqlReplacements'])!==qs_overlay_hash($values,$formulas,$replacements)||$next['sqlSources']!==$current['sqlSources']];
   if(!$undo)$response['sqlTargets']=(object)$sql['sqlTargets'];
   json_encode($calculation,JSON_THROW_ON_ERROR);
   $audit=base_report_formula_audit($l);
   $beforeContent=$formulas+array_column($replacements,'value','ref');
   $afterContent=$next['formulas']+array_column($next['sqlReplacements'],'value','ref');
   if($beforeContent!==$afterContent)$audit=base_report_append_formula_audit($l,$draft['revision'],$beforeContent,$afterContent,$undo?'Undo report restoration':'Restore report formulas');
   $response['formulaAudit']=$audit;
   $json=json_encode($response,JSON_THROW_ON_ERROR);
   // Authorize only the exact validated result after the entire rebuild succeeds.
   foreach(array_diff_key($draft['sqlReplacements'],$next['sqlReplacements']) as $ref=>$record)$_SESSION['sqlRestoreGrants'][$scope][$ref]=true;
   foreach($next['sqlReplacements'] as $record)$_SESSION['sqlReplacementGrants'][$record['signature']]=true;
   $removed=array_fill_keys(array_keys(array_diff_key($formulas+$draft['formulas'],$next['formulas'])),null);
   $_SESSION['baseRestoredFormulaGrants'][$scope]=$next['formulas']+$removed;
   unset($_SESSION['baseManualFormulaUndo'][$scope]);
   if($undo)unset($_SESSION['baseRestoreOperation'][$scope]);
   elseif($token!==null)$_SESSION['baseRestoreOperation'][$scope]=['token'=>$token,'previous'=>$current,'restoredHash'=>qs_stable_hash($next)];
   echo $json;exit;
  }
  $result=base_report_calculate($l,$values+$preview,$formulas,$replacements,$total);
  if($body['action']==='export'){
   require_once __DIR__.'/lib/base-report-export.php';
   $xlsx=base_report_export($l,$values+$preview,$formulas,$replacements,$result,$total);
   header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
   header('Content-Disposition: attachment; filename="PALECO-GenStat-'.$month.'.xlsx"');
   echo $xlsx;exit;
  }
  if(($body['action']??'')==='save'){
   $lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Cannot lock draft.');
   try{$latest=base_report_read_draft($l);if(($body['revision']??-1)!==$latest['revision'])throw new RuntimeException('Another window saved this report. Reload before saving.');
    sql_replacement_submission($l,$replacements,$latest['sqlReplacements']??[]);$history=$latest['sqlHistory']??[];
    foreach(array_unique(array_merge(array_keys($latest['sqlReplacements']??[]),array_keys($replacements))) as $ref){$old=$latest['sqlReplacements'][$ref]??null;$new=$replacements[$ref]??null;if(($old['signature']??null)!==($new['signature']??null))$history[]=['cell'=>$ref,'action'=>$new?'SQL formula replacement':'Restore template formula','previous'=>$old,'replacement'=>$new,'at'=>gmdate('c'),'revision'=>$latest['revision']+1];}
    $draft=array_replace($latest,['values'=>$values,'formulas'=>$formulas,'sqlReplacements'=>(object)$replacements,'sqlHistory'=>$history,'sqlSources'=>(object)qs_verified_value_sources($l,$values,$body['sqlSources']??[]),'revision'=>$latest['revision']+1,'sourceHash'=>$l['sha256'],'reportMonth'=>$month,'savedAt'=>gmdate('c')]);atomic_file_write($path,json_bytes($draft));
    foreach(array_merge((array)($latest['sqlReplacements']??[]),$replacements) as $record)unset($_SESSION['sqlReplacementGrants'][$record['signature']]);unset($_SESSION['sqlRestoreGrants'][$scope]);
   }finally{flock($lock,LOCK_UN);fclose($lock);}
  }
  if($reportSql!==null)$result['sqlTargets']=(object)$reportSql['sqlTargets'];
  echo json_encode($result+['revision'=>$draft['revision'],'total'=>(object)$total,'previewToken'=>qs_period_preview_token($l,$preview,$total)],JSON_THROW_ON_ERROR);exit;
 }
 $reportSql=qs_period_preview($l,true);$total=$reportSql['total'];
 $calc=base_report_calculate($l,$draft['values'],$draft['formulas'],$draft['sqlReplacements'],$total);
 unset($_SESSION['baseWorksheetUnlock']);
 $_SESSION['baseReportMonth']=$month;$monthLabel=base_report_month_label($month);
}catch(Throwable $e){
 http_response_code($e instanceof QuerySettingsError&&$e->getCode()===503?503:400);
 $message=!empty($loading)&&!($e instanceof QuerySettingsError)?'The report could not be loaded. Please try again.':$e->getMessage();
 if($_SERVER['REQUEST_METHOD']==='POST'){header('Content-Type: application/json');echo json_encode(['error'=>$message]);}else echo bh($message);exit;
}
$years=array_values(array_unique(array_map(fn($period)=>substr($period,0,4),$months)));$covered=[];$merges=[];$mergeEnds=[];foreach($s['merges'] as $merge){[$a,$b]=explode(':',$merge);[$ac,$ar]=cell_coordinates($a);[$bc,$br]=cell_coordinates($b);if(!range_contains($report['range'],$a)||!range_contains($report['range'],$b))continue;$merges[$a]=[$bc-$ac+1,$br-$ar+1];$mergeEnds[$a]=$b;for($r=$ar;$r<=$br;$r++)for($c=$ac;$c<=$bc;$c++)if(col_name($c).$r!==$a)$covered[col_name($c).$r]=true;}
$totalStart=$report['lastCol']-2;$areaHeaders=report_area_headers($l);
$widths=[];$hiddenColumns=[];
for($c=1;$c<=$report['lastCol'];$c++){
 $widths[$c]=$c===1?210:($c>=$totalStart?126:94);
 foreach($s['columns'] as $column)if($c>=(int)$column['min']&&$c<=(int)$column['max'])$hiddenColumns[$c]=($column['hidden']??'0')==='1';
}
$coordinateClass='excel-coordinate bg-slate-100 font-medium text-slate-600 dark:bg-slate-800 dark:text-slate-200';
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PALECO GenStat — KWH report</title><script src="assets/report-appearance.js"></script><link rel="stylesheet" href="assets/tailwind.css"><link rel="stylesheet" href="assets/base-report.css"></head><body>
<header class="flex items-center border-b border-slate-200 bg-white dark:border-slate-600 dark:bg-slate-800"><strong>PALECO <span>GenStat</span></strong><label class="report-month">Year <select id="report-year"><?php foreach($years as $year):?><option value="<?=bh($year)?>" <?=$year===substr($month,0,4)?'selected':''?>><?=bh($year)?></option><?php endforeach;?></select></label>
<label class="report-month">Month <select id="report-month"><?php foreach(array_reverse($months) as $option):if(substr($option,0,4)!==substr($month,0,4))continue;?><option value="<?=bh($option)?>" <?=$option===$month?'selected':''?>><?=bh(strtok(base_report_month_label($option),' '))?></option><?php endforeach;?></select></label>
<span id="report-period" aria-live="polite"><?=bh($monthLabel)?></span><div class="report-toolbar ml-auto flex shrink-0 items-center gap-1" role="group" aria-label="Report actions">
 <button id="report-export" type="button" class="report-icon inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors duration-150" aria-label="Export Excel" data-tooltip="Export Excel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm0 0v6h6m-8 3v6m-3-3 3 3 3-3"/></svg></button>
 <button id="save" type="button" class="report-icon inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors duration-150" aria-label="Save Draft" data-tooltip="Save Draft"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 3h13l4 4v13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm3 0v6h9V3m1 18v-8H7v8"/></svg><span class="save-spinner" aria-hidden="true"></span></button>
 <button id="report-formula-history" type="button" class="report-icon inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors duration-150" aria-label="Formula Edit History" title="Formula Edit History" data-tooltip="Formula Edit History" aria-haspopup="dialog"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 1 2.6 6.4M3 5v7h7m2-5v5l4 2"/></svg></button>
 <button id="sql-settings-open" type="button" class="report-icon inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors duration-150" aria-label="Settings" data-tooltip="Settings"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 3-1 3-3 1-2 3 2 2-1 4 3 1 2 3 3-1 3 1 2-3 3-1-1-4 2-2-2-3-3-1-1-3Z"/><circle cx="12" cy="12" r="3"/></svg></button>
</div></header>
<div class="formula-bar"><output id="selection">Select a cell</output><div class="report-formula-field relative flex-1"><input id="formula" aria-label="Selected cell formula or value" readonly><span id="report-formula-colors" class="pointer-events-none absolute overflow-hidden whitespace-pre" aria-hidden="true"></span></div><button id="unlock" type="button" class="report-icon inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors duration-150 focus-visible:ring-2 focus-visible:ring-blue-600 dark:border-slate-600" aria-label="Unlock Formulas" aria-pressed="false" title="Unlock Formulas" data-tooltip="Unlock Formulas"><svg data-lock-icon="closed" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 11h12v10H6V11Zm3 0V7a3 3 0 0 1 6 0v4m-3 4v2"/></svg><svg data-lock-icon="open" hidden viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 11h12v10H6V11Zm3 0V7a3 3 0 0 1 6 0m-3 8v2"/></svg></button>
<div class="report-toolbar formula-actions flex items-center gap-2" role="group" aria-label="Formula and totals actions">
 <button id="restore-sql" type="button" class="report-icon inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors duration-150 hover:bg-slate-100! hover:text-slate-900! focus-visible:ring-2 focus-visible:ring-blue-600 dark:border-slate-600! dark:bg-slate-800! dark:text-slate-200! dark:hover:bg-slate-700! dark:hover:text-white!" aria-label="Restore Template Formula" aria-describedby="restore-sql-tooltip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></button>
 <button id="total-group-toggle" type="button" class="report-icon inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors duration-150" aria-expanded="false" aria-controls="genstat-report-table" aria-label="Show Totals" data-tooltip="Show Totals"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M15 5v14"/><path class="total-chevron" d="m8 9 3 3-3 3"/></svg></button>
 <div id="restore-sql-tooltip" role="tooltip" class="pointer-events-none invisible absolute top-[calc(100%+6px)] right-0 z-[5] w-72 max-w-[calc(100vw-24px)] rounded-sm bg-slate-800 px-2 py-1 text-[11px]/[1.4] text-white dark:bg-slate-700">Restore original Excel formulas and reapply saved SQL and report formulas.</div>
</div><span id="report-reference-note" class="text-xs text-slate-600 dark:text-slate-300" role="status" hidden></span></div>
<main class="report-scroll" tabindex="0" aria-label="Scrollable KWH report"><table id="genstat-report-table" data-total-collapsed="true" aria-label="<?=bh($monthLabel)?> KWH purchased, sold and system losses"><colgroup><col class="excel-gutter"><?php foreach($widths as $column=>$w):?><col data-column="<?=col_name($column)?>" data-excel-column="<?=$column?>" <?=!empty($hiddenColumns[$column])?'hidden':''?> <?=$column>=$totalStart?'data-total-column':''?> style="width:<?=$w?>px"><?php endforeach;?></colgroup>
<thead><tr class="excel-column-heading" data-excel-heading><th class="<?=$coordinateClass?> excel-row-heading" aria-label="Excel row numbers"></th><?php foreach($widths as $column=>$w):$letter=col_name($column);?><th scope="col" class="<?=$coordinateClass?> <?=$column>=$totalStart?'total-column':''?>" data-excel-heading-column="<?=$letter?>" <?=!empty($hiddenColumns[$column])?'hidden':''?>><?=$letter?></th><?php endforeach;?></tr>
<?php for($r=$report['firstRow'];$r<=$report['lastRow'];$r++):if($r===$report['firstRow']+2)echo '</thead><tbody>';$description=$s['cells']['A'.$r]['value']??'';$hidden=($s['rows'][$r]['hidden']??'0')==='1'||base_report_row_hidden($description);$spacer=base_report_row_spacer($s,$report,$r);?><tr data-excel-row="<?=$r?>" <?=$hidden?'hidden':''?> class="<?=$spacer?'spacer-row ':''?><?=preg_match('/^(Total |Dist\. System Loss|System Loss in KWH|Rate of )/i',trim($description))?'total-row':''?>">
<th scope="row" class="<?=$coordinateClass?> excel-row-heading" data-excel-heading><?=$r?></th>
<?php for($c=1;$c<=$report['lastCol'];$c++):$ref=col_name($c).$r;if(isset($covered[$ref])||($r===$report['firstRow']+1&&$c<$totalStart))continue;$cell=$s['cells'][$ref];$ref=$cell['metadata']['reference'];$css=cell_css($l,$cell['style']);if($css['text-align']==='general')$css['text-align']=is_numeric($cell['value'])?'right':'left';$css['vertical-align']=match($css['align-items']){'center'=>'middle','flex-start'=>'top',default=>'bottom'};unset($css['align-items']);$tag=$r<$report['firstRow']+2?'th':'td';$rowToggle=$tag==='td'&&$c===1&&!$hidden&&!$spacer&&trim($description)!=='';$span=$merges[$ref]??[1,1];
if($span!==[1,1]){foreach(['right'=>col_name($c+$span[0]-1).$r,'bottom'=>col_name($c).($r+$span[1]-1)] as $side=>$edge){$edgeCss=cell_css($l,$s['cells'][$edge]['style']);if(isset($edgeCss['border-'.$side]))$css['border-'.$side]=$edgeCss['border-'.$side];}}

$fullHeading=$r===$report['firstRow']&&$c<$totalStart;
if($fullHeading)$span[1]=2;
$visibleSpan=0;for($column=$c;$column<$c+$span[0];$column++)if(empty($hiddenColumns[$column]))$visibleSpan++;
$headingText=$fullHeading?implode("\n",array_column($areaHeaders[$c]['fragments']??[['value'=>$cell['value']]],'value')):null;
?><<?=$tag?> data-ref="<?=$ref?>" <?=isset($mergeEnds[$ref])?'data-excel-range-end="'.bh($mergeEnds[$ref]).'"':''?> <?=$visibleSpan===0?'hidden':''?> data-classification="<?=bh($cell['classification'])?>" <?=base_report_formula_editable($l,$ref)?'data-original-formula-editable':''?> <?=!empty($calc['cells'][$ref]['error'])?'data-calculation-error':''?> <?=$rowToggle?'':'tabindex="0"'?> aria-readonly="<?=$cell['editable']?'false':'true'?>" colspan="<?=max(1,$visibleSpan)?>" rowspan="<?=$span[1]?>" class="<?=$c===1?'particulars ':''?><?=$c>=$totalStart?'total-column ':''?><?=$tag==='th'?'header-cell':''?>" style="<?=bh(css_text($css))?>" <?=$c===1?'aria-label="'.bh($cell['value']).'"':''?> title="<?=bh($ref.' · '.$cell['classification'].($c===1?' · '.$cell['value']:'').($cell['formula']!==null?' · '.(expanded_formulas($s)[$ref]??$cell['formula']):''))?>">

<?php if($fullHeading):?><span class="value"><?=bh($headingText)?></span><?php elseif($rowToggle):?><button type="button" class="report-row-toggle block w-full cursor-pointer" aria-pressed="false"><span class="value"><?=bh($calc['cells'][$ref]['display']??'')?></span></button><?php elseif($cell['editable']):?><input <?=array_key_exists($ref,$total)?'readonly':''?> aria-label="<?=bh($ref.' '.$cell['classification'])?>" data-ref="<?=$ref?>" value="<?=bh(array_key_exists($ref,$total)?($total[$ref]??''):($draft['values'][$ref]??($month!==BASE_REPORT_LEGACY_MONTH&&is_numeric($cell['value'])?'':$cell['value'])))?>"><span class="value"><?=bh($calc['cells'][$ref]['display']??'')?></span><?php else:?><span class="value"><?=bh($calc['cells'][$ref]['display']??'')?></span><?php endif;?></<?=$tag?>>
<?php endfor;?></tr><?php endfor;?></tbody></table></main>
<div class="report-notice flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-600 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300">
 <div class="report-draft flex flex-wrap items-center gap-2"><span id="status" role="status"><?=$draft['revision']>0?'Draft loaded':'No saved report data.'?><?=in_array(null,$total,true)?' Total KWH Sold is unavailable.':''?></span><span id="restore-notice" role="status" class="rounded-md bg-emerald-50 px-2 py-1 text-emerald-900 empty:absolute empty:p-0 data-[error]:bg-red-50 data-[error]:text-red-900 dark:bg-emerald-950 dark:text-emerald-100 dark:data-[error]:bg-red-950 dark:data-[error]:text-red-100"></span><button id="undo-formula" type="button" hidden>Undo Formula Edit</button><button id="undo-restore" class="rounded-md border border-slate-300! px-2! py-1! font-semibold focus-visible:ring-2 focus-visible:ring-blue-600 dark:border-slate-600!" type="button" hidden>Undo Restore</button><button id="report-retry" type="button" hidden>Retry</button></div>
 <details id="report-notes"><summary>Workbook notes · <span id="report-warning-count"><?=count($calc['warnings'])?></span> calculation warnings<span id="report-error-count" hidden></span> · Input legend</summary>
  <div class="report-notes-content"><p>Blue designated inputs can receive SQL values through SQL Query Settings. Formula cells are protected; selecting a cell shows its classification. Blue input indicators are retained. Total KWH Sold uses the saved SQL configuration. Test Query validates changes before Apply &amp; Save.</p><ul id="warnings"></ul></div>
 </details>
</div>
<footer><?=bh($s['name'].'!'.$report['range'])?> · <span id="fit-status">Report table</span> · <span id="cell-kind">Formula cells protected</span></footer>
<div id="report-loading-overlay" class="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm" role="status" aria-live="polite" hidden>
 <div class="report-loading-card flex flex-col items-center rounded-2xl text-center shadow-2xl">
  <div class="report-loading-spinner animate-spin" aria-hidden="true"></div>
  <h2 id="loading-title" class="text-lg font-semibold">Loading GENSTAT Report</h2>
  <p id="loading-period"></p>
 </div>
</div>
<?php require __DIR__.'/partials/sql-settings.php';?>
<dialog id="report-confirmation" class="fixed inset-0 m-auto w-[min(460px,92vw)] rounded-xl border border-slate-200 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-black/60 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" aria-labelledby="report-confirmation-title" aria-describedby="report-confirmation-message report-confirmation-warning report-confirmation-detail">
 <form method="dialog">
  <h2 id="report-confirmation-title" class="m-0 text-lg font-semibold">Confirm report action</h2>
  <p id="report-confirmation-message" class="my-3"></p>
  <p id="report-confirmation-warning" class="my-3 items-start gap-2 rounded-md bg-amber-50 p-3 text-amber-900 not-hidden:flex dark:bg-amber-950 dark:text-amber-100" hidden><svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.8 2.5 18a2 2 0 0 0 1.7 3h15.6a2 2 0 0 0 1.7-3L13.7 3.8a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4m0 4h.01"/></svg><span>Any unsaved manual changes to affected cells will be replaced.</span></p>
  <p id="report-confirmation-detail" class="my-3" hidden></p>
  <div class="flex flex-wrap justify-end gap-2">
   <button value="cancel" class="rounded-md px-4! py-2! transition-colors focus-visible:ring-2 focus-visible:ring-blue-600" autofocus>Cancel</button>
   <button value="confirm" class="rounded-md border-transparent! bg-blue-700! px-4! py-2! font-semibold text-white! transition-colors hover:bg-blue-800! focus-visible:ring-2 focus-visible:ring-blue-600 data-[restore]:bg-red-700! data-[restore]:hover:bg-red-800!">Continue</button>
  </div>
 </form>
</dialog>
<dialog id="report-formula-history-dialog" class="fixed inset-0 m-auto w-[min(540px,92vw)] rounded-xl border border-slate-200 bg-white p-5 text-slate-900 shadow-xl backdrop:bg-black/60 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" aria-labelledby="report-formula-history-title"><form method="dialog"><h2 id="report-formula-history-title" class="m-0 text-lg font-semibold">Formula Edit History</h2><p id="report-formula-history-empty">No manual formula changes have been recorded.</p><ol id="report-formula-history-list" class="space-y-3 break-words p-0"></ol><button autofocus class="rounded-md focus-visible:ring-2">Close</button></form></dialog>
<script type="application/json" id="report-data"><?=json_encode(['total'=>(object)$total,'sqlTargets'=>(object)$reportSql['sqlTargets'],'sqlSources'=>(object)qs_verified_value_sources($l,$draft['values'],$draft['sqlSources']),'previewToken'=>qs_period_preview_token($l,[],$total),'periods'=>$months,'csrf'=>base_report_csrf($month),'month'=>$month,'hash'=>$l['sha256'],'revision'=>$draft['revision'],'values'=>(object)$draft['values'],'formulas'=>(object)$draft['formulas'],'sqlReplacements'=>(object)$draft['sqlReplacements'],'metadata'=>array_map(fn($c)=>$c['metadata'],$s['cells']),'formulaAudit'=>base_report_formula_audit($l),'warnings'=>$calc['warnings'],'errors'=>$calc['errors']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script><script src="assets/formula-references.js"></script><script src="assets/base-reference-highlights.js"></script><script src="assets/edit-history.js"></script><script src="assets/base-report.js"></script><script src="assets/sql-settings.js"></script></body></html>
