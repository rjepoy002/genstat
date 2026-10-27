<?php
declare(strict_types=1);
require __DIR__.'/../lib/app.php';
require __DIR__.'/../lib/render.php';

function expect_format(string $raw, string $format, string $expected): void {
 $actual=format_numeric_display($raw,number_format_spec($format));
 if($actual!==$expected)throw new RuntimeException("Format $format: $raw expected $expected; got $actual");
 echo "PASS: $raw with $format => $expected\n";
}
require_once __DIR__.'/../tools/build-layout.php';
$l=layout();$formatFixture=build_layout(ROOT.'/genstat.xlsx');$formats=[];
foreach($formatFixture['styles']['cellXfs'] as $i=>$style)$formats[(int)$style['numFmtId']]=cell_number_format(['style'=>$i],$formatFixture);
expect_format('26992328.739999998',$formats[4],'26,992,328.74');
expect_format('1000',$formats[4],'1,000.00');
expect_format('0',$formats[4],'0.00');
expect_format('-1000.125',$formats[4],'-1,000.13');
expect_format('0.123456',$formats[10],'12.35%');
expect_format('-0.125',$formats[9],'-13%');
expect_format('0',$formats[10],'0.00%');
expect_format('1000.123456',$formats[167],'1,000.1235');
foreach([164=>2,165=>0,166=>4] as $id=>$places){
 expect_format('0',$formats[$id],'-');
 expect_format('-1000',$formats[$id],'(1,000'.($places?'.'.str_repeat('0',$places):'').')');
}
expect_format('0',$formats[43],'-');
expect_format('-1000',$formats[43],'-1,000.00');
expect_format('26992328.739999998',$formats[0],'26992328.74');
expect_format('1000',$formats[0],'1000');
expect_format('0',$formats[0],'0');
expect_format('-0.00000001',$formats[0],'-0.00000001');
expect_format('1e-12',$formats[0],'1E-12');
expect_format('1e12',$formats[0],'1E+12');
expect_format('1.2345','0.000','1.235');
expect_format('1000.5','#,##0.##','1,000.5');
expect_format('1000.5','"$"#,##0.00;("$"#,##0.00)','$1,000.50');
expect_format('-1000.5','"$"#,##0.00;("$"#,##0.00)','($1,000.50)');
expect_format('46200','m/d/yy','6/27/26');
expect_format('0.5','h:mm AM/PM','12:00 PM');
expect_format('1.5','[h]:mm:ss','36:00:00');
foreach([
 ['26992328.739999998',$formats[4],'26992328.74'],
 ['1000',$formats[4],'1000.00'],
 ['0',$formats[164],'0.00'],
 ['-1000.125',$formats[164],'-1000.13'],
 ['26992328.739999998','General','26992328.74'],
 ['12.3456','General','12.3456'],
 ['0.123456',$formats[10],'0.123456'],
 ['1000.123456',$formats[167],'1000.1235'],
] as [$raw,$format,$expected]){
 if(numeric_editing_text($raw,number_format_spec($format))!==$expected)throw new RuntimeException('Clean source editing text failed for '.$format);
}
echo "PASS: clean source editing text respects decimals, accounting, General and percentage ratios\n";
// Prove that custom format IDs are resolved from their actual definitions.
$custom=$l;$custom['styles']['cellXfs'][0]['numFmtId']='900';$custom['styles']['numFmts'][]=['numFmtId'=>'900','formatCode'=>'0.000'];
if(display_value(['style'=>0,'type'=>'','value'=>'1.2345'],$custom)!=='1.235')throw new RuntimeException('Custom format resolution failed.');
echo "PASS: custom ID uses its format code rather than an ID shortcut\n";

// Export an untouched binary-serialization value and a high-precision override;
// neither display rounding nor grouping may enter numeric worksheet XML.
$raw='26992328.739999998';$override='12345.678901234567';
if(validate_values(['B11'=>$raw])['B11']!==$raw)throw new RuntimeException('Validation rounded raw input.');
$bytes=export_workbook(['B11'=>$override]);$tmp=tempnam(sys_get_temp_dir(),'genstat-format-');
try{
 file_put_contents($tmp,$bytes);$z=zip_read($tmp);$source=zip_read(workbook_path(ROOT));$path=$l['sheets'][0]['path'];
 $d=xml_doc(zip_text($z,$path));$x=xml_path($d);$cell=$x->query('//s:c[@r="B11"]')->item(0);
 if($x->evaluate('string(s:v)',$cell)!==$override||!in_array($cell->getAttribute('t'),['','n'])||(int)$cell->getAttribute('s')!==$l['sheets'][0]['cells']['B11']['style'])throw new RuntimeException('Export changed numeric precision, type or style.');
 if(zip_text($z,'xl/styles.xml')!==zip_text($source,'xl/styles.xml'))throw new RuntimeException('Export changed number formats.');
 echo "PASS: validation/export preserve raw precision, numeric cell type, style and original number formats\n";
}finally{unlink($tmp);}

