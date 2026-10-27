<?php
declare(strict_types=1);
require __DIR__.'/../lib/app.php';require __DIR__.'/../lib/render.php';
if($argc!==3)throw new RuntimeException('Expected exported path and raw B11 value.');
$source=zip_read(ROOT.'/genstat.xlsx');$export=zip_read($argv[1]);$path=layout()['sheets'][0]['path'];
$d=xml_doc(zip_text($export,$path));$x=xml_path($d);$c=$x->query('//s:c[@r="B11"]')->item(0);
if($x->evaluate('string(s:v)',$c)!==$argv[2]||!in_array($c->getAttribute('t'),['','n']))throw new RuntimeException('Export changed raw numeric B11 value/type.');
$styles=xml_doc(zip_text($export,'xl/styles.xml'));$sx=xml_path($styles);$xf=$sx->query('/s:styleSheet/s:cellXfs/s:xf')->item((int)$c->getAttribute('s'));
if($xf->getAttribute('numFmtId')!=='4'||cell_number_format(['style'=>(int)$c->getAttribute('s')],layout())!=='#,##0.00')throw new RuntimeException('Export changed original B11 number format.');
if(zip_text($source,'xl/styles.xml')!==zip_text($export,'xl/styles.xml'))throw new RuntimeException('Export modified workbook styles.');
$before=xml_doc(zip_text($source,$path));$bx=xml_path($before);$count=0;
foreach($bx->query('//s:f') as $f){$ref=$f->parentNode->getAttribute('r');$after=$x->query('//s:c[@r="'.$ref.'"]/s:f')->item(0);if(!$after||$f->C14N()!==$after->C14N())throw new RuntimeException('Formula changed at '.$ref);$count++;}
echo "PASS: downloaded B11 raw value $argv[2], numeric type, #,##0.00 format and $count formulas verified\n";
