<?php
declare(strict_types=1);

function report_area_name(string $text): string {
 $text=strtr($text,["\u{2019}"=>"'","\u{2018}"=>"'","\u{00A0}"=>' ']);
 return strtolower(trim(preg_replace('/\s+/u',' ',preg_replace('/\s*\/\s*/u','/',$text))));
}
function report_area_headers(array $layout): array {
 $sheet=$layout['sheets'][0];$report=$layout['report'];$first=$report['firstRow'];$last=$first;
 // Header band ends at the first Particulars data label, rather than assuming
 // that every area occupies two cells or that the band always starts at row 8.
 for($r=$first+1;$r<=$report['lastRow'];$r++){
  if(trim($sheet['cells']['A'.$r]['value']??'')!=='')break;$last=$r;
 }
 $out=[];
 for($c=2;$c<=$report['lastCol'];$c++){
  $fragments=[];
  for($r=$first;$r<=$last;$r++){$ref=col_name($c).$r;$owner=merged_owner($sheet['merges'],$ref);if($owner!==null&&$owner!==$ref)continue;$value=$sheet['cells'][$ref]['value']??'';if(trim($value)!=='')$fragments[]=['reference'=>$ref,'value'=>$value];}
  // Stop before the verified TOTAL group; never combine totals/comparisons
  // with area labels or borrow a neighbour's merged heading.
  if(array_filter($fragments,fn($f)=>report_area_name($f['value'])==='total'))break;
  if(!$fragments)continue;
  $label=trim(preg_replace('/\s+/u',' ',preg_replace('/\s*\/\s*/u','/',implode(' ',array_column($fragments,'value')))));
  $out[$c]=['column'=>col_name($c),'label'=>$label,'normalized'=>report_area_name($label),'fragments'=>$fragments];
 }
 return $out;
}
function report_area_alias(string $label,array $areas,array $mappings): ?string {
 // Deliberate aliases only. Other area choices, including combined-area
 // assignments, remain under their existing explicit saved bindings.
 $aliases=["brooke's point","brooke's pt.","brooke's pt",'brookes point','brookes pt.','brookes pt'];
 if(!in_array(report_area_name($label),$aliases,true))return null;
 $master=false;foreach($areas as $area)if((string)$area['AreaID']==='12'&&in_array(report_area_name($area['AreaName']),$aliases,true))$master=true;
 $verified=false;foreach($mappings as $mapping)if((string)$mapping['GenstatAreaID']==='12')$verified=true;
 return $master&&$verified?'12':null;
}
