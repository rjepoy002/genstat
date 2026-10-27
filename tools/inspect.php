<?php
require __DIR__.'/../lib/xlsx.php';
$z=zip_read(__DIR__.'/../genstat.xlsx');
$s=xml_doc(zip_text($z,'xl/worksheets/sheet1.xml'));$x=xml_path($s);
$strings=xml_doc(zip_text($z,'xl/sharedStrings.xml'));$sx=xml_path($strings);$ss=[];foreach($sx->query('//s:si') as $si){$t='';foreach($sx->query('.//s:t',$si) as $n)$t.=$n->textContent;$ss[]=$t;}
echo 'Dimension: '.$x->evaluate('string(//s:dimension/@ref)').PHP_EOL;
foreach($x->query('//s:row') as $r){$values=[];foreach($x->query('s:c',$r) as $c){$v=$x->evaluate('string(s:v)',$c);if($c->getAttribute('t')==='s')$v=$ss[(int)$v];$f=$x->evaluate('string(s:f)',$c);if($v!==''||$f!=='')$values[]=$c->getAttribute('r').'[s'.$c->getAttribute('s').'] '.($f!==''?'='.$f:$v);}echo implode(' | ',$values).PHP_EOL;}
echo '\nCOMMENTS\n'.zip_text($z,'xl/comments1.xml');
