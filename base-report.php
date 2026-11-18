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
   $response=['month'=>$month,'csrf'=>base_report_csrf($month),'revision'=>$draft['revision'],'values'=>(object)$draft['values'],'formulas'=>(object)$draft['formulas'],'sqlReplacements'=>(object)$draft['sqlReplacements'],'sqlSources'=>(object)qs_verified_value_sources($l,$draft['values'],$draft['sqlSources']),'sqlTargets'=>(object)$preview['sqlTargets'],'preview'=>(object)$preview['values'],'total'=>(object)$preview['total'],'previewToken'=>qs_period_preview_token($l,$preview['values'],$preview['total']),'calculation'=>$calculation];
   $json=json_encode($response,JSON_THROW_ON_ERROR);
   $_SESSION['baseReportMonth']=$month;
   echo $json;exit;
  }
  if(($body['revision']??null)!==$draft['revision'])throw new RuntimeException('Another window saved this report. Reload before editing.');
  if(!in_array($body['action']??null,['calculate','unlock','restore-sql','save','export'],true))throw new RuntimeException('Unknown report action.');
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
  if(($body['action']??'')==='restore-sql'){
   $ref=$body['ref']??'';if(!isset($replacements[$ref]))throw new RuntimeException('Select an SQL-replaced formula to restore.');$_SESSION['sqlRestoreGrants'][$scope][$ref]=true;unset($_SESSION['sqlReplacementGrants'][$replacements[$ref]['signature']],$_SESSION['baseUnlocked'][$scope][$ref]);unset($replacements[$ref],$formulas[$ref]);
   echo json_encode(['sqlReplacements'=>(object)$replacements,'formulas'=>(object)$formulas,'calculation'=>base_report_calculate($l,$values+$preview,$formulas,$replacements,$total)],JSON_THROW_ON_ERROR);exit;
  }
  // Formula overrides need an explicit, per-cell confirmation in this session.
  if(($body['action']??'')==='unlock'){
   if(array_key_exists($body['ref']??'',$total))throw new RuntimeException('Manage this SQL-controlled cell in SQL Query Settings.');
   $ref=$body['ref']??'';if(isset($replacements[$ref]))throw new RuntimeException('Restore the template formula before manually editing this SQL-applied cell.');if(($s['cells'][$ref]['classification']??'')!=='formula'||!range_contains($report['range'],$ref))throw new RuntimeException('Select a report formula.');
   $_SESSION['baseUnlocked'][$scope][$ref]=true;echo json_encode(['unlocked'=>$ref]);exit;
  }
  foreach($formulas as $ref=>$formula)if(($draft['formulas'][$ref]??null)!==$formula&&empty($_SESSION['baseUnlocked'][$scope][$ref]))throw new RuntimeException('Confirm formula unlocking before editing '.$ref);
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
 $_SESSION['baseReportMonth']=$month;$monthLabel=base_report_month_label($month);
}catch(Throwable $e){
 http_response_code($e instanceof QuerySettingsError&&$e->getCode()===503?503:400);
 $message=!empty($loading)&&!($e instanceof QuerySettingsError)?'The report could not be loaded. Please try again.':$e->getMessage();
 if($_SERVER['REQUEST_METHOD']==='POST'){header('Content-Type: application/json');echo json_encode(['error'=>$message]);}else echo bh($message);exit;
}
$years=array_values(array_unique(array_map(fn($period)=>substr($period,0,4),$months)));$covered=[];$merges=[];foreach($s['merges'] as $merge){[$a,$b]=explode(':',$merge);[$ac,$ar]=cell_coordinates($a);[$bc,$br]=cell_coordinates($b);if(!range_contains($report['range'],$a)||!range_contains($report['range'],$b))continue;$merges[$a]=[$bc-$ac+1,$br-$ar+1];for($r=$ar;$r<=$br;$r++)for($c=$ac;$c<=$bc;$c++)if(col_name($c).$r!==$a)$covered[col_name($c).$r]=true;}
$totalStart=$report['lastCol']-2;
$widths=[];for($c=1;$c<=$report['lastCol'];$c++)$widths[$c]=$c===1?210:($c>=$totalStart?126:94);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PALECO GenStat — KWH report</title><link rel="stylesheet" href="assets/tailwind.css"><link rel="stylesheet" href="assets/base-report.css"></head><body>
<header class="flex items-center border-b border-slate-200 bg-white"><strong>PALECO <span>GenStat</span></strong><label class="report-month">Year <select id="report-year"><?php foreach($years as $year):?><option value="<?=bh($year)?>" <?=$year===substr($month,0,4)?'selected':''?>><?=bh($year)?></option><?php endforeach;?></select></label>
<label class="report-month">Month <select id="report-month"><?php foreach(array_reverse($months) as $option):if(substr($option,0,4)!==substr($month,0,4))continue;?><option value="<?=bh($option)?>" <?=$option===$month?'selected':''?>><?=bh(strtok(base_report_month_label($option),' '))?></option><?php endforeach;?></select></label>
<span id="report-period" aria-live="polite"><?=bh($monthLabel)?></span><div class="report-toolbar ml-auto flex shrink-0 items-center gap-1" role="group" aria-label="Report actions">
 <button id="total-group-toggle" type="button" class="report-icon inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors duration-150" aria-expanded="false" aria-controls="genstat-report-table" aria-label="Show Totals" data-tooltip="Show Totals"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M15 5v14"/><path class="total-chevron" d="m8 9 3 3-3 3"/></svg></button>
 <button id="report-export" type="button" class="report-icon inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors duration-150" aria-label="Export Excel" data-tooltip="Export Excel"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8zm0 0v6h6m-8 3v6m-3-3 3 3 3-3"/></svg></button>
 <button id="save" type="button" class="report-icon inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors duration-150" aria-label="Save Draft" data-tooltip="Save Draft"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 3h13l4 4v13a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1zm3 0v6h9V3m1 18v-8H7v8"/></svg><span class="save-spinner" aria-hidden="true"></span></button>
 <button id="sql-settings-open" type="button" class="report-icon inline-flex h-8 w-8 items-center justify-center rounded-md transition-colors duration-150" aria-label="SQL Query Settings" data-tooltip="Settings"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 3-1 3-3 1-2 3 2 2-1 4 3 1 2 3 3-1 3 1 2-3 3-1-1-4 2-2-2-3-3-1-1-3Z"/><circle cx="12" cy="12" r="3"/></svg></button>
</div></header>
<div class="formula-bar"><output id="selection">Select a cell</output><input id="formula" aria-label="Selected cell formula or value" readonly><button id="unlock" disabled>Unlock formula</button><button id="restore-sql" disabled title="Restore the original formula from the template">Restore template formula</button></div>
<main class="report-scroll" tabindex="0" aria-label="Scrollable KWH report"><table id="genstat-report-table" data-total-collapsed="true" aria-label="<?=bh($monthLabel)?> KWH purchased, sold and system losses"><colgroup><?php foreach($widths as $column=>$w):?><col data-column="<?=col_name($column)?>" <?=$column>=$totalStart?'data-total-column':''?> style="width:<?=$w?>px"><?php endforeach;?></colgroup>
<?php for($r=$report['firstRow'];$r<=$report['lastRow'];$r++):if($r===$report['firstRow'])echo '<thead>';if($r===$report['firstRow']+2)echo '</thead><tbody>';$description=$s['cells']['A'.$r]['value']??'';$hidden=base_report_row_hidden($description);$spacer=base_report_row_spacer($s,$report,$r);?><tr data-excel-row="<?=$r?>" <?=$hidden?'hidden':''?> class="<?=$spacer?'spacer-row ':''?><?=preg_match('/^(Total |Dist\. System Loss|System Loss in KWH|Rate of )/i',trim($description))?'total-row':''?>">
<?php for($c=1;$c<=$report['lastCol'];$c++):$ref=col_name($c).$r;if(isset($covered[$ref]))continue;$cell=$s['cells'][$ref]??worksheet_cell($s,$ref);$css=cell_css($l,$cell['style']);if($css['text-align']==='general')$css['text-align']=is_numeric($cell['value'])?'right':'left';$css['vertical-align']=match($css['align-items']){'center'=>'middle','flex-start'=>'top',default=>'bottom'};unset($css['align-items']);$tag=$r<$report['firstRow']+2?'th':'td';$span=$merges[$ref]??[1,1];
if($span!==[1,1]){foreach(['right'=>col_name($c+$span[0]-1).$r,'bottom'=>col_name($c).($r+$span[1]-1)] as $side=>$edge){$edgeCss=cell_css($l,$s['cells'][$edge]['style']);if(isset($edgeCss['border-'.$side]))$css['border-'.$side]=$edgeCss['border-'.$side];}}

?><<?=$tag?> data-ref="<?=$ref?>" data-classification="<?=bh($cell['classification'])?>" tabindex="0" aria-readonly="<?=$cell['editable']?'false':'true'?>" colspan="<?=$span[0]?>" rowspan="<?=$span[1]?>" class="<?=$c===1?'particulars ':''?><?=$c>=$totalStart?'total-column ':''?><?=$r<$report['firstRow']+2?'header-cell':''?>" style="<?=bh(css_text($css))?>" <?=$c===1?'aria-label="'.bh($cell['value']).'"':''?> title="<?=bh($ref.' · '.$cell['classification'].($c===1?' · '.$cell['value']:'').($cell['formula']!==null?' · '.(expanded_formulas($s)[$ref]??$cell['formula']):''))?>">

<?php if($cell['editable']):?><input <?=array_key_exists($ref,$total)?'readonly':''?> aria-label="<?=bh($ref.' '.$cell['classification'])?>" data-ref="<?=$ref?>" value="<?=bh(array_key_exists($ref,$total)?($total[$ref]??''):($draft['values'][$ref]??($month!==BASE_REPORT_LEGACY_MONTH&&is_numeric($cell['value'])?'':$cell['value'])))?>"><span class="value"><?=bh($calc['cells'][$ref]['display']??'')?></span><?php else:?><span class="value"><?=bh($calc['cells'][$ref]['display']??'')?></span><?php endif;?></<?=$tag?>>
<?php endfor;?></tr><?php endfor;?></tbody></table></main>
<div class="report-notice flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t border-slate-200 bg-slate-50 px-3 py-1 text-xs text-slate-600">
 <div class="report-draft flex flex-wrap items-center gap-2"><span id="status" role="status"><?=$draft['revision']>0?'Draft loaded':'No saved report data.'?><?=in_array(null,$total,true)?' Total KWH Sold is unavailable.':''?></span><button id="report-retry" type="button" hidden>Retry</button></div>
 <details id="report-notes"><summary>Workbook notes · <span id="report-warning-count"><?=count($calc['warnings'])?></span> calculation warnings<span id="report-error-count" hidden></span> · Input legend</summary>
  <div class="report-notes-content"><p>Blue designated inputs can receive SQL values through SQL Query Settings. Formula cells are protected; selecting a cell shows its classification. Blue input indicators are retained. Total KWH Sold uses the saved SQL configuration. Test Query validates changes before Apply &amp; Save.</p><ul id="warnings"></ul></div>
 </details>
</div>
<footer><?=bh($s['name'].'!'.$report['range'])?> · <span id="fit-status">Compact view</span> · <span id="cell-kind">Formula cells protected</span></footer>
<div id="report-loading-overlay" class="fixed inset-0 flex items-center justify-center bg-black/60 backdrop-blur-sm" role="status" aria-live="polite" hidden>
 <div class="report-loading-card flex flex-col items-center rounded-2xl text-center shadow-2xl">
  <div class="report-loading-spinner animate-spin" aria-hidden="true"></div>
  <h2 class="text-lg font-semibold">Loading GENSTAT Report</h2>
  <p id="loading-period"></p>
 </div>
</div>
<?php require __DIR__.'/partials/sql-settings.php';?>
<script type="application/json" id="report-data"><?=json_encode(['total'=>(object)$total,'sqlTargets'=>(object)$reportSql['sqlTargets'],'sqlSources'=>(object)qs_verified_value_sources($l,$draft['values'],$draft['sqlSources']),'previewToken'=>qs_period_preview_token($l,[],$total),'periods'=>$months,'csrf'=>base_report_csrf($month),'month'=>$month,'hash'=>$l['sha256'],'revision'=>$draft['revision'],'values'=>(object)$draft['values'],'formulas'=>(object)$draft['formulas'],'sqlReplacements'=>(object)$draft['sqlReplacements'],'metadata'=>array_map(fn($c)=>$c['metadata'],$s['cells']),'warnings'=>$calc['warnings'],'errors'=>$calc['errors']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script><script src="assets/base-report.js"></script><script src="assets/sql-settings.js"></script></body></html>
