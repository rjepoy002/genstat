<?php
declare(strict_types=1);
require __DIR__.'/../lib/app.php';
$zip=zip_read($argv[1]);$original=zip_read(ROOT.'/genstat.xlsx');$path=layout()['sheets'][0]['path'];$d=xml_doc(zip_text($zip,$path));$x=xml_path($d);
foreach(['C39'=>'ROUND(B11/3,2)','Q11'=>'SUM(B11:P11)+1','H12'=>'H11+2','G12'=>'+G11','I12'=>'+I11'] as $ref=>$expected)if($x->evaluate('string(//s:c[@r="'.$ref.'"]/s:f)')!==$expected)throw new RuntimeException('Formula export mismatch at '.$ref);
if(zip_text($zip,'xl/styles.xml')!==zip_text($original,'xl/styles.xml'))throw new RuntimeException('Styles changed.');
$source=xml_doc(zip_text($original,$path));$sx=xml_path($source);foreach(['mergeCells','cols','pageMargins','pageSetup'] as $tag)if($x->query('//s:'.$tag)->item(0)->C14N()!==$sx->query('//s:'.$tag)->item(0)->C14N())throw new RuntimeException('Layout metadata changed: '.$tag);
echo "PASS: downloaded workbook retains formulas, shared siblings, styles, merges, column widths and print settings\n";
