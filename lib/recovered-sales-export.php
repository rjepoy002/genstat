<?php
declare(strict_types=1);
require_once __DIR__.'/recovered-sales.php';

function recov_export_styles(string $report,string $legacy): array {
 $target=xml_doc($report);$source=xml_doc($legacy);
 $tx=xml_path($target);$sx=xml_path($source);$maps=[];
 $groups=['numFmts'=>'numFmtId','fonts'=>'fontId','fills'=>'fillId','borders'=>'borderId','cellStyleXfs'=>'xfId','cellXfs'=>'style'];
 foreach($groups as $group=>$attribute){
  $from=$sx->query('/s:styleSheet/s:'.$group.'/*');
  if(!$from->length)continue;
  $to=$tx->query('/s:styleSheet/s:'.$group)->item(0);
  if(!$to&&$group==='numFmts'){
   $to=$target->createElementNS(RECOV_NS,$group);
   $target->documentElement->insertBefore($to,$target->documentElement->firstElementChild);
  }
  $count=$tx->query('*',$to)->length;
  $nextFormat=164;
  if($group==='numFmts'){
   foreach($tx->query('*',$to) as $format)$nextFormat=max($nextFormat,(int)$format->getAttribute('numFmtId')+1);
  }
  foreach($from as $index=>$node){
   $copy=$target->importNode($node,true);
   if($group==='numFmts'){
    $maps[$attribute][$node->getAttribute($attribute)]=$nextFormat;
    $copy->setAttribute($attribute,(string)$nextFormat++);
   }else{
    foreach($maps as $key=>$map){
     if(isset($map[$copy->getAttribute($key)]))$copy->setAttribute($key,(string)$map[$copy->getAttribute($key)]);
    }
    $maps[$attribute][$index]=$count;
   }
   $to->appendChild($copy);$count++;
  }
  $to->setAttribute('count',(string)$count);
 }
 return [$target->saveXML(),$maps['style']];
}

function recov_export_original(array &$changes,array $source): void {
 $legacy=recov_legacy($source);
 if(!$legacy)return;
 [$name,$part,$doc]=$legacy;
 $xpath=xml_path($doc);
 [$changes['xl/styles.xml'],$styles]=recov_export_styles($changes['xl/styles.xml'],zip_text($source,'xl/styles.xml'));
 $strings=recov_shared_strings($source);
 foreach($xpath->query('//s:c|//s:sheetData/s:row|//s:col') as $node){
  $attribute=$node->localName==='col'?'style':'s';
  if($node->hasAttribute($attribute)||$node->localName==='c')
   $node->setAttribute($attribute,(string)$styles[(int)$node->getAttribute($attribute)]);
  if($node->getAttribute('t')!=='s')continue;
  $value=$xpath->query('s:v',$node)->item(0);
  $string=$strings[(int)$value->textContent];
  $node->removeChild($value);$node->setAttribute('t','inlineStr');
  $inline=$node->appendChild($doc->createElementNS(RECOV_NS,'is'));
  foreach($string->childNodes as $child)$inline->appendChild($doc->importNode($child,true));
 }
 foreach($xpath->query('//s:sheetView') as $view)$view->removeAttribute('tabSelected');
 $exportPart='xl/worksheets/recov-original.xml';
 recov_add_sheet($changes,$name,$exportPart,$doc->saveXML());
 $relsPath=dirname($part).'/_rels/'.basename($part).'.rels';
 if(!isset($source[$relsPath]))return;
 $rels=xml_doc(zip_text($source,$relsPath));
 $types=xml_doc($changes['[Content_Types].xml']);
 foreach($rels->documentElement->childNodes as $rel){
  if(!$rel instanceof DOMElement)continue;
  $sourcePart=package_target($part,$rel->getAttribute('Target'));
  $type=basename($rel->getAttribute('Type'));
  $target=match($type){
   'comments'=>'recov-original-comments.xml',
   'vmlDrawing'=>'recov-original-notes.vml',
   default=>throw new RuntimeException('Unsupported original recovery worksheet relationship.')
  };
  $rel->setAttribute('Target','../'.$target);
  $changes['xl/'.$target]=zip_text($source,$sourcePart);
  if($type==='comments'){
   $override=$types->documentElement->appendChild($types->createElementNS(RECOV_TYPES,'Override'));
   $override->setAttribute('PartName','/xl/'.$target);
   $override->setAttribute('ContentType','application/vnd.openxmlformats-officedocument.spreadsheetml.comments+xml');
  }
 }
 $changes[dirname($exportPart).'/_rels/'.basename($exportPart).'.rels']=$rels->saveXML();
 $changes['[Content_Types].xml']=$types->saveXML();
}

function recov_export_sheets(array &$changes,string $root,array $records,int $decimalStyle): void {
 $annual=zip_read(workbook_path($root));
 recov_export_original($changes,$annual);
 recov_add_sheet($changes,'RecovBill_Data',RECOV_DATA,recov_data_xml($records,$decimalStyle));
}
