<?php
declare(strict_types=1);
require __DIR__.'/../lib/app.php';require __DIR__.'/../tools/build-layout.php';
function check(bool $ok,string $message): void {if(!$ok)throw new RuntimeException('FAIL: '.$message);echo 'PASS: '.$message.PHP_EOL;}
$l=layout();$rebuilt=sync_reconcile($l,build_layout(workbook_path(ROOT)));$sourceLayout=$l;unset($sourceLayout['coordinateMaps']);unset($rebuilt['coordinateMaps'],$rebuilt['coordinateAxes'],$sourceLayout['coordinateAxes']);$sourceLayout['template']=$rebuilt['template'];check($sourceLayout===$rebuilt,'Source layout matches fresh extraction apart from derived reconciliation metadata');
$s=$l['sheets'][0];check($s['name']==='Jun26'&&$s['range']==='A1:AG99','Source name and full used range');check(count($s['merges'])===16,'All 16 merge ranges extracted');
$inputs=array_filter($s['cells'],fn($c)=>$c['editable']);check(count($inputs)>17,'All non-formula cells are editable, beyond the legacy allowlist');check($inputs['C39']['value']===''&&$inputs['E39']['value']==='','Blank verified input cells retained');
foreach($inputs as $ref=>$c)check($c['formula']===null&&!$c['formulaProtected'],"$ref is non-formula and editable");
foreach([['Q11'=>'2'],['ZZ999'=>'2'],['B11'=>'=1+1'],['B11'=>'1e999'],['B11'=>12]] as $bad){try{validate_values($bad);throw new RuntimeException('Bad value accepted');}catch(InvalidArgumentException $e){}}
check(true,'Unknown/protected cells, formulas, nonfinite and nonstring values rejected');
$masterHash=hash_file('sha256',workbook_path(ROOT));$original=zip_read(workbook_path(ROOT));$bytes=export_workbook(['B11'=>'12345.67','C39'=>'98.5','E39'=>'']);$tmp=tempnam(sys_get_temp_dir(),'genstat-test-');file_put_contents($tmp,$bytes);$export=zip_read($tmp);unlink($tmp);
check(array_keys($original)===array_keys($export),'Export retains every ZIP part');
foreach($export as $name=>$entry){$raw=zip_text($export,$name);check(strlen($raw)===$entry['header']['size']&&sprintf('%u',crc32($raw))===sprintf('%u',$entry['header']['crc']),"ZIP length/CRC valid: $name");if(!in_array($name,[$s['path'],'xl/workbook.xml']))check(zip_text($original,$name)===$raw,"Unchanged part: $name");if(str_ends_with($name,'.xml')||str_ends_with($name,'.rels'))xml_doc($raw);}
$before=xml_doc(zip_text($original,$s['path']));$after=xml_doc(zip_text($export,$s['path']));$bx=xml_path($before);$ax=xml_path($after);
check($ax->evaluate('string(//s:c[@r="B11"]/s:v)')==='12345.67'&&$ax->evaluate('string(//s:c[@r="C39"]/s:v)')==='98.5','Export writes populated and blank inputs to original positions');check($ax->query('//s:c[@r="E39"]/s:v')->length===0,'Cleared inputs export as blank');
$count=0;foreach($bx->query('//s:f') as $f){$ref=$f->parentNode->getAttribute('r');$new=$ax->query('//s:c[@r="'.$ref.'"]/s:f')->item(0);check($f->C14N()===$new->C14N(),'Formula preserved: '.$ref);$count++;}
foreach($bx->query('//s:c') as $c){$ref=$c->getAttribute('r');if(in_array($ref,['B11','C39','E39']))continue;check($c->C14N()===$ax->query('//s:c[@r="'.$ref.'"]')->item(0)->C14N(),'Cell unchanged: '.$ref);}
foreach($bx->query('/s:worksheet/*[not(self::s:sheetData)]') as $n){$new=$ax->query('/s:worksheet/s:'.$n->localName)->item(0);check($n->C14N()===$new->C14N(),'Sheet metadata preserved: '.$n->localName);}
check(hash_file('sha256',workbook_path(ROOT))===$masterHash,'Master template hash unchanged');echo "FORMULAS VERIFIED: $count\n";

