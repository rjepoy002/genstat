<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/xlsx.php';
require_once __DIR__.'/../lib/workbook-policy.php';
function attrs(DOMElement $n): array {$a=[];foreach($n->attributes as $v)$a[$v->nodeName]=$v->value;return $a;}
function tree(DOMElement $n): array {$a=attrs($n);foreach($n->childNodes as $c)if($c instanceof DOMElement)$a['children'][]=['tag'=>$c->localName,'data'=>tree($c)];if(trim($n->textContent)!=='')$a['text']=$n->textContent;return $a;}
function col_num(string $s): int {$n=0;foreach(str_split($s) as $c)$n=$n*26+ord($c)-64;return $n;}
function col_name(int $n): string {$s='';while($n>0){$n--;$s=chr(65+$n%26).$s;$n=intdiv($n,26);}return $s;}
function package_target(string $source,string $target): string {
 if(str_contains($target,':')||str_contains($target,'\\'))throw new RuntimeException('Invalid package relationship target.');
 $parts=[];foreach(explode('/',str_starts_with($target,'/')?ltrim($target,'/'):dirname($source).'/'.$target) as $part){if($part===''||$part==='.')continue;if($part==='..'){if(!$parts)throw new RuntimeException('Package relationship escapes workbook.');array_pop($parts);}else $parts[]=$part;}return implode('/',$parts);
}
function build_layout(string $file, ?string $inputFile = null): array {
 $z=zip_read($file);$styles=xml_doc(zip_text($z,'xl/styles.xml'));$sx=xml_path($styles);$styleData=[];
 foreach(['fonts'=>'font','fills'=>'fill','borders'=>'border','cellXfs'=>'xf','numFmts'=>'numFmt'] as $group=>$child){$styleData[$group]=[];foreach($sx->query("/s:styleSheet/s:$group/s:$child") as $n)$styleData[$group][]=tree($n);}
 $ss=[];if(isset($z['xl/sharedStrings.xml'])){$d=xml_doc(zip_text($z,'xl/sharedStrings.xml'));$x=xml_path($d);foreach($x->query('//s:si') as $si){$t='';foreach($x->query('.//s:t',$si) as $n)$t.=$n->textContent;$ss[]=$t;}}
 $theme=xml_doc(zip_text($z,'xl/theme/theme1.xml'));$tx=new DOMXPath($theme);$tx->registerNamespace('a','http://schemas.openxmlformats.org/drawingml/2006/main');$colors=[];foreach($tx->query('//a:clrScheme/*') as $n){$c=$n->firstElementChild;$colors[]=$c->getAttribute('lastClr')?:$c->getAttribute('val');}
 // Spreadsheet theme indices begin lt1, dk1, lt2, dk2.
 [$colors[0],$colors[1]]=[$colors[1],$colors[0]];[$colors[2],$colors[3]]=[$colors[3],$colors[2]];
 $wb=xml_doc(zip_text($z,'xl/workbook.xml'));$wx=xml_path($wb);$rels=xml_doc(zip_text($z,'xl/_rels/workbook.xml.rels'));$targets=[];foreach($rels->documentElement->childNodes as $n)if($n instanceof DOMElement)$targets[$n->getAttribute('Id')]=$n->getAttribute('Target');
 $result=['template'=>basename($file),'sha256'=>hash_file('sha256',$file),'inputDefinitionsHash'=>hash_file('sha256',$inputFile ?? __DIR__.'/../config/inputs.php'),'styles'=>$styleData,'themeColors'=>$colors,'definedNames'=>[],'externalLinks'=>[],'sheets'=>[]];
 foreach($wx->query('//s:definedName') as $n)$result['definedNames'][]=['attributes'=>attrs($n),'value'=>$n->textContent];
 foreach($z as $name=>$e)if(str_contains($name,'externalLinks/')&&str_ends_with($name,'.rels'))$result['externalLinks'][$name]=zip_text($z,$name);
 $inputs=require ($inputFile ?? __DIR__.'/../config/inputs.php');
 if(!is_array($inputs))throw new RuntimeException('Input definitions must return an array.');
 foreach($inputs as $ref=>$evidence){cell_coordinates($ref);if(!is_string($evidence)||trim($evidence)==='')throw new RuntimeException('Every input requires explicit approval evidence.');}
 $result['approvedInputDefinitions']=$inputs;
 foreach($wx->query('//s:sheets/s:sheet') as $sheet){$target=$targets[$sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships','id')];$path=str_starts_with($target,'/')?ltrim($target,'/'):'xl/'.$target;
 $d=xml_doc(zip_text($z,$path));$x=xml_path($d);$s=['name'=>$sheet->getAttribute('name'),'state'=>$sheet->getAttribute('state')?:'visible','path'=>$path,'range'=>$x->evaluate('string(//s:dimension/@ref)'),'cells'=>[],'rows'=>[],'columns'=>[],'merges'=>[],'settings'=>[],'comments'=>[]];
 $policy=worksheet_policy($d);$s['formulaRanges']=$policy['ranges'];
 foreach($x->query('//s:cols/s:col') as $n)$s['columns'][]=attrs($n);
 foreach($x->query('//s:sheetData/s:row') as $n)$s['rows'][$n->getAttribute('r')]=attrs($n);
 foreach($x->query('//s:mergeCell') as $n)$s['merges'][]=$n->getAttribute('ref');
 foreach($d->documentElement->childNodes as $n)if($n instanceof DOMElement && !in_array($n->localName,['sheetData','cols','mergeCells']))$s['settings'][$n->localName]=$d->saveXML($n);
 foreach($x->query('//s:sheetData/s:row/s:c') as $c){$ref=$c->getAttribute('r');$type=$c->getAttribute('t');$v=$x->evaluate('string(s:v)',$c);if($type==='s')$v=$ss[(int)$v];if($type==='inlineStr')$v=$x->evaluate('string(s:is/s:t)',$c);$f=$x->query('s:f',$c)->item(0);$numeric=$v!==''&&($type===''||$type==='n')&&is_numeric($v);
 $info=formula_info($policy,$ref);$owner=merged_owner($policy['merges'],$ref);
 $editable=$sheet->getAttribute('name')==='Jun26'&&isset($inputs[$ref])&&$info===null&&($owner===null||$owner===$ref);
 $s['cells'][$ref]=['style'=>(int)$c->getAttribute('s'),'type'=>$type,'value'=>$v,'formula'=>$f? $f->textContent:null,'formulaAttributes'=>$f?attrs($f):null,'formulaProtected'=>$info!==null,'formulaKind'=>$info['kind']??null,'editable'=>$editable,'inputType'=>$editable?'number':null,'classification'=>$info!==null?'formula':($editable?'manual-input':($numeric?'protected-source-or-result':($v!==''?'static-label':'blank-protected'))),'evidence'=>$sheet->getAttribute('name')==='Jun26'?($inputs[$ref]??null):null];}
 // Approved implicit blanks are valid inputs, but only the visible merge anchor
 // may be edited. No other blank coordinate is automatically made editable.
 if($sheet->getAttribute('name')==='Jun26')foreach($inputs as $ref=>$evidence){
 if(isset($s['cells'][$ref])||!range_contains($s['range'],$ref))continue;
 $owner=merged_owner($policy['merges'],$ref);$info=formula_info($policy,$ref);$editable=$info===null&&($owner===null||$owner===$ref);
 [$col,$row]=cell_coordinates($ref);$style=(int)($s['rows'][$row]['s']??0);
 if(!isset($s['rows'][$row]['s']))foreach($s['columns'] as $column)if($col>=(int)$column['min']&&$col<=(int)$column['max'])$style=(int)($column['style']??0);
 $s['cells'][$ref]=['style'=>$style,'type'=>'','value'=>'','formula'=>null,'formulaAttributes'=>null,'formulaProtected'=>$info!==null,'formulaKind'=>$info['kind']??null,'editable'=>$editable,'inputType'=>$editable?'number':null,'classification'=>$info!==null?'formula':($editable?'manual-input':'blank-protected'),'evidence'=>$evidence,'implicit'=>true];
 }
 $sheetRels=dirname($path).'/_rels/'.basename($path).'.rels';
 if(isset($z[$sheetRels])){$rd=xml_doc(zip_text($z,$sheetRels));foreach($rd->documentElement->childNodes as $relationship)if($relationship instanceof DOMElement&&str_ends_with($relationship->getAttribute('Type'),'/comments')){
  if($relationship->getAttribute('TargetMode')==='External')throw new RuntimeException('External comments are not supported.');
  $commentPath=package_target($path,$relationship->getAttribute('Target'));$cd=xml_doc(zip_text($z,$commentPath));$cx=xml_path($cd);foreach($cx->query('//s:comment') as $n){$v='';foreach($cx->query('.//s:t',$n) as $t)$v.=$t->textContent;$s['comments'][$n->getAttribute('ref')]=$v;}
 }}
 $s['inputDefinitionIssues']=[];
 if($sheet->getAttribute('name')==='Jun26')foreach($inputs as $ref=>$evidence)if(!($s['cells'][$ref]['editable']??false))$s['inputDefinitionIssues'][]=['cell'=>$ref,'reason'=>formula_info($policy,$ref)!==null?'Formula protection overrides input approval.':(!range_contains($s['range'],$ref)?'Approved coordinate is outside the worksheet used range.':'Covered merged coordinates cannot be inputs; approve the anchor instead.')];
 $s['analysis']=['explicitCellCount'=>count($s['cells']),'formulaNodeCount'=>count(array_filter($s['cells'],fn($c)=>$c['formula']!==null)),'hiddenRows'=>array_keys(array_filter($s['rows'],fn($r)=>($r['hidden']??'0')==='1')),'inputCount'=>count(array_filter($s['cells'],fn($c)=>$c['editable'])),'databaseFields'=>[],'inputPolicy'=>'Explicit source-comment-backed allowlist; other numeric constants remain protected pending business review.'];
 $result['sheets'][]=$s;
 }return apply_browser_edit_policy($result);
}
if(realpath($_SERVER['SCRIPT_FILENAME']??'')===__FILE__){fwrite(STDERR,"Use php tools/refresh-template.php --plan storage/refresh-review.json, review it, then --apply storage/refresh-review.json. Direct regeneration is disabled to protect drafts.\n");exit(1);}

