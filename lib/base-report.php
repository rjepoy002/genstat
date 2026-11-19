<?php
declare(strict_types=1);
require_once __DIR__.'/../tools/build-layout.php';
require_once __DIR__.'/render.php';
require_once __DIR__.'/formula-engine.php';
require_once __DIR__.'/sql-replacements.php';

function base_report_draft_path(array $layout): string {
 return __DIR__.'/../storage/base-report-'.base_report_scope($layout).'.json';
}
function base_report_read_draft(array $layout): array {
 $path=base_report_draft_path($layout);
 // Keep the original July file as a fallback until the first confirmed save.
 if(!is_file($path)&&base_report_month($layout)===BASE_REPORT_LEGACY_MONTH)
  $path=__DIR__.'/../storage/base-report-'.$layout['sha256'].'.json';
 $draft=is_file($path)?json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR):['values'=>[],'formulas'=>[],'revision'=>0];
 if(isset($draft['reportMonth'])&&$draft['reportMonth']!==base_report_month($layout))throw new RuntimeException('The saved draft belongs to a different reporting month.');
 $draft['sqlReplacements']??=[];$draft['sqlSources']??=[];return $draft;
}

// Independent source/draft namespace: annual synchronization never writes this template.
function base_report_layout(): array {
 $l=build_layout(__DIR__.'/../excel-files/reference/GENSTAT_BASE.xlsx');
 $s=$l['sheets'][0];
 if($s['name']!=='Jul26')throw new RuntimeException('Expected the authoritative Jul26 worksheet.');
 $labels=[];foreach($s['cells'] as $ref=>$cell)if(preg_match('/^A(\d+)$/',$ref,$m))$labels[(int)$m[1]]=trim($cell['value']);
 $start=array_search('Particulars',$labels,true);$end=array_search('Rate of System Loss -ADJ',$labels,true);
 $last=null;foreach($s['cells'] as $ref=>$cell)if($cell['value']==='Last Month'&&cell_coordinates($ref)[1]===$start+1)$last=cell_coordinates($ref)[0];
 if(!$start||!$end||!$last)throw new RuntimeException('Reporting boundaries cannot be identified in the source workbook.');
 $l['report']=['firstRow'=>$start,'lastRow'=>$end,'lastCol'=>$last,'range'=>'A'.$start.':'.col_name($last).$end];
 $formulas=expanded_formulas($s);$dbRows=[];
 // These exact source labels designate input rows, corroborated by source blue fills.
 foreach($labels as $r=>$label)if(in_array($label,['a.)  NPC/PPGI/DELTA P/DMCI','Total KWH Sold','d.)  Disconnected Consumers','e.) Bill Adjustment (Previous months)','f.) Coop Consumption','TX/SS Losses In KWH-Grid'],true)){
  $bg=strtoupper(cell_css($l,$s['cells']['A'.$r]['style'])['background-color']??'');
  if($bg==='#00B0F0')$dbRows[$r]=true;
 }
 // Materialize implicit display blanks for complete metadata and consistent protection.
 for($r=$start;$r<=$end;$r++)for($c=1;$c<=$last;$c++){$ref=col_name($c).$r;if(!isset($s['cells'][$ref]))$s['cells'][$ref]=worksheet_cell($s,$ref);}
 foreach($s['cells'] as $ref=>&$cell){
  [$c,$r]=cell_coordinates($ref);$owner=merged_owner($s['merges'],$ref);
  $visible=$r>=$start&&$r<=$end&&$c<=$last;$entry=$visible&&$r>$start+1&&$c>=2&&($owner===null||$owner===$ref);
  $formula=isset($formulas[$ref]);$bg=strtoupper(cell_css($l,$cell['style'])['background-color']??'');
  // Area input slots only for label-designated rows; totals and historical comparisons
  // are not inferred from the label. Individually blue numeric cells also qualify.
  $db=$entry&&!$formula&&(($c<=16&&isset($dbRows[$r]))||in_array($bg,['#00B0F0','#4BACC6'],true));
  $editable=$entry&&!$formula&&($db||is_numeric($cell['value']));
  $cell['classification']=$formula?'formula':($db?'database_input':($editable?'manual':($cell['value']===''?'blank':'static')));
  $cell['editable']=$editable;$cell['databaseInput']=$db;
  $cell['metadata']=['worksheet'=>$s['name'],'reference'=>$ref,'row'=>$r,'column'=>$c,'originalValue'=>$cell['value'],'originalFormula'=>$cell['formula'],'effectiveFormula'=>$formulas[$ref]??null,'format'=>cell_number_format($cell,$l),'style'=>$cell['style'],'classification'=>$cell['classification'],'editable'=>$editable,'databaseInput'=>$db];
 }unset($cell);
 $l['sheets'][0]=$s;return $l;
}
function base_report_validate(array $l,array $values,array $formulas=[]): void {
 foreach($values as $ref=>$value){$c=$l['sheets'][0]['cells'][$ref]??null;if(!$c||!$c['editable'])throw new InvalidArgumentException('Protected input: '.$ref);if(!is_string($value)||strlen($value)>32767||str_starts_with($value,'=')||str_contains($value,"\0")||(is_numeric($value)&&!is_finite((float)$value)))throw new InvalidArgumentException('Invalid value: '.$ref);}
 $engine=new FormulaEngine($l);
 foreach($formulas as $ref=>$formula){$c=$l['sheets'][0]['cells'][$ref]??null;if(!$c||$c['classification']!=='formula'||!range_contains($l['report']['range'],$ref))throw new InvalidArgumentException('Unknown formula: '.$ref);if(!is_string($formula))throw new InvalidArgumentException('Invalid formula.');if(str_starts_with($formula,'='))$engine->validate($formula,$l['sheets'][0]['name']);elseif(!is_numeric($formula)||!is_finite((float)$formula)||strlen($formula)>32767)throw new InvalidArgumentException('Enter a number or supported Excel formula.');}
}
function base_report_calculate(array $l,array $values=[],array $formulas=[],array $replacements=[],array $total=[]): array {
 base_report_validate($l,$values,$formulas);sql_replacement_validate($l,$replacements,$formulas);$s=$l['sheets'][0];
 $sql=$replacements;
 foreach($total as $ref=>$value){$sql[$ref]=['value'=>$value??'#N/A'];unset($values[$ref],$formulas[$ref]);}
 $working=sql_replacement_working_layout($l,$sql);
 foreach($total as $ref=>$value)if($value===null)$working['sheets'][0]['cells'][$ref]['type']='e';
 if(base_report_month($l)!==BASE_REPORT_LEGACY_MONTH){
  foreach($working['sheets'][0]['cells'] as $ref=>&$cell){
   $meta=$cell['metadata'];
   if($meta['column']<2||$meta['row']<=$l['report']['firstRow']+1||!range_contains($l['report']['range'],$ref)||isset($sql[$ref]))continue;
   $formula=$meta['effectiveFormula'];
   if($formula!==null||is_numeric($cell['value'])){$cell['value']='';$cell['type']='';}
   // The workbook has two literal-only July formulas. Their expressions stay
   // available for inspection and export, but their July values are not inputs.
   if($formula!==null&&preg_match('/^=[\d.()+*\/^% -]+$/D',$formula)&&!isset($formulas[$ref])){
    $cell['formula']=null;$cell['formulaAttributes']=null;
   }
  }unset($cell);
 }
 $engine=new FormulaEngine($working,$values,$formulas);$out=[];
 foreach($s['cells'] as $ref=>$cell){if(!range_contains($l['report']['range'],$ref))continue;
  try{$value=$engine->value($s['name'],$ref);$error=null;}catch(FormulaError $e){$value=$e->getMessage();$error=$value;}
  $display=$cell;$display['value']=is_bool($value)?($value?'1':'0'):($value===null?'':(is_float($value)?sprintf('%.17g',$value):(string)$value));
  if(isset($values[$ref])||array_key_exists($ref,$total)||$cell['classification']==='formula')$display['type']=$error?'e':(is_numeric($value)?'n':'str');
  $out[$ref]=['value'=>$value,'display'=>display_value($display,$l),'error'=>$error];
 }
 $warnings=[];foreach($engine->warnings as $key=>$message){$ref=substr($key,strpos($key,'!')+1);$warnings[]=['cell'=>$key,'formula'=>expanded_formulas($s)[$ref]??null,'message'=>$message];}
 return ['cells'=>$out,'warnings'=>$warnings,'errors'=>$engine->errors];
}

function base_report_formula_editable(array $layout,string $ref): bool {
 $sheet=$layout['sheets'][0];
 return ($sheet['cells'][$ref]['classification']??null)==='formula'
  &&range_contains($layout['report']['range'],$ref)
  &&cell_coordinates($ref)[1]>$layout['report']['firstRow']+1
  &&sql_replacement_cell_allowed($sheet,$ref);
}
function base_report_formula_audit(array $layout): array {
 $path=base_report_draft_path($layout).'.formula-audit.json';
 if(!is_file($path))return [];
 $contents=file_get_contents($path);
 if($contents===false)throw new RuntimeException('Unable to read Formula Edit History.');
 return json_decode($contents,true,512,JSON_THROW_ON_ERROR);
}
function base_report_append_formula_audit(array $layout,int $revision,array $before,array $after,string $action): array {
 $path=base_report_draft_path($layout);
 $lock=fopen($path.'.lock','c');
 if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Unable to lock Formula Edit History.');
 try{
  if(base_report_read_draft($layout)['revision']!==$revision)throw new RuntimeException('Another window saved this report. Reload before editing.');
  $audit=base_report_formula_audit($layout);
  $sheet=$layout['sheets'][0];$source=expanded_formulas($sheet);
  foreach(array_keys($before+$after) as $ref){
   $old=$before[$ref]??$source[$ref]??null;$new=$after[$ref]??$source[$ref]??null;
   if($old===$new)continue;
   $audit[]=['period'=>base_report_month($layout),'sheet'=>$sheet['name'],'cell'=>$ref,'previousFormula'=>$old,'newFormula'=>$new,'action'=>$action,'timestamp'=>gmdate('c'),'editor'=>$_SESSION['username']??null,'revision'=>$revision];
  }
  atomic_file_write($path.'.formula-audit.json',json_bytes($audit));
  return $audit;
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
