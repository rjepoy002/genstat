<?php
declare(strict_types=1);
require_once __DIR__.'/recovered-sales-export.php';

function base_report_export(array $layout,array $values,array $formulas,array $replacements,array $calculation,array $total=[]): string {
 $total=base_sql_manual_effective($layout,$total);
 $source=__DIR__.'/../excel-files/reference/GENSTAT_BASE.xlsx';
 $zip=zip_read($source);
 if(hash_file('sha256',$source)!==$layout['sha256'])throw new RuntimeException('The reference workbook changed. Reload before exporting.');
 $sheet=$layout['sheets'][0];$part=$sheet['path'];
 $doc=xml_doc(zip_text($zip,$part));$xpath=xml_path($doc);$ns=$doc->documentElement->namespaceURI;
 $cells=[];$rows=[];
 foreach($xpath->query('//s:sheetData/s:row') as $row){
  $rows[$row->getAttribute('r')]=$row;
  foreach($xpath->query('s:c',$row) as $node)$cells[$node->getAttribute('r')]=$node;
 }
 $recovered=(array)($calculation['recoveredSales']??[]);
 $recoveryState=$layout['recoveryState']??base_report_recovery_state($layout);
 $recoveryTargets=$recovered?recov_targets($layout):[];
 $styles=xml_doc(zip_text($zip,'xl/styles.xml'));
 $styleIndices=[0];
 foreach($recoveryTargets as $ref=>$area)$styleIndices[]=$sheet['cells'][$ref]['style'];
 $decimalStyles=recov_decimal_styles($styles,$styleIndices);
 $lastRecoveryRow=max(2,count($recoveryState['records'])+1);
 $edits=$recovered+$total+$formulas+$replacements;$groups=[];
 foreach($edits as $ref=>$unused){
  $attributes=$sheet['cells'][$ref]['formulaAttributes']??[];
  if(($attributes['t']??null)==='shared')$groups[$attributes['si']]=true;
 }
 $expanded=expanded_formulas($sheet);
 foreach($cells as $ref=>$node){
  $attributes=$sheet['cells'][$ref]['formulaAttributes']??[];
  if(($attributes['t']??null)==='shared'&&isset($groups[$attributes['si']])){
   $formula=$xpath->query('s:f',$node)->item(0);
   while($formula->attributes->length)$formula->removeAttributeNode($formula->attributes->item(0));
   $formula->textContent=substr($expanded[$ref],1);
  }
 }
 $month=base_report_month($layout);
 foreach($calculation['cells'] as $ref=>$result){
  $model=$sheet['cells'][$ref];$node=$cells[$ref]??null;
  if((isset($values[$ref])||array_key_exists($ref,$total)||array_key_exists($ref,$recovered))&&!$node){
   [$column,$number]=cell_coordinates($ref);
   $row=$rows[$number]??null;
   if(!$row)throw new RuntimeException('Workbook is missing report row '.$number.'.');
   $node=$doc->createElementNS($ns,'c');$node->setAttribute('r',$ref);$node->setAttribute('s',(string)$model['style']);
   $next=null;foreach($xpath->query('s:c',$row) as $existing)if(cell_coordinates($existing->getAttribute('r'))[0]>$column){$next=$existing;break;}
   $row->insertBefore($node,$next);
  }
  if(!$node)continue;
  if(array_key_exists($ref,$recovered)){
   $node->setAttribute('s',(string)$decimalStyles[$model['style']]);
   write_cell_value($doc,$node,'');
   $area='"'.str_replace('"','""',$recoveryTargets[$ref]).'"';
   $monthRange='RecovBill_Data!$B$2:$B$'.$lastRecoveryRow;
   $areaRange='RecovBill_Data!$C$2:$C$'.$lastRecoveryRow;
   $usedRange='RecovBill_Data!$D$2:$D$'.$lastRecoveryRow;
   $criteria=$monthRange.',"'.$month.'",'.$areaRange.','.$area;
   $formula='IF(COUNTIFS('.$criteria.')=0,"",SUMIFS('.$usedRange.','.$criteria.'))';
   $node->appendChild($doc->createElementNS($ns,'f'))->textContent=$formula;
   if($recovered[$ref]===null)$node->setAttribute('t','str');
   $node->appendChild($doc->createElementNS($ns,'v',$recovered[$ref]??''));
   continue;
  }
  if(array_key_exists($ref,$total)){
   write_cell_value($doc,$node,$total[$ref]??'#N/A');
   if($total[$ref]===null){
    $node->setAttribute('t','e');$node->removeChild($node->lastChild);
    $node->appendChild($doc->createElementNS($ns,'v','#N/A'));
   }
   continue;
  }
  if(isset($replacements[$ref])){write_cell_value($doc,$node,$replacements[$ref]['value']);continue;}
  if(isset($formulas[$ref])&&!str_starts_with($formulas[$ref],'=')){write_cell_value($doc,$node,$formulas[$ref]);continue;}
  if(isset($values[$ref])){write_cell_value($doc,$node,$values[$ref]);continue;}
  if($model['classification']!=='formula'){
   if($month!==BASE_REPORT_LEGACY_MONTH&&$result['value']===null&&is_numeric($model['value']))write_cell_value($doc,$node,'');
   continue;
  }
  $formula=$xpath->query('s:f',$node)->item(0);
  if(isset($formulas[$ref]))$formula->textContent=substr($formulas[$ref],1);
  if(!$formula)continue;
  foreach($xpath->query('s:v|s:is',$node) as $cached)$node->removeChild($cached);
  $node->removeAttribute('t');$value=$result['value'];
  if($value===null)continue;
  $cache=$doc->createElementNS($ns,'v');
  if($result['error'])$node->setAttribute('t','e');
  elseif(is_bool($value))$node->setAttribute('t','b');
  elseif(is_string($value))$node->setAttribute('t','str');
  $cache->textContent=is_bool($value)?($value?'1':'0'):(is_float($value)?sprintf('%.17g',$value):(string)$value);
  $node->appendChild($cache);
 }
 foreach($rows as $number=>$row)if(base_report_row_hidden($sheet['cells']['A'.$number]['value']??''))$row->setAttribute('hidden','1');
 if(isset($cells['A6']))write_cell_value($doc,$cells['A6'],'For the Month of '.base_report_month_label($month));
 $workbook=xml_doc(zip_text($zip,'xl/workbook.xml'));$calc=xml_path($workbook)->query('//s:calcPr')->item(0);
 if($calc&&$month!==BASE_REPORT_LEGACY_MONTH){
  // Keep the exported period's computed values until the user explicitly
  // recalculates the original template expressions in Excel.
  $calc->setAttribute('calcMode','manual');$calc->setAttribute('fullCalcOnLoad','0');$calc->setAttribute('forceFullCalc','0');$calc->setAttribute('calcOnSave','0');
 }
 $changes=[$part=>$doc->saveXML(),'xl/workbook.xml'=>$workbook->saveXML(),'xl/styles.xml'=>$styles->saveXML()];
 if($edits)xlsx_remove_calc_chain($zip,$changes);
 foreach(['xl/_rels/workbook.xml.rels','[Content_Types].xml','xl/styles.xml'] as $packagePart)$changes[$packagePart]??=zip_text($zip,$packagePart);
 recov_export_sheets($changes,dirname(__DIR__),$recoveryState['records'],$decimalStyles[0]);
 return zip_write($zip,$changes);
}
