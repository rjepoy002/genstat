<?php
declare(strict_types=1);
require_once __DIR__.'/workbook-storage.php';

function structural_signature(array $layout): string {
 $data=['styles'=>$layout['styles'],'theme'=>$layout['themeColors'],'names'=>$layout['definedNames'],'sheets'=>[]];
 foreach($layout['sheets'] as $sheet){
  $settings=$sheet['settings'];unset($settings['sheetViews']);
  $cells=[];foreach($sheet['cells'] as $ref=>$cell)$cells[$ref]=$cell['style'];ksort($cells);
  $merges=$sheet['merges'];sort($merges);
  $data['sheets'][]=['name'=>$sheet['name'],'state'=>$sheet['state'],'path'=>$sheet['path'],'range'=>$sheet['range'],'rows'=>$sheet['rows'],'columns'=>$sheet['columns'],'merges'=>$merges,'settings'=>$settings,'cells'=>$cells];
 }
 return hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
}
function current_layout(array $baseline,bool $force=false): array {
 return auto_template_layout(ROOT,$baseline,$force);
}
function auto_template_layout(string $root,array $baseline,bool $force=false,?string $source=null): array {
 $lock=fopen($root.'/storage/sync.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Cannot acquire synchronization lock.');
 try{return auto_template_locked($root,$baseline,$force,$source);}finally{flock($lock,LOCK_UN);fclose($lock);}
}
function auto_template_locked(string $root,array $baseline,bool $force,?string $source=null): array {
$workbook=$source ?? workbook_path($root);
 require_once __DIR__.'/template-refresh.php';
 require_once __DIR__.'/sync-mapping.php';
 $file=$root.'/storage/template-auto.json';$cacheRaw=is_file($file)?file_get_contents($file):null;$cache=$cacheRaw!==null?json_decode($cacheRaw,true):null;
 $previous=apply_browser_edit_policy(($cache['baseline']??null)===$baseline['sha256']?($cache['layout']??$baseline):$baseline);
 if(($cache['baseline']??null)!==$baseline['sha256'])$cache=null;
 $verified=$previous;
$extractor=hash('sha256',hash_file('sha256',__DIR__.'/../tools/build-layout.php').hash_file('sha256',__DIR__.'/sync-mapping.php').hash_file('sha256',__DIR__.'/workbook-policy.php'));$forceExtraction=($cache['extractor']??null)!==$extractor;
 $GLOBALS['templateRefresh']=['state'=>'ready','message'=>'Synced: workbook and browser are up to date.','changes'=>[],'workbook'=>basename($workbook),'syncedAt'=>$cache['status']['syncedAt']??gmdate('c')];
 try{
  clearstatcache();$stat=@stat($workbook);if(!$stat)throw new RuntimeException('Excel workbook is unavailable; retry after saving.');
  $stamp=[$stat['mtime'],$stat['ctime'],$stat['size'],hash_file('sha256',$root.'/config/inputs.php')];
  $hash=@hash_file('sha256',$workbook);if(!$hash)throw new RuntimeException('Excel file is temporarily locked. Retry shortly.');
  if(!$force&&!$forceExtraction&&($cache['observedHash']??null)===$hash&&($cache['stamp'][3]??null)===$stamp[3]&&(($cache['status']['state']??'')==='ready'||time()-($cache['checkedAt']??0)<10)){$GLOBALS['templateRefresh']=$cache['status'];return $previous;}
  if(!$forceExtraction&&$hash===$previous['sha256']&&$stamp[3]===$previous['inputDefinitionsHash']){$status=$GLOBALS['templateRefresh'];}
  else {
   if($previous['sha256']!==$baseline['sha256']&&!isset($previous['coordinateMaps'][$baseline['sha256']])){
    $bootstrap=sync_reconcile($baseline,$previous);$bootstrap['coordinateMaps']=array_replace($previous['coordinateMaps']??[],$bootstrap['coordinateMaps']);$previous=$bootstrap;
   }
   $content=@auto_template_content($root,$previous,$forceExtraction?null:($cache['contentSignature']??null),$workbook);
   $next=$content['layout']??build_layout($workbook,$root.'/config/inputs.php');
   if($hash!==$next['sha256'])throw new RuntimeException('Excel changed while it was being read. Retry synchronization after saving finishes.');
   if($stamp[3]!==$baseline['inputDefinitionsHash'])throw new SyncConflict('Input approval definitions changed. Restore the approved definitions and retry synchronization.');
   $changes=auto_template_changes($previous,$next);
   $next=sync_reconcile($previous,$next);
   foreach(glob($root.'/storage/draft-*.json')?:[] as $draftFile){$draft=json_decode(file_get_contents($draftFile),true,512,JSON_THROW_ON_ERROR);sync_project_draft($draft,$baseline,$next);}
   if($changes['formulas']){
    require_once __DIR__.'/formula-edits.php';
    $engine=new FormulaEngine($next);$graph=[];$changed=[];$edges=0;
    foreach($next['sheets'] as $sheet){$graph=[];$changed=[];foreach(expanded_formulas($sheet) as $ref=>$formula){
     $priorSheet=array_values(array_filter($previous['sheets'],fn($s)=>$s['name']===$sheet['name']))[0]??['cells'=>[]];
     $isChanged=isset($priorSheet['cells'][$ref])&&(expanded_formulas($priorSheet)[$ref]??null)!==$formula;
     // Preserve explicit Excel error literals as source evidence; never repair them.
     if(in_array(ltrim($formula,'=+'),['#REF!','#N/A','#VALUE!'],true)){$graph[$ref]=[];continue;}
     try{$ast=(new FormulaParser($formula))->parse();$graph[$ref]=formula_dependencies($ast,$sheet['name']);}catch(InvalidArgumentException $e){if($isChanged)sync_conflict($sheet['name'],$ref,$e->getMessage());$graph[$ref]=[];}
     $edges+=count($graph[$ref]);if($edges>50000)throw new RuntimeException('Formula dependency limit exceeded.');
     if($isChanged){try{$engine->validate($formula,$sheet['name']);}catch(Throwable $e){sync_conflict($sheet['name'],$ref,$e->getMessage());}$changed[]=$ref;}
    }
    $done=[];foreach($changed as $ref){$active=[];try{formula_cycle_visit($ref,$graph,$active,$done,0);}catch(Throwable $e){sync_conflict($sheet['name'],$ref,$e->getMessage());}}
    }
    // Changed expressions are checked above; existing Excel errors remain visible.
   }
   // Archive exact drafts and metadata without migrating or rewriting any draft.
   $backup=$root.'/storage/backups/auto-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4));
   if(!mkdir($backup,0777,true))throw new RuntimeException('Cannot back up template metadata.');
   $metadata=json_bytes($verified);atomic_file_write($backup.'/layout.json',$metadata);
   if(hash_file('sha256',$backup.'/layout.json')!==hash('sha256',$metadata))throw new RuntimeException('Metadata backup verification failed.');
   if($cacheRaw!==null){atomic_file_write($backup.'/template-auto.json',$cacheRaw);if(hash_file('sha256',$backup.'/template-auto.json')!==hash('sha256',$cacheRaw))throw new RuntimeException('Cache backup verification failed.');}
   foreach(glob($root.'/storage/draft-*.json')?:[] as $draft){$raw=file_get_contents($draft);atomic_file_write($backup.'/'.basename($draft),$raw);if(hash_file('sha256',$backup.'/'.basename($draft))!==hash('sha256',$raw))throw new RuntimeException('Draft backup verification failed.');}
   $bytes=file_get_contents($workbook);if(hash('sha256',$bytes)!==$next['sha256'])throw new RuntimeException('Excel changed during refresh; retry.');
   atomic_file_write($backup.'/observed-template.xlsx',$bytes);
   $previous=$next;$status=['state'=>'ready','message'=>'Synced: workbook and browser are up to date.','syncedAt'=>gmdate('c'),'workbook'=>'genstat_2026.xlsx','changes'=>$changes,'strategy'=>$content['layout']!==null?'values-only':'layout-extraction','backup'=>basename($backup)];
  }
  $signature=$content['signature']??$cache['contentSignature']??@auto_template_content($root,$previous,null,$workbook)['signature'];
  if(hash_file('sha256',$workbook)!==$previous['sha256'])throw new RuntimeException('Excel changed while publishing metadata; retry.');
  if(hash_file('sha256',$root.'/config/inputs.php')!==$stamp[3])throw new RuntimeException('Input definitions changed during synchronization. Retry.');
  atomic_file_write($file,json_bytes(['extractor'=>$extractor,'baseline'=>$baseline['sha256'],'layout'=>$previous,'stamp'=>$stamp,'observedHash'=>$hash,'checkedAt'=>time(),'status'=>$status,'contentSignature'=>$signature]));
  $GLOBALS['templateRefresh']=$status;return $previous;
 }catch(Throwable $e){
  $conflict=$e instanceof SyncConflict;$GLOBALS['templateRefresh']=['state'=>$conflict?'conflict':'error','message'=>($conflict?'Conflict: ':'Error: ').$e->getMessage().' Last verified view and drafts are preserved.','workbook'=>'genstat_2026.xlsx','syncedAt'=>$cache['status']['syncedAt']??null,'changes'=>[]];
  // Retry transient saves after the short digest interval, rather than parsing
  // an invalid package on every concurrent browser request.
  if(isset($stamp))try{atomic_file_write($file,json_bytes(['baseline'=>$baseline['sha256'],'layout'=>$verified,'stamp'=>$stamp,'observedHash'=>$hash??null,'checkedAt'=>time(),'status'=>$GLOBALS['templateRefresh'],'contentSignature'=>$cache['contentSignature']??null]));}catch(Throwable $ignored){}
  return $verified;
 }
}
function auto_template_changes(array $old,array $next): array {
 $format=[$old['styles'],$old['themeColors']]!==[$next['styles'],$next['themeColors']];$values=false;$formulas=false;
 foreach($next['sheets'] as $i=>$sheet)foreach($sheet['cells'] as $ref=>$cell){$before=$old['sheets'][$i]['cells'][$ref]??null;$values=$values||($before['value']??null)!==$cell['value'];$formulas=$formulas||formula_signature($before)!==formula_signature($cell);$format=$format||($before!==null&&$before['style']!==$cell['style']);}
 return ['values'=>$values,'formatting'=>$format,'formulas'=>$formulas,'structure'=>structural_signature($old)!==structural_signature($next)];
}
function auto_template_content(string $root,array $previous,?string $signature,?string $source=null): array {
$workbook=$source ?? workbook_path($root);
 $zip=workbook_validate($workbook);$parts=[];$documents=[];$strings=[];
 foreach($zip as $name=>$entry){$raw=zip_text($zip,$name);
  if(strlen($raw)!==$entry['header']['size']||sprintf('%u',crc32($raw))!==sprintf('%u',$entry['header']['crc']))throw new RuntimeException('Incomplete or invalid Excel package.');
  if(str_ends_with($name,'.xml')||str_ends_with($name,'.rels'))$doc=xml_doc($raw);
  if($name==='xl/sharedStrings.xml'){$x=xml_path($doc);foreach($x->query('//s:si') as $si){$value='';foreach($x->query('.//s:t',$si) as $t)$value.=$t->textContent;$strings[]=$value;}continue;}
  if(str_starts_with($name,'docProps/'))continue;
  if(preg_match('~^xl/worksheets/[^/]+\.xml$~',$name)){
   $documents[$name]=clone $doc;$x=xml_path($doc);foreach($x->query('//s:c') as $cell){$cell->removeAttribute('t');foreach(iterator_to_array($cell->childNodes) as $child)if($child instanceof DOMElement&&in_array($child->localName,['v','is']))$cell->removeChild($child);}$raw=$doc->saveXML();
  }
  $parts[$name]=hash('sha256',$raw);
 }
 ksort($parts);$digest=hash('sha256',json_bytes($parts));$layout=null;
 if($signature!==null&&hash_equals($signature,$digest)){
  $layout=$previous;$layout['sha256']=hash_file('sha256',$workbook);
  foreach($layout['sheets'] as &$sheet){$x=xml_path($documents[$sheet['path']]);foreach($x->query('//s:c') as $cell){$ref=$cell->getAttribute('r');$type=$cell->getAttribute('t');$value=$x->evaluate('string(s:v)',$cell);if($type==='s')$value=$strings[(int)$value];if($type==='inlineStr')$value=$x->evaluate('string(s:is/s:t)',$cell);$sheet['cells'][$ref]['value']=$value;$sheet['cells'][$ref]['type']=$type;}}unset($sheet);
 }
 return ['signature'=>$digest,'layout'=>$layout];
}

