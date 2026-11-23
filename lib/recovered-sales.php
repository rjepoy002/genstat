<?php
declare(strict_types=1);
require_once __DIR__.'/../tools/build-layout.php';
require_once __DIR__.'/workbook-storage.php';

require_once __DIR__.'/report-areas.php';
const RECOV_MAX_ROWS = 1000;
const RECOV_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
const RECOV_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
const RECOV_PACKAGE_REL = 'http://schemas.openxmlformats.org/package/2006/relationships';
const RECOV_TYPES = 'http://schemas.openxmlformats.org/package/2006/content-types';
const RECOV_DATA = 'xl/worksheets/recov-data.xml';
const RECOV_AUDIT = 'xl/worksheets/recov-audit.xml';

function recov_area_columns(array $layout): array {
 // Recovered Sales uses Main through Agutaya (B:O). Rizal is outside this list.
 $headers=array_intersect_key(report_area_headers($layout),array_flip(range(2,15)));
 return array_column($headers,'column','label');
}

function recov_targets(array $layout): array {
 $sheet=$layout['sheets'][0];
 $rows=[];
 for($row=$layout['report']['firstRow']+2;$row<=$layout['report']['lastRow'];$row++){
  $label=trim($sheet['cells']['A'.$row]['value']??'');
  if(preg_match('/^b\.\)\s*Recover(?:e)?d Sales in KWH$/iD',$label))$rows[]=$row;
 }
 if(count($rows)!==1)throw new RuntimeException('Cannot identify the Recovered Sales summary row.');
 $targets=[];
 foreach(recov_area_columns($layout) as $area=>$column){
  $ref=$column.$rows[0];
  if(!isset($sheet['cells'][$ref])||merged_owner($sheet['merges'],$ref)!==null)
   throw new RuntimeException('Invalid Recovered Sales summary cell: '.$ref);
  $targets[$ref]=$area;
 }
 if(count($targets)!==14)throw new RuntimeException('Expected 14 Recovered Sales summary cells.');
 return $targets;
}

function recov_summary(array $layout,array $state,string $month): array {
 $targets=recov_targets($layout);$references=array_flip($targets);
 $totals=array_fill_keys(array_keys($targets),null);
 foreach($state['records'] as $record){
  if($record['month']!==$month||!isset($references[$record['area']]))continue;
  $ref=$references[$record['area']];
  $totals[$ref]=($totals[$ref]??0)+recov_cents($record['used']);
 }
 return array_map(fn($amount)=>$amount===null?null:recov_decimal($amount),$totals);
}


function recov_cents($value): int {
 if(!is_string($value)||!preg_match('/^(\d{1,10}|\d{1,3}(?:,\d{3}){1,3})(?:\.(\d{1,2}))?$/D',trim($value),$parts))
  throw new InvalidArgumentException('Enter a nonnegative kWh amount with up to two decimal places.');
 $whole=str_replace(',','',$parts[1]);
 if(strlen($whole)>10)throw new InvalidArgumentException('Enter at most ten digits before the decimal point.');
 return (int)$whole*100+(int)str_pad($parts[2]??'',2,'0');
}

function recov_decimal(int $value): string {
 return intdiv($value,100).'.'.str_pad((string)($value%100),2,'0',STR_PAD_LEFT);
}

function recov_record(array $entry,string $id,string $month,array $areas): array {
 $area=$entry['area']??null;
 if(!in_array($area,$areas,true))throw new InvalidArgumentException('Select an area.');
 $used=recov_cents($entry['used']??null);
 $paid=recov_cents($entry['paid']??null);
 if($paid>$used)throw new InvalidArgumentException('KWH Paid cannot exceed KWH Used.');
 $remarks=$entry['remarks']??'';
 if(!is_string($remarks)||mb_strlen($remarks)>500||!preg_match('/^[\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]*$/uD',$remarks))
  throw new InvalidArgumentException('Enter valid remarks of up to 500 characters.');
 return compact('id','month','area')+['used'=>recov_decimal($used),'paid'=>recov_decimal($paid),'remarks'=>$remarks];
}

function recov_legacy(array $zip): ?array {
 $workbook=xml_doc(zip_text($zip,'xl/workbook.xml'));
 $rels=xml_doc(zip_text($zip,'xl/_rels/workbook.xml.rels'));
 foreach(xml_path($workbook)->query('//s:sheets/s:sheet') as $sheet){
  $name=$sheet->getAttribute('name');
  if(!in_array($name,['RecovBill','RecovBill26'],true))continue;
  foreach($rels->documentElement->childNodes as $rel){
   if($rel instanceof DOMElement&&$rel->getAttribute('Id')===$sheet->getAttributeNS(RECOV_REL,'id')){
    $part=package_target('xl/workbook.xml',$rel->getAttribute('Target'));
    return [$name,$part,xml_doc(zip_text($zip,$part))];
   }
  }
  throw new RuntimeException('The original recovery worksheet relationship is missing.');
 }
 return null;
}

function recov_shared_strings(array $zip): array {
 if(!isset($zip['xl/sharedStrings.xml']))return [];
 return iterator_to_array(xml_path(xml_doc(zip_text($zip,'xl/sharedStrings.xml')))->query('//s:si'));
}

function recov_sheet_rows(DOMDocument $doc,array $shared=[]): array {
 $xpath=xml_path($doc);$rows=[];
 foreach($xpath->query('//s:sheetData/s:row') as $row){
  $values=[];
  foreach($xpath->query('s:c',$row) as $cell){
   $value=$xpath->evaluate('string(s:v)',$cell);
   $type=$cell->getAttribute('t');
   if($type==='s'||$type==='inlineStr'){
    $node=$type==='s'?($shared[(int)$value]??null):$xpath->query('s:is',$cell)->item(0);
    if(!$node)throw new RuntimeException('The recovery worksheet has an invalid text cell.');
    $value=implode('',array_map(fn($text)=>$text->textContent,iterator_to_array(xml_path($node->ownerDocument)->query('s:t|s:r/s:t',$node))));
   }
   $values[rtrim($cell->getAttribute('r'),'0123456789')]=$value;
  }
  $rows[(int)$row->getAttribute('r')]=$values;
 }
 return $rows;
}

function recov_seed(string $root,string $year,array $areas): array {
 if($year!=='2026')return [];
 $zip=zip_read(workbook_path($root));$source=recov_legacy($zip);
 if(!$source)return [];
 $month=null;$records=[];$areaNames=array_combine(array_map('report_area_name',$areas),$areas);
 foreach(recov_sheet_rows($source[2],recov_shared_strings($zip)) as $row=>$cells){
  $label=trim($cells['A']??'');
  if(preg_match('/^([A-Za-z]+)\s+(\d{4})$/D',$label,$parts)){
   $date=DateTimeImmutable::createFromFormat('!F Y',$parts[1].' '.$parts[2]);
   $month=$date?$date->format('Ym'):null;
  }
  $area=$areaNames[report_area_name($label)]??null;
  // January's Summary entry is the Main record (also under the Main column in G:L).
  if($month==='202601'&&$row===3&&strtolower($label)==='summary')$area='Main';
  if(!$month||substr($month,0,4)!==$year||!in_array($area,$areas,true)||in_array(trim($cells['B']??''),['','KWH used'],true))continue;
  $id=substr(hash('sha256',$source[0].':'.$month.':'.$row),0,32);
  $records[$id]=recov_record(['area'=>$area,'used'=>$cells['B'],'paid'=>($cells['C']??'')===''?'0':$cells['C'],'remarks'=>$cells['E']??''],$id,$month,$areas);
 }
 return $records;
}

function recov_workbook_path(string $root,string $month): string {
 if(!preg_match('/^\d{4}(?:0[1-9]|1[0-2])$/D',$month))throw new InvalidArgumentException('Invalid reporting month.');
 return $root.'/storage/genstat_'.substr($month,0,4).'_recovered_sales.xlsx';
}

function recov_state(string $root,string $month,array $areas): array {
 $path=recov_workbook_path($root,$month);
 if(!is_file($path))return ['records'=>recov_seed($root,substr($month,0,4),[...$areas,'BPSO']),'audit'=>[]];
 $zip=zip_read($path);
 $shared=recov_shared_strings($zip);
 $rows=recov_sheet_rows(xml_doc(zip_text($zip,RECOV_DATA)),$shared);
 $audit=recov_sheet_rows(xml_doc(zip_text($zip,RECOV_AUDIT)),$shared);
 $records=[];
 foreach(array_slice($rows,1) as $row){
  $id=$row['A']??'';$period=$row['B']??'';
  if(!preg_match('/^[a-f0-9]{32}$/D',$id)||isset($records[$id])||!preg_match('/^'.substr($month,0,4).'(?:0[1-9]|1[0-2])$/D',$period))
   throw new RuntimeException('The recovered sales workbook contains an invalid record ID or month.');
  $records[$id]=recov_record(['area'=>$row['C']??null,'used'=>$row['D']??null,'paid'=>$row['E']??null,'remarks'=>$row['G']??''],$id,$period,[...$areas,'BPSO']);
 }
 return ['records'=>$records,'audit'=>array_map('array_values',array_slice($audit,1))];
}

function recov_view(array $state,string $month): array {
 $records=array_filter($state['records'],fn($row)=>$row['month']===$month);
 $history=array_filter($state['audit'],fn($row)=>$row[0]===$month);
 return ['records'=>array_values($records),'revision'=>hash('sha256',json_encode([$records,count($history)],JSON_THROW_ON_ERROR))];
}

function recov_save(string $root,string $month,array $submission,string $actor,array $areas): array {
 $path=recov_workbook_path($root,$month);
 $lock=fopen($path.'.lock','c');
 if(!$lock)throw new RuntimeException('Could not open the recovered sales lock.');
 try{
  if(!flock($lock,LOCK_EX))throw new RuntimeException('Could not lock recovered sales.');
  $state=recov_state($root,$month,$areas);$view=recov_view($state,$month);
  $entries=$submission['records']??null;$deletions=$submission['deleted']??null;
  if(!is_array($entries)||!array_is_list($entries)||count($entries)>RECOV_MAX_ROWS||!is_array($deletions)||!array_is_list($deletions)||count($deletions)>RECOV_MAX_ROWS)
   throw new InvalidArgumentException('Submit up to '.RECOV_MAX_ROWS.' valid records for this month.');
  $revision=$submission['revision']??null;
  if(!is_string($revision))throw new InvalidArgumentException('Missing recovered sales revision.');
  $requestKey=hash('sha256',json_encode([$month,$actor,$revision,$entries,$deletions],JSON_THROW_ON_ERROR));
  if(!hash_equals($view['revision'],$revision)){
   foreach($state['audit'] as $event)if(($event[7]??null)===$requestKey)return $view;
   throw new RuntimeException('Recovered sales changed in another window. Reopen the dialog to load the latest records.',409);
  }
  $prior=array_column($view['records'],null,'id');$next=[];
  foreach($entries as $index=>$entry){
   if(!is_array($entry))throw new InvalidArgumentException('Invalid recovered sales record.');
   $id=$entry['id']??null;
   if($id===null)$id=bin2hex(random_bytes(16));
   elseif(!is_string($id)||!isset($prior[$id])||isset($next[$id]))throw new InvalidArgumentException('Invalid recovered sales record ID.');
   $allowedAreas=($prior[$id]['area']??null)==='BPSO'?[...$areas,'BPSO']:$areas;
   try{$next[$id]=recov_record($entry,$id,$month,$allowedAreas);}
   catch(InvalidArgumentException $e){throw new InvalidArgumentException('Row '.($index+1).'. '.$e->getMessage());}
  }
  $deleted=[];
  foreach($deletions as $id){
   if(!is_string($id)||!isset($prior[$id])||isset($deleted[$id])||isset($next[$id]))throw new InvalidArgumentException('Invalid recovered sales deletion.');
   $deleted[$id]=true;
  }
  if(array_diff_key($prior,$next+$deleted))throw new InvalidArgumentException('Explicitly delete any records you want to remove.');
  $audit=$state['audit'];
  foreach($prior+$next as $id=>$unused){
   $before=$prior[$id]??null;$after=$next[$id]??null;
   if($before===$after)continue;
   $audit[]=[$month,$id,$before===null?'create':($after===null?'delete':'update'),gmdate('c'),$actor,
    json_encode($before,JSON_THROW_ON_ERROR),json_encode($after,JSON_THROW_ON_ERROR),$requestKey];
  }
  if($audit===$state['audit'])return $view;
  $state=['records'=>array_diff_key($state['records'],$prior)+$next,'audit'=>$audit];
  $bytes=recov_storage_bytes($state);
  if(is_file($path))atomic_file_write($path.'.bak',file_get_contents($path));
  atomic_file_write($path,$bytes);
  return recov_view($state,$month);
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}

function recov_text_cell(DOMDocument $doc,DOMElement $cell,string $value): void {
 $cell->setAttribute('t','inlineStr');
 $inline=$cell->appendChild($doc->createElementNS(RECOV_NS,'is'));
 $text=$inline->appendChild($doc->createElementNS(RECOV_NS,'t'));
 $text->setAttributeNS('http://www.w3.org/XML/1998/namespace','xml:space','preserve');
 $text->textContent=$value;
}

function recov_decimal_styles(DOMDocument $styles,array $sourceIndices): array {
 $xpath=xml_path($styles);
 $xfs=$xpath->query('/s:styleSheet/s:cellXfs')->item(0);
 $formats=iterator_to_array($xpath->query('s:xf',$xfs));
 $count=count($formats);
 $result=[];
 foreach(array_unique($sourceIndices) as $index){
  $copy=$formats[$index]->cloneNode(true);
  $copy->setAttribute('numFmtId','4');
  $copy->setAttribute('applyNumberFormat','1');
  $xfs->appendChild($copy);
  $result[$index]=$count++;
 }
 $xfs->setAttribute('count',(string)$count);
 return $result;
}


function recov_sheet_xml(array $rows,?int $decimalStyle=null): string {
 $doc=xml_doc('<worksheet xmlns="'.RECOV_NS.'"><sheetData/></worksheet>');
 $sheet=$doc->documentElement->firstElementChild;
 foreach($rows as $index=>$values){
  $number=$index+1;$row=$sheet->appendChild($doc->createElementNS(RECOV_NS,'row'));
  $row->setAttribute('r',(string)$number);
  foreach($values as $column=>$value){
   $cell=$row->appendChild($doc->createElementNS(RECOV_NS,'c'));
   $cell->setAttribute('r',col_name($column+1).$number);
   if($decimalStyle!==null&&$index>0&&in_array($column,[3,4,5],true)){
    $cell->setAttribute('s',(string)$decimalStyle);
    if($column===5)$cell->appendChild($doc->createElementNS(RECOV_NS,'f','D'.$number.'-E'.$number));
    $cell->appendChild($doc->createElementNS(RECOV_NS,'v',$value));
   }else recov_text_cell($doc,$cell,$value);
  }
 }
 return $doc->saveXML();
}

function recov_data_xml(array $records,int $decimalStyle): string {
 $rows=[['RecordID','BillMonth','Area','KWH Used','KWH Paid','Remaining KWH','Remarks']];
 foreach($records as $entry){
  $remaining=recov_decimal(recov_cents($entry['used'])-recov_cents($entry['paid']));
  $rows[]=[$entry['id'],$entry['month'],$entry['area'],$entry['used'],$entry['paid'],$remaining,$entry['remarks']];
 }
 return recov_sheet_xml($rows,$decimalStyle);
}

function recov_add_sheet(array &$changes,string $name,string $part,string $xml,bool $hidden=false): void {
 $wb=xml_doc($changes['xl/workbook.xml']);
 $rels=xml_doc($changes['xl/_rels/workbook.xml.rels']);
 $types=xml_doc($changes['[Content_Types].xml']);
 $sheets=xml_path($wb)->query('//s:sheets')->item(0);
 $id=0;
 foreach($sheets->childNodes as $sheet){
  if(!$sheet instanceof DOMElement)continue;
  if(strcasecmp($sheet->getAttribute('name'),$name)===0)throw new RuntimeException('Duplicate recovery worksheet name.');
  $id=max($id,(int)$sheet->getAttribute('sheetId'));
 }
 $relationship='rIdGenstat'.($id+1);
 $sheet=$sheets->appendChild($wb->createElementNS(RECOV_NS,'sheet'));
 $sheet->setAttribute('name',$name);
 $sheet->setAttribute('sheetId',(string)($id+1));
 if($hidden)$sheet->setAttribute('state','hidden');
 $sheet->setAttributeNS(RECOV_REL,'r:id',$relationship);
 $rel=$rels->documentElement->appendChild($rels->createElementNS(RECOV_PACKAGE_REL,'Relationship'));
 $rel->setAttribute('Id',$relationship);
 $rel->setAttribute('Type',RECOV_REL.'/worksheet');
 $rel->setAttribute('Target',substr($part,3));
 $type=$types->documentElement->appendChild($types->createElementNS(RECOV_TYPES,'Override'));
 $type->setAttribute('PartName','/'.$part);
 $type->setAttribute('ContentType','application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml');
 $changes['xl/workbook.xml']=$wb->saveXML();
 $changes['xl/_rels/workbook.xml.rels']=$rels->saveXML();
 $changes['[Content_Types].xml']=$types->saveXML();
 $changes[$part]=$xml;
}

function recov_storage_bytes(array $state): string {
 $styles=xml_doc('<styleSheet xmlns="'.RECOV_NS.'">
  <fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
  <fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
  <borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
  <cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
  <cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
  <cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
 </styleSheet>');
 $decimalStyle=recov_decimal_styles($styles,[0])[0];
 $parts=[
  '_rels/.rels'=>'<Relationships xmlns="'.RECOV_PACKAGE_REL.'"><Relationship Id="rId1" Type="'.RECOV_REL.'/officeDocument" Target="xl/workbook.xml"/></Relationships>',
  'xl/workbook.xml'=>'<workbook xmlns="'.RECOV_NS.'"><sheets/></workbook>',
  'xl/_rels/workbook.xml.rels'=>'<Relationships xmlns="'.RECOV_PACKAGE_REL.'"><Relationship Id="rIdStyles" Type="'.RECOV_REL.'/styles" Target="styles.xml"/></Relationships>',
  'xl/styles.xml'=>$styles->saveXML(),
  '[Content_Types].xml'=>'<Types xmlns="'.RECOV_TYPES.'"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
 ];
 recov_add_sheet($parts,'RecovBill_Data',RECOV_DATA,recov_data_xml($state['records'],$decimalStyle));
 $audit=array_merge([['BillMonth','RecordID','Operation','Timestamp UTC','User','Previous','New','RequestKey']],$state['audit']);
 recov_add_sheet($parts,'RecovBill_Audit',RECOV_AUDIT,recov_sheet_xml($audit),true);
 return zip_write([],$parts);
}
