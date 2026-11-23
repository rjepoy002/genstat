<?php
declare(strict_types=1);
require_once __DIR__.'/../lib/recovered-sales.php';
require_once __DIR__.'/../lib/base-report.php';

function expect_recovery(bool $condition,string $message): void {
 if(!$condition)throw new RuntimeException($message);
}

$export=workbook_validate(__DIR__.'/recovered-sales-export.xlsx');
$source=zip_read(workbook_path(dirname(__DIR__)));
[$name,,$original]=recov_legacy($source);
[$exportName,,$copy]=recov_legacy($export);
expect_recovery($name===$exportName,'Original recovery worksheet name');
expect_recovery(recov_sheet_rows($copy)===recov_sheet_rows($original,recov_shared_strings($source)),'Original recovery values');
$formulas=fn(DOMDocument $sheet)=>array_map(fn($f)=>[$f->parentNode->getAttribute('r'),$f->textContent],iterator_to_array(xml_path($sheet)->query('//s:f')));
expect_recovery($formulas($copy)===$formulas($original),'Original recovery formulas');
$doc=xml_doc(zip_text($export,RECOV_DATA));$rows=recov_sheet_rows($doc);
$exported=array_filter($rows,fn($row)=>$row['A']===$argv[1]);
$recordRow=array_key_first($exported);
$record=$exported[$recordRow]??[];
expect_recovery(count($exported)===1&&$record['B']==='202607','Selected record and stable ID');
expect_recovery($record['C']==='Aborlan'&&$record['F']==='113.00'&&$record['G']==='rounding & =1+1','Exact area, result and remarks');
expect_recovery(in_array(['F'.$recordRow,'D'.$recordRow.'-E'.$recordRow],$formulas($doc),true),'Remaining formula');
expect_recovery(count(array_filter($rows,fn($row)=>$row['B']==='202608'))===1,'Other months included in annual export');
expect_recovery(!isset($export[RECOV_AUDIT]),'Audit excluded from exports');
workbook_validate(recov_workbook_path(dirname(__DIR__),'202607'));
$columns=recov_area_columns(base_report_layout());
expect_recovery(array_values($columns)===range('B','O')&&$columns['Araceli/Dumaran']==='J'&&$columns['Cuyo/Magsaysay']==='N','Authoritative report column mapping');
$areas=array_keys($columns);
$state=recov_state(dirname(__DIR__),'202607',$areas);
$legacyRows=array_filter($state['records'],fn($row)=>$row['area']==='BPSO');
expect_recovery(count($legacyRows)===3,'Legacy BPSO entries still stored under their original label');
expect_recovery(count($state['audit'])===1&&$state['audit'][0][1]===$argv[1]&&$state['audit'][0][2]==='create','Create audit with stable identifier');
$report=xml_doc(zip_text($export,'xl/worksheets/sheet1.xml'));
$xpath=xml_path($report);
expect_recovery(str_contains($xpath->evaluate('string(//s:c[@r="C22"]/s:f)'),'SUMIFS('),'Aborlan uses the consolidated worksheet');
foreach(['B22'=>'8048.00','C22'=>'123.00','D22'=>'6880.00','Q22'=>'15051'] as $ref=>$expected)
 expect_recovery((float)$xpath->evaluate('string(//s:c[@r="'.$ref.'"]/s:v)')===(float)$expected,'Exported summary '.$ref);
expect_recovery($xpath->evaluate('string(//s:c[@r="Q22"]/s:f)')==='SUM(B22:P22)','Dependent report formula preserved');

$root=dirname(__DIR__);
$testLayout=base_report_layout();
$testLayout['reportMonth']='202607';
$testLayout['recoveryState']=['records'=>[]];
foreach($areas as $index=>$area){
 $testLayout['recoveryState']['records'][]=['month'=>'202607','area'=>$area,'used'=>recov_decimal($index*100)];
}
$testLayout['recoveryState']['records'][]=['month'=>'202608','area'=>'Aborlan','used'=>'999.00'];
$testLayout['recoveryState']['records'][]=['month'=>'202607','area'=>'BPSO','used'=>'888.00'];
$calculation=base_report_calculate($testLayout);
foreach(array_keys(recov_targets($testLayout)) as $ref){
 $index=array_search(substr($ref,0,-2),array_values($columns),true);
 expect_recovery($calculation['cells'][$ref]['display']===number_format($index,2),'Display for '.$ref);
}
expect_recovery((float)$calculation['cells']['Q22']['value']===91.0,'Only this month and supported areas roll up to Q22');
$legacyView=recov_view($state,'202609');
$entries=$legacyView['records'];
foreach($entries as &$entry){
 if($entry['area']==='BPSO')$entry['remarks']='Legacy edit';
}
unset($entry);
$updated=recov_save($root,'202609',['revision'=>$legacyView['revision'],'records'=>$entries,'deleted'=>[]],'fixture',$areas);
expect_recovery($updated['records']===$entries,'Legacy records retain IDs and labels when saved');