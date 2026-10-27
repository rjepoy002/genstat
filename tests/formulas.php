<?php
declare(strict_types=1);
require __DIR__.'/../lib/app.php';
function yes(bool $ok,string $message): void{if(!$ok)throw new RuntimeException('FAIL: '.$message);echo 'PASS: '.$message.PHP_EOL;}
$l=layout();$sheet=$l['sheets'][0]['name'];$hash=hash_file('sha256',workbook_path(ROOT));
foreach(['=SUM(B11:D11)'=>30713708.5,'=C10+D10'=>0,'=C10'=>0,'=COUNT(C10:D10)'=>0,'=SUM(TRUE,"2")'=>3,'=IF(B11>0,2,0)'=>2,'=ROUND(37/3,2)'=>12.33,"='Jun26'!D11"=>2678729,'=IFERROR(1/0,9)'=>9,'=SUM(1,2,3)^2'=>36,'=-2^2'=>4] as $formula=>$expected){
 $e=new FormulaEngine($l,[],['C39'=>$formula]);$e->validate($formula,$sheet);yes(abs((float)$e->value($sheet,'C39')-$expected)<0.00001,'Safe evaluation: '.$formula);
}
$fixture=$l;$second=$l['sheets'][0];$second['name']='Sheet 2';$second['cells']['B5']['value']='123.456';$second['cells']['B5']['type']='';$fixture['sheets'][]=$second;
$e=new FormulaEngine($fixture,[],['C39'=>"='Sheet 2'!B5"]);$e->validate("='Sheet 2'!B5",$sheet);yes($e->value($sheet,'C39')===123.456,'Quoted cross-sheet reference resolves existing worksheet names');
$source=expanded_formulas($l['sheets'][0]);yes($source['H12']==='=+H11','Shared formula followers expand with relative references');
yes(shifted_formula('SUM($B$11:C11)+$D12+E$9','B1','C2')==='SUM($B$11:D12)+$D13+F$9','Shared translation preserves mixed absolute/relative references');
$a=new FormulaEngine($l,[],['C39'=>'=ROUND(10/3,2)']);$result=$a->results();yes($result[$sheet]['C39']['value']===3.33,'Blank input formula evaluates');
$a=new FormulaEngine($l,['B11'=>'1000'],['C39'=>'=B11*2']);$result=$a->results();yes($result[$sheet]['C39']['value']===2000.0&&$result[$sheet]['B12']['value']===1000.0,'Manual input edits recalculate new and existing dependent formulas');
foreach([['C39'=>'=SUM('],['C39'=>'==1'],['C39'=>'=WEBSERVICE("https://example.invalid")'],['C39'=>'=C39+1'],['C39'=>'=Q39+1'],['C39'=>'=IF(FALSE,C39,0)'],['C39'=>'=E39+1','E39'=>'=C39+1'],['C39'=>'=MissingSheet!A1'],['C39'=>'=A9999999']] as $bad){
 try{validate_formula_overlays($bad,[]);throw new RuntimeException('Bad formula accepted: '.json_encode($bad));}catch(InvalidArgumentException $e){}
}yes(true,'Invalid, unsupported, cyclic, hidden-branch cycles, unknown sheet/range and unapproved coordinates are rejected');
try{validate_formula_overlays(['Q11'=>'=2'],[],[],true);throw new RuntimeException('Authorization bypass');}catch(FormulaPermissionException $e){}yes(true,'Original formula changes require confirmation for that cell');
validate_formula_overlays(['C39'=>'=ROUND(37/3,2)'],[],[],true);yes(true,'Approved blank inputs accept formulas without widening any other blank permissions');
$bytes=export_workbook([],['C39'=>'=ROUND(37/3,2)','H12'=>'=H11+1']);$file=tempnam(sys_get_temp_dir(),'genstat-formula-');
try{file_put_contents($file,$bytes);$z=zip_read($file);$d=xml_doc(zip_text($z,$l['sheets'][0]['path']));$x=xml_path($d);
 yes($x->evaluate('string(//s:c[@r="C39"]/s:f)')==='ROUND(37/3,2)'&&$x->evaluate('string(//s:c[@r="H12"]/s:f)')==='H11+1','Excel export retains inserted and modified actual formulas');
 yes($x->evaluate('string(//s:c[@r="G12"]/s:f)')==='+G11'&&$x->evaluate('string(//s:c[@r="I12"]/s:f)')==='+I11','Editing a shared member preserves sibling formula semantics');
 yes(!isset($z['xl/calcChain.xml']),'Stale calculation chain is invalidated for formula edits');
 $original=zip_read(workbook_path(ROOT));yes(zip_text($z,'xl/styles.xml')===zip_text($original,'xl/styles.xml'),'Formula export preserves original number formats and styles');
 foreach($z as $name=>$entry){$text=zip_text($z,$name);yes(strlen($text)===$entry['header']['size']&&sprintf('%u',crc32($text))===sprintf('%u',$entry['header']['crc']),'ZIP validity: '.$name);}
}finally{unlink($file);}
yes(hash_file('sha256',workbook_path(ROOT))===$hash,'Original workbook fixture remains unmodified');

