<?php
declare(strict_types=1);
require __DIR__.'/lib/app.php';require __DIR__.'/lib/render.php';require __DIR__.'/tools/build-layout.php';
session_set_cookie_params(['httponly'=>true,'samesite'=>'Strict']);session_start();$_SESSION['csrf']??=bin2hex(random_bytes(32));
try{$l=layout(true);$s=$l['sheets'][0];$primary=true;if(isset($_GET['sheet'])){if(!is_string($_GET['sheet']))throw new InvalidArgumentException('Invalid worksheet identifier.');$matches=array_values(array_filter($l['sheets'],fn($sheet)=>$sheet['name']===$_GET['sheet']));if(!$matches)throw new InvalidArgumentException('Unknown worksheet.');$s=$matches[0];$primary=true;}}catch(Throwable $e){http_response_code(503);header('Content-Type: text/html; charset=utf-8');echo '<!doctype html><html lang="en"><meta charset="utf-8"><title>GenStat synchronization error</title><h1>Synchronization error</h1><p>'.htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8').'</p><p>Existing drafts are preserved. Correct the workbook and reload to retry.</p></html>';exit;}
[, $last]=explode(':',$s['range'].':'.$s['range']);[$maxCol,$maxRow]=cell_coordinates($last);
$defaults=['defaultColWidth'=>8.88671875,'defaultRowHeight'=>12];foreach($defaults as $key=>$value)if(preg_match('/'. $key .'="([0-9.]+)"/',$s['settings']['sheetFormatPr']??'',$match))$defaults[$key]=(float)$match[1];
function h($v): string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$widths=[];$columnStyles=[];$xx=[1=>36];for($c=1;$c<=$maxCol;$c++){$w=$defaults['defaultColWidth'];$hidden=false;$style=0;foreach($s['columns'] as $col)if($c>=(int)$col['min']&&$c<=(int)$col['max']){$w=(float)$col['width'];$hidden=($col['hidden']??'0')==='1';$style=(int)($col['style']??0);}$widths[$c]=$hidden?0:floor((256*$w+floor(128/9))/256*9);$columnStyles[$c]=$style;$xx[$c+1]=$xx[$c]+$widths[$c];}
$heights=[];$yy=[1=>24];for($r=1;$r<=$maxRow;$r++){$row=$s['rows'][$r]??[];$heights[$r]=($row['hidden']??'0')==='1'?0:(float)($row['ht']??$defaults['defaultRowHeight'])*96/72;$yy[$r+1]=$yy[$r]+$heights[$r];}
$merge=[];$covered=[];foreach($s['merges'] as $range){preg_match('/([A-Z]+)(\d+):([A-Z]+)(\d+)/',$range,$m);$c1=col_num($m[1]);$r1=(int)$m[2];$c2=col_num($m[3]);$r2=(int)$m[4];$merge[$m[1].$r1]=[$c2,$r2];for($r=$r1;$r<=$r2;$r++)for($c=$c1;$c<=$c2;$c++)if($r!==$r1||$c!==$c1)$covered[col_name($c).$r]=true;}
$totalW=$xx[$maxCol+1];$totalH=$yy[$maxRow+1];
function ui_icon(string $name): string {
 $paths=[
  'bolt'=>'M13 2 4 14h7l-1 8 10-12h-7l1-8Z',
  'open'=>'M3 7h6l2 3h10l-3 10H3V7Zm0 0V4h6l2 3h8v3',
  'save'=>'M5 3h12l4 4v14H3V3h2Zm2 0v6h10V3M7 21v-8h10v8',
  'export'=>'M12 3v12m-5-5 5 5 5-5M4 15v6h16v-6',
  'shield'=>'m12 3 9 4v6c0 4-9 8-9 8s-9-4-9-8V7l9-4Zm-4 9 3 3 5-6',
  'lock'=>'M6 11h12v10H6V11Zm3 0V7a3 3 0 0 1 6 0v4m-3 4v2',
  'info'=>'M12 8h.01M12 11v6M22 12a10 10 0 1 1-20 0 10 10 0 0 1 20 0Z',
  'sheet'=>'M5 3h14v18H5V3Zm0 6h14M5 15h14M11 3v18',
  'zoom'=>'M21 21l-6-6m2-6a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z',
  'sync'=>'M20 7v5h-5M4 17v-5h5M5 8a8 8 0 0 1 13-3l2 3M4 16l2 3a8 8 0 0 0 13-3',
  'warning'=>'m12 3 10 18H2L12 3Zm0 6v5m0 3h.01',
  'copy'=>'M9 8h12v13H9V8ZM3 16V3h12',
 ];
 return '<svg class="ui-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="'.$paths[$name].'"/></svg>';
}

?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="layout-hash" content="<?=h(hash_file('sha256',ROOT.'/config/layout.json'))?>"><meta name="template-hash" content="<?=h($l['sha256'])?>"><meta name="csrf-token" content="<?=h($_SESSION['csrf'])?>"><title>PALECO GenStat</title><link rel="stylesheet" href="assets/app.css"><script src="assets/formula-references.js" defer></script><script src="assets/reference-highlights.js" defer></script><script src="assets/app.js" defer></script></head>
<body data-sheet="<?=h($s['name'])?>" data-primary="<?=$primary?'true':'false'?>">
<script type="application/json" id="worksheet-source-formulas"><?=json_encode(expanded_formulas($s),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR)?></script>
<input id="period" type="hidden" value="<?=h(preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep)26$/',$s['name'],$m)?'2026-'.sprintf('%02d',array_search($m[1],['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep'])+1):'2026-06')?>">
<header class="compact-header">
 <div class="app-header">
  <div class="brand"><?php if(is_file(__DIR__.'/assets/images/paleco-logo.png')):?><img class="paleco-logo" src="assets/images/paleco-logo.png" alt="PALECO logo" width="38" height="38"><?php else:?><span class="brand-mark"><?=ui_icon('bolt')?></span><?php endif;?><h1>PALECO <span>GenStat</span></h1></div>
  <nav class="primary-actions" aria-label="Report actions">
   <button id="open" class="icon-button" type="button" aria-label="Open Report" title="Open Report" data-tooltip="Open Report"><?=ui_icon('open')?></button>
   <button id="save" class="icon-button" type="button" aria-label="Save Draft" title="Save Draft" data-tooltip="Save Draft"><?=ui_icon('save')?></button>
   <button id="export" class="icon-button" type="button" aria-label="Export Excel" title="Export Excel" data-tooltip="Export Excel"><?=ui_icon('export')?></button>
   <button id="formula-unlock" class="icon-button" type="button" aria-label="Unlock Formula Editing" title="Unlock Formula Editing" data-tooltip="Unlock Formula Editing"><?=ui_icon('lock')?><span id="formula-unlock-label" class="sr-only">Unlock Formula Editing</span></button>
   <details id="zoom-menu" class="zoom-menu"><summary class="icon-button" aria-label="Zoom" title="Zoom" data-tooltip="Zoom"><?=ui_icon('zoom')?></summary><label class="zoom-panel" for="zoom">Zoom<select id="zoom"><?php $zoomLevels=array_merge(range(50,200,10),[125]);sort($zoomLevels);foreach($zoomLevels as $zoomPercent):?><option value="<?=$zoomPercent/100?>" <?=$zoomPercent===70?'selected':''?>><?=$zoomPercent?>%</option><?php endforeach;?></select></label></details>
  </nav>
 </div>
 <div class="notification-strip template-update" data-state="info">
  <div class="sync-summary" role="status"><div class="sync-headline"><span id="sync-status">Syncing...</span><span id="status" role="status" aria-live="polite">Opening June 2026...</span></div><small id="sync-meta"><span id="sync-timestamp"></span><span id="draft-saved" hidden> | Draft saved · <time id="draft-saved-at"></time></span></small></div>
  <span id="formula-mode" class="access-badge" role="status">Formula editing locked</span>
  <button id="sync-now" class="icon-button" type="button" aria-label="Sync Now" title="Sync Now: save browser entries to Excel and check workbook changes" data-tooltip="Sync Now: save browser entries to Excel and check workbook changes"><?=ui_icon('sync')?></button>
  <button id="sync-reload" class="icon-button" type="button" aria-label="Reload updated workbook" title="Reload updated workbook" data-tooltip="Reload updated workbook" hidden><?=ui_icon('open')?></button>
  <details id="workbook-notes"><summary class="icon-button" aria-label="Workbook warnings and details" title="Workbook warnings and details" data-tooltip="Workbook warnings and details"><?=ui_icon('warning')?><span id="workbook-warning-count" class="warning-count" hidden></span></summary>
<div class="notes-panel"><h3>Workbook notes</h3><div id="calculation-notice" class="calculation-notice">Formula results update after saving.</div><ul id="workbook-warning-list" class="warning-list"></ul><div class="input-legend"><span class="swatch"></span><strong>Pale yellow cells</strong> accept manual entries.</div><p>Original formula cells are locked by default. Confirmed formula changes use the formula bar and are recorded in the saved draft.</p><p>Sheet <strong><?=h($s['name'])?></strong> spans <?=h($s['range'])?>. All working columns are included. Workbook print settings are preserved.</p><p>Non-formula cells, including empty cells, accept text, numbers or supported formulas. Merged covered cells and array/data-table ranges retain their restrictions.</p><p>External references, including year-specific RecovBill dependencies, are retained. Linked workbooks may be required to update cached results in Excel. January through September 2026 and RecovBill26 are available. Each worksheet saves its entries separately. June AC31 and AC38 contain source #REF! formulas; the workbook also retains the invalid defined name ok.</p></div></details>
 </div>
</header>

<div class="formula-bar"><label for="formula-text" class="formula-label"><span aria-hidden="true">fx</span><span class="sr-only">Formula or value</span></label><input id="formula-text" type="text" readonly autocomplete="off" spellcheck="false" aria-label="Formula bar" placeholder="Select a cell · Enter to apply · Esc to cancel"><span id="formula-reference-note" class="formula-reference-note" role="status" hidden></span><button id="formula-undo" class="icon-button" type="button" title="Undo pending formula change" aria-label="Undo pending formula change" disabled>&#8630;</button><span id="cell-protection" class="cell-protection" data-state="locked">No selection</span></div>
<dialog id="formula-unlock-dialog" aria-labelledby="formula-unlock-title"><form id="formula-unlock-form"><h2 id="formula-unlock-title">Formula Protected</h2><p>This cell contains a formula. Editing it may affect other calculations. Do you wish to unlock and edit this cell?</p><p id="formula-auth-error" class="dialog-message error-message" role="alert" hidden></p><div class="dialog-actions"><button id="formula-unlock-cancel" class="button button-outline" type="button">Cancel</button><button class="button button-primary" type="submit">Unlock &amp; Edit</button></div></form></dialog>
<main id="viewport" aria-label="Scrollable original Excel worksheet" tabindex="0"><div id="sheet-size" style="width:<?=$totalW*.7?>px;height:<?=$totalH*.7?>px"><div id="sheet" role="table" aria-label="<?=h($s['name'])?> worksheet" aria-rowcount="<?=$maxRow?>" aria-colcount="<?=$maxCol?>" data-width="<?=$totalW?>" data-height="<?=$totalH?>" style="width:<?=$totalW?>px;height:<?=$totalH?>px;transform:scale(.7)">
<div class="corner" style="width:36px;height:24px"></div>
<?php for($c=1;$c<=$maxCol;$c++):if(!$widths[$c])continue;?><div class="col-heading" style="left:<?=$xx[$c]?>px;top:0;width:<?=$widths[$c]?>px;height:24px"><?=col_name($c)?></div><?php endfor;?>
<?php for($r=1;$r<=$maxRow;$r++):if(!$heights[$r])continue;?><div class="row-heading" style="left:0;top:<?=$yy[$r]?>px;width:36px;height:<?=$heights[$r]?>px"><?=$r?></div>
<?php for($c=1;$c<=$maxCol;$c++):$ref=col_name($c).$r;if(isset($covered[$ref])||!$widths[$c])continue;$cell=worksheet_cell($s,$ref);[$endC,$endR]=$merge[$ref]??[$c,$r];$width=$xx[$endC+1]-$xx[$c];$height=$yy[$endR+1]-$yy[$r];$css=cell_css($l,$cell['style']);
// Merged perimeter styles can reside on the final cell of a merge.
if($endC!==$c||$endR!==$r){foreach(['right'=>col_name($endC).$r,'bottom'=>col_name($c).$endR] as $side=>$edge){if(isset($s['cells'][$edge])){$edgeCss=cell_css($l,$s['cells'][$edge]['style']);if(isset($edgeCss['border-'.$side]))$css['border-'.$side]=$edgeCss['border-'.$side];}}}
$numeric=is_numeric($cell['value'])&&!in_array($cell['type'],['s','str','inlineStr']);if($css['text-align']==='general')$css['text-align']=$numeric?'right':'left';
$span=$width;if(!$numeric&&$cell['value']!==''&&$endC===$c&&$css['white-space']==='pre'&&$css['text-align']==='left'){for($nc=$c+1;$nc<=$maxCol;$nc++){if(($s['cells'][col_name($nc).$r]['value']??'')!==''||isset($merge[col_name($nc).$r]))break;$span+=$widths[$nc];}}
$isFormula=($cell['formulaProtected']??false)||$cell['formula']!==null;$cell['editable']=$cell['editable']&&!$isFormula;
$baseTitle=$ref.(isset($s['comments'][$ref])?"\n".$s['comments'][$ref]:'');
$title=$ref.($cell['formula']!==null?' · ='.$cell['formula']:'').(isset($s['comments'][$ref])?"\n".$s['comments'][$ref]:'');
?><div role="cell" aria-colindex="<?=$c?>" aria-rowindex="<?=$r?>" <?=isset($merge[$ref])?'aria-colspan="'.($endC-$c+1).'" aria-rowspan="'.($endR-$r+1).'"':''?> class="cell <?=$cell['editable']?'editable':''?> <?=$cell['editable']&&!empty($cell['evidence'])?'manual-input':''?> <?=$isFormula?'formula':''?>" data-ref="<?=$ref?>" contenteditable="false" <?=$isFormula?'tabindex="0" aria-readonly="true"':''?> data-raw-value="<?=h($cell['value'])?>" data-base-title="<?=h($baseTitle)?>" data-formula-kind="<?=h($cell['formulaKind']??'ordinary')?>" data-formula-text="<?=h($cell['formula']??'')?>" title="<?=h($isFormula?'Read-only formula - '.$title:$title)?>" style="left:<?=$xx[$c]?>px;top:<?=$yy[$r]?>px;width:<?=$width?>px;height:<?=$height?>px;<?=h(css_text($css))?>">
<?php if($cell['editable']):?><input type="text" inputmode="text" autocomplete="off" spellcheck="false" aria-label="<?=h($s['name'])?> <?=$ref?> input" data-ref="<?=$ref?>" data-original="<?=h($cell['value'])?>" value="<?=h(numeric_editing_text($cell['value'],number_format_spec(cell_number_format($cell,$l))))?>" data-format="<?=h(json_encode(number_format_spec(cell_number_format($cell,$l)),JSON_THROW_ON_ERROR))?>" disabled><span class="input-preview" style="width:<?=max(0,$span-4)?>px;right:auto" aria-hidden="true"><?=h(display_value($cell,$l))?></span><?php else:?><span class="cell-content" style="width:<?=max(0,$span-4)?>px"><?=h(display_value($cell,$l))?></span><?php endif;?></div>
<?php endfor;endfor;?></div></div></main><footer class="worksheet-footer"><nav class="worksheet-tabs" aria-label="Worksheets"><?php foreach($l['sheets'] as $tab):?><a class="worksheet-tab" href="?sheet=<?=rawurlencode($tab['name'])?>" <?=$tab['name']===$s['name']?'aria-current="page"':''?>><?=h($tab['name'])?></a><?php endforeach;?></nav><span id="cell-info" class="footer-selection" title="<?=h($s['name'])?> · A1" aria-label="Active worksheet cell">A1</span><span class="footer-template">Original workbook layout preserved</span></footer></body></html>



