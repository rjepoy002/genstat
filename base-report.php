<?php
declare(strict_types=1);
require_once __DIR__.'/lib/base-report.php';
require_once __DIR__.'/lib/files.php';
require_once __DIR__.'/lib/base-report-view.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict']);session_start();$_SESSION['baseCsrf']??=bin2hex(random_bytes(32));
function bh($v): string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
try{
 $l=base_report_layout();$s=$l['sheets'][0];$report=$l['report'];$path=__DIR__.'/storage/base-report-'.$l['sha256'].'.json';
 $draft=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):['values'=>[],'formulas'=>[],'revision'=>0];$draft['sqlReplacements']??=[];
 if($_SERVER['REQUEST_METHOD']==='POST'){
  header('Content-Type: application/json');$body=json_decode(file_get_contents('php://input'),true,512,JSON_THROW_ON_ERROR);
  if(!hash_equals($_SESSION['baseCsrf'],(string)($body['csrf']??'')))throw new RuntimeException('Invalid session token.');
  if(!hash_equals($l['sha256'],(string)($body['hash']??'')))throw new RuntimeException('Source changed. Reload before editing.');
  $values=$body['values']??[];$formulas=$body['formulas']??[];$replacements=$body['sqlReplacements']??[];base_report_validate($l,$values,$formulas);sql_replacement_validate($l,$replacements,$formulas);sql_replacement_submission($l,$replacements,$draft['sqlReplacements']);
  if(($body['action']??'')==='restore-sql'){
   $ref=$body['ref']??'';if(!isset($replacements[$ref]))throw new RuntimeException('Select an SQL-replaced formula to restore.');$_SESSION['sqlRestoreGrants'][$l['sha256']][$ref]=true;unset($_SESSION['sqlReplacementGrants'][$replacements[$ref]['signature']],$_SESSION['baseUnlocked'][$l['sha256']][$ref]);unset($replacements[$ref],$formulas[$ref]);
   echo json_encode(['sqlReplacements'=>(object)$replacements,'formulas'=>(object)$formulas,'calculation'=>base_report_calculate($l,$values,$formulas,$replacements)],JSON_THROW_ON_ERROR);exit;
  }
  // Formula overrides need an explicit, per-cell confirmation in this session.
  if(($body['action']??'')==='unlock'){
   $ref=$body['ref']??'';if(isset($replacements[$ref]))throw new RuntimeException('Restore the template formula before manually editing this SQL-applied cell.');if(($s['cells'][$ref]['classification']??'')!=='formula'||!range_contains($report['range'],$ref))throw new RuntimeException('Select a report formula.');
   $_SESSION['baseUnlocked'][$l['sha256']][$ref]=true;echo json_encode(['unlocked'=>$ref]);exit;
  }
  foreach($formulas as $ref=>$formula)if(($draft['formulas'][$ref]??null)!==$formula&&empty($_SESSION['baseUnlocked'][$l['sha256']][$ref]))throw new RuntimeException('Confirm formula unlocking before editing '.$ref);
  $result=base_report_calculate($l,$values,$formulas,$replacements);
  if(($body['action']??'')==='save'){
   $lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Cannot lock draft.');
   try{$latest=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):['revision'=>0];if(($body['revision']??-1)!==$latest['revision'])throw new RuntimeException('Another window saved this report. Reload before saving.');
    sql_replacement_submission($l,$replacements,$latest['sqlReplacements']??[]);$history=$latest['sqlHistory']??[];
    foreach(array_unique(array_merge(array_keys($latest['sqlReplacements']??[]),array_keys($replacements))) as $ref){$old=$latest['sqlReplacements'][$ref]??null;$new=$replacements[$ref]??null;if(($old['signature']??null)!==($new['signature']??null))$history[]=['cell'=>$ref,'action'=>$new?'SQL formula replacement':'Restore template formula','previous'=>$old,'replacement'=>$new,'at'=>gmdate('c'),'revision'=>$latest['revision']+1];}
    $draft=array_replace($latest,['values'=>$values,'formulas'=>$formulas,'sqlReplacements'=>(object)$replacements,'sqlHistory'=>$history,'revision'=>$latest['revision']+1,'sourceHash'=>$l['sha256'],'savedAt'=>gmdate('c')]);atomic_file_write($path,json_bytes($draft));
    foreach(array_merge((array)($latest['sqlReplacements']??[]),$replacements) as $record)unset($_SESSION['sqlReplacementGrants'][$record['signature']]);unset($_SESSION['sqlRestoreGrants'][$l['sha256']]);
   }finally{flock($lock,LOCK_UN);fclose($lock);}
  }
  echo json_encode($result+['revision'=>$draft['revision']],JSON_THROW_ON_ERROR);exit;
 }
 base_report_validate($l,$draft['values'],$draft['formulas']);$calc=base_report_calculate($l,$draft['values'],$draft['formulas'],(array)$draft['sqlReplacements']);
}catch(Throwable $e){http_response_code(400);if($_SERVER['REQUEST_METHOD']==='POST'){header('Content-Type: application/json');echo json_encode(['error'=>$e->getMessage()]);}else echo bh($e->getMessage());exit;}
$covered=[];$merges=[];foreach($s['merges'] as $merge){[$a,$b]=explode(':',$merge);[$ac,$ar]=cell_coordinates($a);[$bc,$br]=cell_coordinates($b);if(!range_contains($report['range'],$a)||!range_contains($report['range'],$b))continue;$merges[$a]=[$bc-$ac+1,$br-$ar+1];for($r=$ar;$r<=$br;$r++)for($c=$ac;$c<=$bc;$c++)if(col_name($c).$r!==$a)$covered[col_name($c).$r]=true;}
$widths=[];for($c=1;$c<=$report['lastCol'];$c++)$widths[$c]=$c===1?210:($c>=17?126:94);
?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>PALECO GenStat — KWH report</title><link rel="stylesheet" href="assets/tailwind.css"><link rel="stylesheet" href="assets/base-report.css"></head><body>
<header class="flex items-center border-b border-slate-200 bg-white"><strong>PALECO <span>GenStat</span></strong><span><?=bh($s['cells']['A6']['value'])?></span><nav class="text-xs text-slate-500" aria-label="Current report worksheet"><?=bh($s['name'])?></nav><button id="save" type="button">Save Draft</button><button id="sql-settings-open" type="button" class="ml-auto flex items-center justify-center" aria-label="SQL Query Settings" title="SQL Query Settings"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="m9 3-1 3-3 1-2 3 2 2-1 4 3 2 3-1 2 4h3l1-3 3-1 2-3-2-2 1-4-3-2-3 1-2-4Z"/><circle cx="12" cy="12" r="3"/></svg></button></header>
<div class="formula-bar"><output id="selection">Select a cell</output><input id="formula" aria-label="Selected cell formula or value" readonly><button id="unlock" disabled>Unlock formula</button><button id="restore-sql" disabled title="Restore the original formula from the template">Restore template formula</button></div>
<div class="report-notice"><span id="status" role="status">Workbook values loaded</span><details><summary>Workbook notes · <?=count($calc['warnings'])?> calculation warnings · input legend</summary><p>Blue designated inputs can receive reviewed query values through SQL Query Settings. Formula cells are protected; selecting a cell shows its classification. Blue input indicators are retained. Queries never run automatically.</p><ul id="warnings"></ul></details></div>
<main class="report-scroll" tabindex="0" aria-label="Scrollable KWH report"><table aria-label="Jul26 KWH purchased, sold and system losses"><colgroup><?php foreach($widths as $w):?><col style="width:<?=$w?>px"><?php endforeach;?></colgroup>
<?php for($r=$report['firstRow'];$r<=$report['lastRow'];$r++):if($r===$report['firstRow'])echo '<thead>';if($r===$report['firstRow']+2)echo '</thead><tbody>';$description=$s['cells']['A'.$r]['value']??'';$hidden=base_report_row_hidden($description);$spacer=base_report_row_spacer($s,$report,$r);?><tr data-excel-row="<?=$r?>" <?=$hidden?'hidden':''?> class="<?=$spacer?'spacer-row ':''?><?=preg_match('/^(Total |Dist\. System Loss|System Loss in KWH|Rate of )/i',trim($description))?'total-row':''?>">
<?php for($c=1;$c<=$report['lastCol'];$c++):$ref=col_name($c).$r;if(isset($covered[$ref]))continue;$cell=$s['cells'][$ref]??worksheet_cell($s,$ref);$css=cell_css($l,$cell['style']);if($css['text-align']==='general')$css['text-align']=is_numeric($cell['value'])?'right':'left';$css['vertical-align']=match($css['align-items']){'center'=>'middle','flex-start'=>'top',default=>'bottom'};unset($css['align-items']);$tag=$r<$report['firstRow']+2?'th':'td';$span=$merges[$ref]??[1,1];
if($span!==[1,1]){foreach(['right'=>col_name($c+$span[0]-1).$r,'bottom'=>col_name($c).($r+$span[1]-1)] as $side=>$edge){$edgeCss=cell_css($l,$s['cells'][$edge]['style']);if(isset($edgeCss['border-'.$side]))$css['border-'.$side]=$edgeCss['border-'.$side];}}
?><<?=$tag?> data-ref="<?=$ref?>" data-classification="<?=bh($cell['classification'])?>" tabindex="0" aria-readonly="<?=$cell['editable']?'false':'true'?>" colspan="<?=$span[0]?>" rowspan="<?=$span[1]?>" class="<?=$c===1?'particulars ':''?><?=$c>=17?'total-column ':''?><?=$r<$report['firstRow']+2?'header-cell':''?>" style="<?=bh(css_text($css))?>" <?=$c===1?'aria-label="'.bh($cell['value']).'"':''?> title="<?=bh($ref.' · '.$cell['classification'].($c===1?' · '.$cell['value']:'').($cell['formula']!==null?' · '.(expanded_formulas($s)[$ref]??$cell['formula']):''))?>">
<?php if($cell['editable']):?><input aria-label="<?=bh($ref.' '.$cell['classification'])?>" data-ref="<?=$ref?>" value="<?=bh($draft['values'][$ref]??$cell['value'])?>"><span class="value"><?=bh($calc['cells'][$ref]['display']??'')?></span><?php else:?><span class="value"><?=bh($calc['cells'][$ref]['display']??'')?></span><?php endif;?></<?=$tag?>>
<?php endfor;?></tr><?php endfor;?></tbody></table></main>
<footer><?=bh($s['name'].'!'.$report['range'])?> · <span id="fit-status">Compact view</span> · <span id="cell-kind">Formula cells protected</span></footer>
<?php require __DIR__.'/partials/sql-settings.php';?>
<script type="application/json" id="report-data"><?=json_encode(['csrf'=>$_SESSION['baseCsrf'],'hash'=>$l['sha256'],'revision'=>$draft['revision'],'values'=>(object)$draft['values'],'formulas'=>(object)$draft['formulas'],'sqlReplacements'=>(object)$draft['sqlReplacements'],'metadata'=>array_map(fn($c)=>$c['metadata'],$s['cells']),'warnings'=>$calc['warnings'],'errors'=>$calc['errors']],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script><script src="assets/base-report.js"></script><script src="assets/sql-settings.js"></script></body></html>
