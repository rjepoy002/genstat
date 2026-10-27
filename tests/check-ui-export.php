<?php
declare(strict_types=1);
require __DIR__.'/../lib/app.php';
$source=zip_read(ROOT.'/genstat.xlsx');$export=zip_read($argv[1]);$part=layout()['sheets'][0]['path'];$before=xml_doc(zip_text($source,$part));$after=xml_doc(zip_text($export,$part));$bx=xml_path($before);$ax=xml_path($after);
if($ax->evaluate('string(//s:c[@r="B11"]/s:v)')!=='1000.123'||!in_array($ax->evaluate('string(//s:c[@r="B11"]/@t)'),['','n']))throw new RuntimeException('Raw manual numeric value/type changed.');
if($ax->evaluate('string(//s:c[@r="Q11"]/s:f)')!=='ROUND(B11/3,2)')throw new RuntimeException('Authorized formula was not exported.');
if(zip_text($source,'xl/styles.xml')!==zip_text($export,'xl/styles.xml'))throw new RuntimeException('Styles/number formats changed.');
foreach(['cols','mergeCells','pageMargins','pageSetup'] as $tag)if($bx->query('//s:'.$tag)->item(0)->C14N()!==$ax->query('//s:'.$tag)->item(0)->C14N())throw new RuntimeException('Worksheet layout changed: '.$tag);
$count=0;foreach($bx->query('//s:f') as $f){$ref=$f->parentNode->getAttribute('r');if($ref==='Q11')continue;$new=$ax->query('//s:c[@r="'.$ref.'"]/s:f')->item(0);if(!$new||$new->C14N()!==$f->C14N())throw new RuntimeException('Unexpected formula change: '.$ref);$count++;}
foreach(['xl/externalLinks/externalLink1.xml','xl/externalLinks/externalLink2.xml','xl/externalLinks/_rels/externalLink1.xml.rels','xl/externalLinks/_rels/externalLink2.xml.rels'] as $name)if(zip_text($source,$name)!==zip_text($export,$name))throw new RuntimeException('External/year-specific references changed.');
echo "PASS: exported numeric precision, authorized formula, $count other formulas, styles, merges, print settings and year-specific external references preserved\n";
