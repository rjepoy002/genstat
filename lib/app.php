<?php
declare(strict_types=1);
require_once __DIR__.'/xlsx.php';
require_once __DIR__.'/workbook-storage.php';
require_once __DIR__.'/workbook-policy.php';
require_once __DIR__.'/files.php';
require_once __DIR__.'/template-sync.php';
require_once __DIR__.'/formula-confirmation.php';
require_once __DIR__.'/formula-edits.php';
const ROOT = __DIR__.'/..';
function sheet_named(array $layout,string $name): array {
 foreach($layout['sheets'] as $sheet)if($sheet['name']===$name)return $sheet;
 throw new InvalidArgumentException('Unknown worksheet.');
}
function report_sheet(): array {
 $name=$_GET['sheet']??layout()['sheets'][0]['name'];
 if(!is_string($name))throw new InvalidArgumentException('Invalid worksheet identifier.');
 return sheet_named(layout(),$name);
}
function editing_layout(): array {
 $all=layout();$sheet=report_sheet();
 $all['sheets']=array_merge([$sheet],array_values(array_filter($all['sheets'],fn($s)=>$s['name']!==$sheet['name'])));
 return $all;
}
function write_cell_value(DOMDocument $doc,DOMElement $cell,string $value): void {
 foreach(iterator_to_array($cell->childNodes) as $n)if($n instanceof DOMElement&&in_array($n->localName,['f','v','is']))$cell->removeChild($n);
 $cell->removeAttribute('t');$ns=$doc->documentElement->namespaceURI;
 if($value==='')return;
 if(is_numeric($value)){$v=$doc->createElementNS($ns,'v');$v->appendChild($doc->createTextNode($value));$cell->appendChild($v);}
 else{$cell->setAttribute('t','inlineStr');$is=$doc->createElementNS($ns,'is');$t=$doc->createElementNS($ns,'t');$t->setAttributeNS('http://www.w3.org/XML/1998/namespace','xml:space','preserve');$t->appendChild($doc->createTextNode($value));$is->appendChild($t);$cell->appendChild($is);}
}
function template_guard(): void {
 static $lock=null;
 if($lock===null){$lock=fopen(ROOT.'/storage/template.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Cannot acquire worksheet lock.');}
 if(is_file(ROOT.'/storage/refresh-in-progress.json'))throw new RuntimeException('An earlier template transaction was interrupted. Restore its recorded backup before retrying synchronization. Drafts remain protected.');
}
function layout(bool $force=false): array {template_guard();static $l=null;if($l!==null)return $l;$baseline=json_decode(file_get_contents(ROOT.'/config/layout.json'),true,512,JSON_THROW_ON_ERROR);storage_refresh(ROOT,$baseline);$l=current_layout($baseline,$force);if(isset($GLOBALS['incomingWarning']))$GLOBALS['templateRefresh']=['state'=>'warning','message'=>$GLOBALS['incomingWarning']]+$GLOBALS['templateRefresh'];if(isset($GLOBALS['monthlyWorksheetReview']))$GLOBALS['templateRefresh']['monthlyWorksheets']=$GLOBALS['monthlyWorksheetReview'];return $l;}
function assert_template(): void {
 layout();if(!in_array($GLOBALS['templateRefresh']['state']??'ready',['ready','warning'],true))throw new RuntimeException($GLOBALS['templateRefresh']['message']);
 if(!hash_equals(layout()['sha256'],hash_file('sha256',workbook_path(ROOT))))throw new RuntimeException('Excel template changed while processing this request. Reload before saving.');
 if(!hash_equals(layout()['inputDefinitionsHash']??'',hash_file('sha256',ROOT.'/config/inputs.php')))throw new RuntimeException('Input definitions changed. Restore the approved definitions and retry synchronization.');
}
function live_policy(): array {
 assert_template();$z=zip_read(workbook_path(ROOT));return worksheet_policy(xml_doc(zip_text($z,report_sheet()['path'])));
}
function period(string $p): string {if(!preg_match('/^2026-0[1-9]$/D',$p))throw new InvalidArgumentException('Unsupported reporting period.');return $p;}
function validate_values(array $values): array {
 $s=report_sheet();$policy=live_policy();$out=[];foreach($values as $ref=>$v){if(!($cell=worksheet_cell($s,$ref))||!$cell['editable'])throw new InvalidArgumentException("Protected or unknown cell: $ref");
 if(formula_info($policy,$ref)!==null)throw new InvalidArgumentException("Formula cell is read-only: $ref");
 $owner=merged_owner($policy['merges'],$ref);if($owner!==null&&$owner!==$ref)throw new InvalidArgumentException("Only the merged anchor is editable: $ref");
 if(!is_string($v)||strlen($v)>32767||str_contains($v,"\0"))throw new InvalidArgumentException("Invalid value in $ref");
 if(str_starts_with($v,'='))throw new InvalidArgumentException("Use the formula editor in $ref");if(is_numeric($v)&&!is_finite((float)$v))throw new InvalidArgumentException("Enter a finite number in $ref");$out[$ref]=$v;
 }return $out;
}
function draft_path(string $p): string {return ROOT.'/storage/draft-'.period($p).(report_sheet()['name']===layout()['sheets'][0]['name']?'':'--'.substr(hash('sha256',report_sheet()['name']),0,16)).'.json';}
function read_draft(string $p): array {
 assert_template();$path=draft_path($p);if(!is_file($path))return ['period'=>$p,'values'=>(object)[],'formulas'=>(object)[],'revision'=>0,'savedAt'=>null];
 $d=json_decode(file_get_contents($path),true,512,JSON_THROW_ON_ERROR);$baseline=json_decode(file_get_contents(ROOT.'/config/layout.json'),true,512,JSON_THROW_ON_ERROR);
 if(isset(layout()['coordinateMaps'][$d['templateHash']??'']))$d=sync_project_draft($d,$baseline,layout());
 if(($d['templateHash']??'')!==layout()['sha256']){
  $oldSources=$d['sourceCells']??[];
  foreach(array_unique(array_merge(array_keys($d['values']),array_keys($d['formulas']??[]))) as $ref){
   $old=$oldSources[$ref]??worksheet_cell(sheet_named($baseline,report_sheet()['name']),$ref);$new=worksheet_cell(report_sheet(),$ref);
   if(!$old||!$new||($old['formula']===null&&$old['value']!==$new['value'])||$old['formula']!==$new['formula']||(array_key_exists('attributes',$old)?$old['attributes']:($old['formulaAttributes']??null))!==$new['formulaAttributes'])throw new DomainException("Conflict: ".report_sheet()['name']."!$ref: Excel and saved web data differ. Correct the workbook and select Sync Now. Draft is preserved.");
  }
 }
 validate_values($d['values']);validate_formula_overlays($d['formulas']??[],$d['values']);return $d;
}
function save_draft(string $p,array $values,int $revision,?array $formulas=null): array {
 assert_template();
 $path=draft_path($p);$lock=fopen($path.'.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Cannot lock draft.');
 try{$old=read_draft($p);if($old['revision']!==$revision)throw new DomainException('Another window saved this draft. Open Report to load its latest version before saving.');
 $prior=(array)($old['formulas']??[]);$formulas??=$prior;$values=validate_values($values);$formulas=validate_formula_overlays($formulas,$values,$prior,true);
 $audit=$old['formulaAudit']??[];$source=expanded_formulas(report_sheet());
 foreach(array_unique(array_merge(array_keys($prior),array_keys($formulas))) as $ref)if(($prior[$ref]??null)!==($formulas[$ref]??null))$audit[]=['sheet'=>report_sheet()['name'],'cell'=>$ref,'originalFormula'=>$source[$ref]??null,'previousFormula'=>$prior[$ref]??$source[$ref]??null,'newFormula'=>$formulas[$ref]??$source[$ref]??null,'timestamp'=>gmdate('c'),'editor'=>'web-entry','revision'=>$revision+1];
 $d=['period'=>$p,'sheet'=>report_sheet()['name'],'templateHash'=>layout()['sha256'],'values'=>(object)$values,'formulas'=>(object)$formulas,'formulaAudit'=>$audit,'sourceCells'=>overlay_source_cells($values,$formulas),'revision'=>$revision+1,'savedAt'=>gmdate('c')];assert_template();atomic_file_write($path,json_bytes($d));foreach(array_unique(array_merge(array_keys($prior),array_keys($formulas))) as $ref)unset($_SESSION['formulaCells'][formula_grant_key($ref)]);return $d;
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function export_workbook(array $values,array $formulas=[]): string {
 assert_template();$values=validate_values($values);$z=zip_read(workbook_path(ROOT));$path=report_sheet()['path'];$doc=xml_doc(zip_text($z,$path));$x=xml_path($doc);
 $policy=worksheet_policy($doc);
 foreach($values as $ref=>$value){$c=$x->query('//s:c[@r="'.$ref.'"]')->item(0);if(formula_info($policy,$ref)!==null)throw new RuntimeException('Formula input conflicts with template.');
 if(!$c){
 [$col,$row]=cell_coordinates($ref);$ns=$doc->documentElement->namespaceURI;$data=$x->query('//s:sheetData')->item(0);$r=$x->query('//s:row[@r="'.$row.'"]')->item(0);
 if(!$r){$r=$doc->createElementNS($ns,'row');$r->setAttribute('r',(string)$row);$next=null;foreach($x->query('s:row',$data) as $existing)if((int)$existing->getAttribute('r')>$row){$next=$existing;break;}$data->insertBefore($r,$next);}
 $c=$doc->createElementNS($ns,'c');$c->setAttribute('r',$ref);$c->setAttribute('s',(string)worksheet_cell(report_sheet(),$ref)['style']);$next=null;foreach($x->query('s:c',$r) as $existing)if(cell_coordinates($existing->getAttribute('r'))[0]>$col){$next=$existing;break;}$r->insertBefore($c,$next);
 }
 $c->removeAttribute('t');foreach(iterator_to_array($c->childNodes) as $n)if($n instanceof DOMElement&&in_array($n->localName,['v','is']))$c->removeChild($n);
 write_cell_value($doc,$c,$value);}
 // Preserve all formula nodes and caches; ask Excel to recalculate on open.
 $wb=xml_doc(zip_text($z,'xl/workbook.xml'));$wx=xml_path($wb);$calc=$wx->query('//s:calcPr')->item(0);if($calc){$calc->setAttribute('fullCalcOnLoad','1');$calc->setAttribute('forceFullCalc','1');}
 $changes=[];
 if($formulas){$formulas=validate_formula_overlays($formulas,$values);apply_formula_export($z,$doc,$formulas,$values,$changes);}
 assert_template();return zip_write($z,$changes+[$path=>$doc->saveXML(),'xl/workbook.xml'=>$wb->saveXML()]);
}
// Commit an already saved draft, under the request-wide template lock.
function synchronize_draft(string $p): array {
 assert_template();$draft=read_draft($p);$values=(array)$draft['values'];$formulas=(array)($draft['formulas']??[]);
 if(!$values&&!$formulas)return ['message'=>'No browser entries to synchronize.','hash'=>layout()['sha256']];

 $active=workbook_path(ROOT);$expected=layout()['sha256'];$bytes=export_workbook($values,$formulas);
 $tmp=$active.'.'.bin2hex(random_bytes(6)).'.xlsx';$draftFile=draft_path($p);$draftRaw=file_get_contents($draftFile);$cacheFile=ROOT.'/storage/template-auto.json';$cacheRaw=is_file($cacheFile)?file_get_contents($cacheFile):null;
 $syncFile=ROOT.'/storage/workbook-sync.json';$syncRaw=is_file($syncFile)?file_get_contents($syncFile):null;
 $published=false;
 try {
  atomic_file_write($tmp,$bytes);workbook_validate($tmp);
  if(hash_file('sha256',$active)!==$expected)throw new DomainException('Conflict: Excel changed during synchronization. Reload and reconcile; no cells were overwritten.');
  $backup=workbook_backup(ROOT,$active,'before-sync');
  if(hash_file('sha256',$active)!==$expected)throw new DomainException('Conflict: Excel changed during backup. No cells were overwritten.');
  $next=sync_reconcile(layout(),build_layout($tmp,ROOT.'/config/inputs.php'));$next['template']='genstat_2026.xlsx';
  $draft['templateHash']=hash('sha256',$bytes);$draft['sourceCells']=[];
  foreach(array_unique(array_merge(array_keys($values),array_keys($formulas))) as $ref){$c=worksheet_cell(sheet_named($next,report_sheet()['name']),$ref);$draft['sourceCells'][$ref]=['value'=>$c['value'],'formula'=>$c['formula'],'attributes'=>$c['formulaAttributes']];}
  $draft['revision']++;$draft['synchronizedAt']=gmdate('c');$draft['values']=(object)$values;$draft['formulas']=(object)$formulas;
  if(hash_file('sha256',$active)!==$expected)throw new DomainException('Conflict: Excel changed before replacement. No cells were overwritten.');
  atomic_file_write($active,$bytes);$published=true;
  atomic_file_write($draftFile,json_bytes($draft));
  $baseline=json_decode(file_get_contents(ROOT.'/config/layout.json'),true,512,JSON_THROW_ON_ERROR);
  $status=['state'=>'ready','message'=>'Browser entries synchronized to Excel.','workbook'=>'genstat_2026.xlsx','syncedAt'=>gmdate('c'),'changes'=>[]];
  atomic_file_write($cacheFile,json_bytes(['baseline'=>$baseline['sha256'],'layout'=>$next,'status'=>$status,'checkedAt'=>time(),'observedHash'=>$next['sha256']]));
  atomic_file_write($syncFile,json_bytes(['sha256'=>$next['sha256'],'synchronizedAt'=>$draft['synchronizedAt'],'backup'=>basename($backup)]));
  if(hash_file('sha256',$active)!==$next['sha256'])throw new DomainException('Conflict: Excel changed while synchronization completed.');
  return ['message'=>$status['message'],'hash'=>$next['sha256'],'backup'=>basename($backup)];
 }catch(Throwable $e){
  if($published){$external=hash_file('sha256',$active)!==hash('sha256',$bytes);if(!$external)atomic_file_write($active,file_get_contents($backup));atomic_file_write($draftFile,$draftRaw);if($cacheRaw!==null)atomic_file_write($cacheFile,$cacheRaw);elseif(is_file($cacheFile))unlink($cacheFile);if($syncRaw!==null)atomic_file_write($syncFile,$syncRaw);elseif(is_file($syncFile))unlink($syncFile);if($external)throw new DomainException('Conflict: a newer Excel workbook was preserved. The original draft was restored and backups retained.',0,$e);}
  throw $e;
 }finally{if(is_file($tmp))unlink($tmp);}
}



