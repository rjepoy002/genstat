<?php
declare(strict_types=1);
require_once __DIR__.'/formula-engine.php';

function formula_creation_allowed(array $sheet,string $ref): bool {
 $c=worksheet_cell($sheet,$ref);return $c!==null&&$c['editable']&&!($c['formulaProtected']??false)&&$c['formula']===null;
}
function validate_formula_overlays(array $formulas,array $values,array $old=[],bool $authorize=false): array {
 $layout=editing_layout();$sheet=$layout['sheets'][0];$original=expanded_formulas($sheet);$policy=live_policy();
 if(count($formulas)>500)throw new InvalidArgumentException('Too many formula overrides.');
 $engine=new FormulaEngine($layout,$values,$formulas);
 foreach($formulas as $ref=>$formula){
  if(!is_string($ref)||!preg_match('/^[A-Z]{1,3}[1-9][0-9]*$/D',$ref)||!worksheet_cell($sheet,$ref)||!is_string($formula))throw new InvalidArgumentException('Unknown cell or invalid formula entry.');
  if((worksheet_cell($sheet,$ref)['readOnly']??false)||($sheet['readOnly']??false))throw new InvalidArgumentException('This cell is explicitly read-only.');
  if(array_key_exists($ref,$values))throw new InvalidArgumentException("Cell $ref cannot contain both a value override and a formula.");
  $owner=merged_owner($sheet['merges'],$ref);if($owner!==null&&$owner!==$ref)throw new InvalidArgumentException('Only the merged anchor may be edited.');
  if(isset($original[$ref])){
   $kind=$sheet['cells'][$ref]['formulaKind']??'ordinary';if(!in_array($kind,['ordinary','shared']))throw new InvalidArgumentException('Array/data-table formulas are not editable in this phase.');
  }elseif(!formula_creation_allowed($sheet,$ref)||formula_info($policy,$ref)!==null)throw new InvalidArgumentException("$ref is not an editable formula-entry cell.");
  if(str_starts_with($formula,'='))$engine->validate($formula,$sheet['name']);elseif(strlen($formula)>32767||str_contains($formula,"\0")||(is_numeric($formula)&&!is_finite((float)$formula)))throw new InvalidArgumentException('Invalid replacement value.');
 }
 foreach(array_unique(array_merge(array_keys($old),array_keys($formulas))) as $ref)if(($old[$ref]??null)!==($formulas[$ref]??null)&&(isset($original[$ref])||str_starts_with($old[$ref]??'','='))&&$authorize)require_formula_cell_confirmation($ref);
 // A cycle is rejected even in an unselected IF branch: dependency safety
 // must not rely on the currently chosen conditional branch.
 $graph=[];
 $effective=array_replace($original,array_map('formula_overlay_expression',$formulas));
 $edges=0;foreach($effective as $ref=>$formula){try{$ast=(new FormulaParser($formula))->parse();$graph[$ref]=formula_dependencies($ast,$sheet['name']);}catch(InvalidArgumentException $e){if(isset($formulas[$ref]))throw $e;$graph[$ref]=[];}$edges+=count($graph[$ref]);if($edges>50000)throw new InvalidArgumentException('Formula dependency graph exceeds the safe size limit.');}
 $done=[];foreach(array_keys($formulas) as $ref){$active=[];formula_cycle_visit($ref,$graph,$active,$done,0);}
 foreach($formulas as $ref=>$formula){try{$engine->value($sheet['name'],$ref);}catch(FormulaError $e){if(in_array($e->getMessage(),['#CIRC!','#LIMIT!','#EXTERNAL!']))throw new InvalidArgumentException("Formula in $ref cannot be saved: ".$e->getMessage());}}
 return $formulas;
}
function formula_dependencies(array $a,string $sheet): array {
 if($a[0]==='ref')return ($a[1]===''||strcasecmp($a[1],$sheet)===0)?[str_replace('$','',$a[2])]:[];
 if($a[0]==='binary'&&$a[1]===':'){
  if($a[2][1]!==''&&strcasecmp($a[2][1],$sheet)!==0)return [];
  [$c1,$r1]=cell_coordinates($a[2][2]);[$c2,$r2]=cell_coordinates($a[3][2]);if(($c2-$c1+1)*($r2-$r1+1)>20000)throw new InvalidArgumentException('Formula range is too large.');
  $out=[];for($r=$r1;$r<=$r2;$r++)for($c=$c1;$c<=$c2;$c++)$out[]=formula_col($c).$r;return $out;
 }
 $out=[];foreach(array_slice($a,1) as $child)if(is_array($child)){if(isset($child[0])&&is_string($child[0]))$out=array_merge($out,formula_dependencies($child,$sheet));else foreach($child as $node){$out=array_merge($out,formula_dependencies($node,$sheet));if(count($out)>20000)throw new InvalidArgumentException('Too many formula dependencies.');}if(count($out)>20000)throw new InvalidArgumentException('Too many formula dependencies.');}return array_unique($out);
}
function formula_cycle_visit(string $ref,array $graph,array &$active,array &$done,int $depth): void {
 if(isset($active[$ref]))throw new InvalidArgumentException('Circular reference involving '.$ref.'.');
 if(isset($done[$ref])||!isset($graph[$ref]))return;if($depth>256)throw new InvalidArgumentException('Formula dependency depth limit exceeded.');
 $active[$ref]=true;foreach($graph[$ref] as $dependency)formula_cycle_visit($dependency,$graph,$active,$done,$depth+1);unset($active[$ref]);$done[$ref]=true;
}
function overlay_source_cells(array $values,array $formulas): array {
 $sheet=report_sheet();$out=[];
 foreach(array_unique(array_merge(array_keys($values),array_keys($formulas))) as $ref){$c=worksheet_cell($sheet,$ref);$out[$ref]=['value'=>$c['value'],'formula'=>$c['formula'],'attributes'=>$c['formulaAttributes']];}
 return $out;
}
function calculate_draft(array $draft): array {
 require_once __DIR__.'/render.php';
 $layout=editing_layout();$sheet=$layout['sheets'][0];$formulas=(array)($draft['formulas']??[]);$engine=new FormulaEngine($layout,(array)$draft['values'],$formulas);$results=$engine->results();
 $display=[];$effective=array_replace(expanded_formulas($sheet),$formulas);
 foreach($results[$sheet['name']]??[] as $ref=>$r){
  $cell=worksheet_cell($sheet,$ref);if(!$cell)continue;
  $numeric=is_int($r['value'])||is_float($r['value']);$v=$r['error']??(is_bool($r['value'])?($r['value']?'TRUE':'FALSE'):($numeric?sprintf('%.17g',(float)$r['value']):(string)$r['value']));$cell['value']=$v;$cell['type']=$r['error']||!$numeric?'str':'';
  $display[$ref]=['raw'=>$v,'display'=>display_value($cell,$layout),'error'=>$r['error'],'formula'=>$effective[$ref]??null,'cached'=>isset($engine->warnings[$sheet['name'].'!'.$ref])];
 }
 return ['cells'=>$display,'warnings'=>$engine->warnings];
}

function apply_formula_export(array &$zip,DOMDocument $doc,array $formulas,array $values,array &$changes): void {
 $sheet=report_sheet();$expanded=expanded_formulas($sheet);$x=xml_path($doc);$ns=$doc->documentElement->namespaceURI;
 // Changing one shared member requires materializing the affected shared
 // group, preserving sibling semantics and mixed absolute references.
 $groups=[];foreach($formulas as $ref=>$formula){$a=$sheet['cells'][$ref]['formulaAttributes']??[];if(($a['t']??'')==='shared')$groups[$a['si']]=true;}
 foreach($sheet['cells'] as $ref=>$cell)if(($cell['formulaAttributes']['t']??'')==='shared'&&isset($groups[$cell['formulaAttributes']['si']])){
  $f=$x->query('//s:c[@r="'.$ref.'"]/s:f')->item(0);if($f){while($f->attributes->length)$f->removeAttributeNode($f->attributes->item(0));$f->textContent=substr($expanded[$ref],1);}
 }
 $engine=new FormulaEngine(editing_layout(),$values,$formulas);$results=$engine->results()[$sheet['name']];
 foreach($formulas as $ref=>$formula){
  $cell=$x->query('//s:c[@r="'.$ref.'"]')->item(0);
  if(!$cell){ // Approved implicit blank inside the existing layout.
   [$col,$row]=cell_coordinates($ref);$data=$x->query('//s:sheetData')->item(0);$r=$x->query('//s:row[@r="'.$row.'"]')->item(0);
   if(!$r){$r=$doc->createElementNS($ns,'row');$r->setAttribute('r',(string)$row);$next=null;foreach($x->query('s:row',$data) as $n)if((int)$n->getAttribute('r')>$row){$next=$n;break;}$data->insertBefore($r,$next);}
   $cell=$doc->createElementNS($ns,'c');$cell->setAttribute('r',$ref);$cell->setAttribute('s',(string)worksheet_cell($sheet,$ref)['style']);$next=null;foreach($x->query('s:c',$r) as $n)if(cell_coordinates($n->getAttribute('r'))[0]>$col){$next=$n;break;}$r->insertBefore($cell,$next);
  }
  foreach(iterator_to_array($cell->childNodes) as $n)if($n instanceof DOMElement&&in_array($n->localName,['f','v','is']))$cell->removeChild($n);
  if(!str_starts_with($formula,'=')){write_cell_value($doc,$cell,$formula);continue;}
  $cell->removeAttribute('t');$f=$doc->createElementNS($ns,'f');$f->textContent=substr($formula,1);$cell->appendChild($f);
  $r=$results[$ref];$v=$doc->createElementNS($ns,'v');
  if($r['error']){$cell->setAttribute('t','e');$v->textContent=$r['error'];}
  elseif(is_bool($r['value'])){$cell->setAttribute('t','b');$v->textContent=$r['value']?'1':'0';}
  elseif(is_int($r['value'])||is_float($r['value']))$v->textContent=sprintf('%.17g',$r['value']);
  else{$cell->setAttribute('t','str');$v->textContent=(string)$r['value'];}$cell->appendChild($v);
 }
 foreach($results as $ref=>$result){
  if(isset($formulas[$ref])||isset($engine->warnings[$sheet['name'].'!'.$ref]))continue;
  $cell=$x->query('//s:c[@r="'.$ref.'"]')->item(0);if(!$cell||!$x->query('s:f',$cell)->length)continue;
  foreach(iterator_to_array($cell->childNodes) as $n)if($n instanceof DOMElement&&$n->localName==='v')$cell->removeChild($n);
  $v=$doc->createElementNS($ns,'v');$cell->removeAttribute('t');
  if($result['error']){$cell->setAttribute('t','e');$v->textContent=$result['error'];}
  elseif(is_bool($result['value'])){$cell->setAttribute('t','b');$v->textContent=$result['value']?'1':'0';}
  elseif(is_int($result['value'])||is_float($result['value']))$v->textContent=sprintf('%.17g',(float)$result['value']);
  else{$cell->setAttribute('t','str');$v->textContent=(string)$result['value'];}$cell->appendChild($v);
 }
 // Old dependency chains cannot describe newly inserted/edited formulas.
 // Remove the optional chain and its package references so Excel rebuilds it.
 unset($zip['xl/calcChain.xml']);
 foreach(['xl/_rels/workbook.xml.rels','[Content_Types].xml'] as $part){$d=xml_doc(zip_text($zip,$part));foreach(iterator_to_array($d->documentElement->childNodes) as $n)if($n instanceof DOMElement&&(str_ends_with($n->getAttribute('Type'),'/calcChain')||$n->getAttribute('PartName')==='/xl/calcChain.xml'))$d->documentElement->removeChild($n);$changes[$part]=$d->saveXML();}
}
function formula_api_payload(array $draft): array {
 $sheet=report_sheet();$draft['formulas']=$draft['formulas']??(object)[];$draft['authorization']=formula_auth_status();
 $draft['sourceFormulas']=expanded_formulas($sheet);
 $draft['calculation']=calculate_draft($draft);return $draft;
}
