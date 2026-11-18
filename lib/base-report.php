<?php
declare(strict_types=1);
require_once __DIR__.'/../tools/build-layout.php';
require_once __DIR__.'/render.php';
require_once __DIR__.'/formula-engine.php';
require_once __DIR__.'/sql-replacements.php';

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
 foreach($formulas as $ref=>$formula){$c=$l['sheets'][0]['cells'][$ref]??null;if(!$c||$c['classification']!=='formula'||!range_contains($l['report']['range'],$ref))throw new InvalidArgumentException('Unknown formula: '.$ref);if(!is_string($formula))throw new InvalidArgumentException('Invalid formula.');$engine->validate($formula,$l['sheets'][0]['name']);}
}
function base_report_calculate(array $l,array $values=[],array $formulas=[],array $replacements=[]): array {
 base_report_validate($l,$values,$formulas);sql_replacement_validate($l,$replacements,$formulas);$s=$l['sheets'][0];$engine=new FormulaEngine(sql_replacement_working_layout($l,$replacements),$values,$formulas);$out=[];
 foreach($s['cells'] as $ref=>$cell){if(!range_contains($l['report']['range'],$ref))continue;
  try{$value=$engine->value($s['name'],$ref);$error=null;}catch(FormulaError $e){$value=$e->getMessage();$error=$value;}
  $display=$cell;$display['value']=is_bool($value)?($value?'1':'0'):($value===null?'':(is_float($value)?sprintf('%.17g',$value):(string)$value));
  if(isset($values[$ref])||$cell['classification']==='formula')$display['type']=$error?'e':(is_numeric($value)?'n':'str');
  $out[$ref]=['value'=>$value,'display'=>display_value($display,$l),'error'=>$error];
 }
 $warnings=[];foreach($engine->warnings as $key=>$message){$ref=substr($key,strpos($key,'!')+1);$warnings[]=['cell'=>$key,'formula'=>expanded_formulas($s)[$ref]??null,'message'=>$message];}
 return ['cells'=>$out,'warnings'=>$warnings,'errors'=>$engine->errors];
}
