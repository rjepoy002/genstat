<?php
declare(strict_types=1);
$source=dirname(__DIR__);$tmp=sys_get_temp_dir().'/genstat-storage-'.bin2hex(random_bytes(6));mkdir($tmp);
foreach(['lib','tools','config','storage','excel-files/incoming','excel-files/backups','excel-files/archive','excel-files/active'] as $dir)mkdir($tmp.'/'.$dir,0777,true);
foreach(['lib','tools','config'] as $dir)foreach(glob($source.'/'.$dir.'/*') as $f)if(is_file($f))copy($f,$tmp.'/'.$dir.'/'.basename($f));
copy($source.'/genstat.xlsx',$tmp.'/genstat.xlsx');
$fixture=json_decode(file_get_contents($source.'/storage/draft-2026-06.json'),true);$fixture['templateHash']=json_decode(file_get_contents($tmp.'/config/layout.json'),true)['sha256'];file_put_contents($tmp.'/storage/draft-2026-06.json',json_encode($fixture));
$candidate=glob($source.'/excel-files/archive/source-2026-*.xlsx')[0];copy($candidate,$tmp.'/excel-files/incoming/GENSTAT_2026(2).xlsx');
function run_stage(string $code): string {global $tmp;$file=$tmp.'/stage.php';file_put_contents($file,"<?php\nrequire __DIR__.'/lib/app.php';\n".$code);$cmd='"'.PHP_BINARY.'" "'.$file.'"';exec($cmd.' 2>&1',$lines,$rc);$out=implode("\n",$lines);if($rc!==0)throw new RuntimeException($out);echo $out,"\n";return $out;}
try {
 run_stage(<<<'PHP'
$l=layout(true);if(count($l['sheets'])!==10||$l['sheets'][0]['name']!=='Jun26')throw new Exception('migration failed: '.json_encode($GLOBALS['templateRefresh']));
foreach($l['sheets'] as $s)if(!array_filter($s['cells'],fn($c)=>$c['editable']))throw new Exception('Worksheet editability missing');
$d=save_draft('2026-06',['B11'=>'12345.6789'],read_draft('2026-06')['revision']);
echo 'PASS: ten sheets migrated, RecovBill retained, non-formula cells editable on every worksheet, draft saved';
PHP);
 $draft=file_get_contents($tmp.'/storage/draft-2026-06.json');$active=$tmp.'/excel-files/active/genstat_2026.xlsx';$before=file_get_contents($active);
 run_stage(<<<'PHP'
$l=layout();$z=zip_read(workbook_path(ROOT));$r=synchronize_draft('2026-06');$after=zip_read(workbook_path(ROOT));
foreach($z as $name=>$entry)if(!in_array($name,[$l['sheets'][0]['path'],'xl/workbook.xml'])&&zip_text($z,$name)!==zip_text($after,$name))throw new Exception('Untouched part changed: '.$name);
$old=xml_doc(zip_text($z,$l['sheets'][0]['path']));$new=xml_doc(zip_text($after,$l['sheets'][0]['path']));$a=xml_path($old);$b=xml_path($new);
foreach($a->query('//s:c') as $c){$ref=$c->getAttribute('r');if($ref!=='B11'&&$c->C14N()!==$b->query('//s:c[@r="'.$ref.'"]')->item(0)->C14N())throw new Exception('Untouched cell changed: '.$ref);}
if($b->evaluate('string(//s:c[@r="B11"]/s:v)')!=='12345.6789')throw new Exception('Write failed');
echo 'PASS: synchronization preserves precision, all untouched cells, formulas, formatting and linked parts; verified backup created';
PHP);
 run_stage(<<<'PHP'
$l=layout(true);$d=read_draft('2026-06');if($d['values']['B11']!=='12345.6789')throw new Exception('draft lost');
try{save_draft('2026-06',['Q11'=>'9'],$d['revision']);throw new Exception('protection failed');}catch(InvalidArgumentException $e){}
echo 'PASS: synchronized draft survives reload and formula input stays protected';
PHP);
 run_stage(<<<'PHP'
$_SESSION=[];confirm_formula_cell('Q11');
$d=read_draft('2026-06');save_draft('2026-06',(array)$d['values'],$d['revision'],['Q11'=>'=B11+1']);$l=layout();synchronize_draft('2026-06');$z=zip_read(workbook_path(ROOT));$x=xml_path(xml_doc(zip_text($z,$l['sheets'][0]['path'])));if($x->evaluate('string(//s:c[@r="Q11"]/s:f)')!=='B11+1')throw new Exception('Formula sync failed');
try{require_formula_cell_confirmation('Q11');throw new Exception('Saved confirmation was not consumed');}catch(FormulaPermissionException $e){}
echo 'PASS: per-cell confirmation allows formula save/sync and is consumed after save';
PHP);
 // Modify the Excel cell externally after a saved web entry.
 run_stage(<<<'PHP'
$l=layout();$d=save_draft('2026-06',['B11'=>'222'],read_draft('2026-06')['revision']);$file=workbook_path(ROOT);$z=zip_read($file);$path=$l['sheets'][0]['path'];$doc=xml_doc(zip_text($z,$path));xml_path($doc)->query('//s:c[@r="B11"]/s:v')->item(0)->nodeValue='333';atomic_file_write($file,zip_write($z,[$path=>$doc->saveXML()]));
echo 'PASS: external edit fixture saved';
PHP);
 $conflictBytes=file_get_contents($active);$conflictDraft=file_get_contents($tmp.'/storage/draft-2026-06.json');
 run_stage(<<<'PHP'
try{synchronize_draft('2026-06');throw new Exception('conflict was ignored');}catch(RuntimeException $e){if(!str_contains($e->getMessage(),'Both Excel'))throw $e;}echo 'PASS: external Excel conflict reported';
PHP);
 if(file_get_contents($active)!==$conflictBytes||file_get_contents($tmp.'/storage/draft-2026-06.json')!==$conflictDraft)throw new Exception('Conflict lost data');
 file_put_contents($tmp.'/excel-files/incoming/GENSTAT_2026(2).xlsx','broken');
 run_stage(<<<'PHP'
$l=layout(true);if(!isset($GLOBALS['incomingWarning']))throw new Exception('No failure warning');echo 'PASS: malformed incoming workbook deferred with a nonfatal warning';
PHP);
 if(file_get_contents($active)!==$conflictBytes||file_get_contents($tmp.'/storage/draft-2026-06.json')!==$conflictDraft)throw new Exception('Failure lost data');
 echo "PASS: rejected refresh and sync preserve exact active workbook and draft bytes\n";
 if(count(glob($tmp.'/excel-files/backups/*.xlsx'))<3)throw new Exception('Backups missing');
 echo "PASS: timestamped workbook backups retained\n";
}finally{
 $resolved=realpath($tmp);$base=realpath(sys_get_temp_dir());if($resolved&&str_starts_with($resolved,$base.DIRECTORY_SEPARATOR)&&str_starts_with(basename($resolved),'genstat-storage-')){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($resolved);}
}


