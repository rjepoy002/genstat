<?php
declare(strict_types=1);
require __DIR__.'/../lib/workbook-storage.php';require __DIR__.'/../lib/monthly-worksheets.php';
function month_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException('FAIL: '.$label);echo 'PASS: '.$label,"\n";}
$source=dirname(__DIR__);$root=sys_get_temp_dir().'/genstat-monthly-'.bin2hex(random_bytes(6));mkdir($root);
foreach(['config','storage','excel-files/active','excel-files/incoming','excel-files/backups','excel-files/archive'] as $dir)mkdir($root.'/'.$dir,0777,true);
copy($source.'/config/inputs.php',$root.'/config/inputs.php');copy($source.'/config/layout.json',$root.'/config/layout.json');
$active=$root.'/excel-files/active/genstat_2026.xlsx';copy($source.'/excel-files/active/genstat_2026.xlsx',$active);$incoming=$root.'/excel-files/incoming/GENSTAT_2026.xlsx';
try{
 $old=build_layout($active,$root.'/config/inputs.php');$sep=array_values(array_filter($old['sheets'],fn($s)=>$s['name']==='Sep26'))[0]??null;if(!$sep)throw new RuntimeException('Test fixture requires the existing September worksheet.');
 $next=$old;$next['sheets']=array_values(array_filter($old['sheets'],fn($s)=>$s['name']!=='Sep26'));
 $none=fn($year,$month)=>['state'=>'no-records','tables'=>['overallsalessummary'=>['records'=>0],'billadjustment'=>['records'=>0]],'complete'=>false];
 $partial=fn($year,$month)=>['state'=>'partial-records','tables'=>['overallsalessummary'=>['records'=>552],'billadjustment'=>['records'=>356]],'complete'=>false];
 $review=missing_monthly_review($root,$old,$next,$none);month_check(count($review['months'])===1&&$review['months'][0]['message']==='No data available for September 2026.','Missing September receives the exact no-data message without a fatal deletion exception');
 month_check(!$review['canApply']&&!$review['canRegenerate']&&$review['months'][0]['preserveExistingSheet'],'No empty worksheet is fabricated and the existing September sheet is retained');
 $review=missing_monthly_review($root,$old,$next,$partial);month_check($review['months'][0]['source']['tables']['billadjustment']['records']===356&&!$review['canRegenerate'],'Available sales AND adjustment records cannot substitute for missing approved worksheet mappings');
 $missingSupport=$old;$missingSupport['sheets']=array_values(array_filter($old['sheets'],fn($s)=>$s['name']!=='RecovBill26'));month_check(missing_monthly_sheets($old,$missingSupport)===[],'Supporting worksheet deletion is not misclassified as monthly generation');
 month_check(monthly_sheet_period('Sep25')===null&&monthly_sheet_period('Sept26')===null,'Year and month naming conventions are enforced');
 $draftFile=$root.'/storage/draft-2026-09--'.substr(hash('sha256','Sep26'),0,16).'.json';
 $draft=['period'=>'2026-09','sheet'=>'Sep26','templateHash'=>$old['sha256'],'values'=>['B11'=>'654.321'],'formulas'=>['Q11'=>'=ROUND(1/3,2)'],'sourceCells'=>[],'revision'=>5,'savedAt'=>'2026-10-27T01:00:00Z'];file_put_contents($draftFile,json_encode($draft));$draftBytes=file_get_contents($draftFile);
 $withDependency=$old;foreach($withDependency['sheets'] as &$s)if($s['name']==='Aug26')$s['cells']['ZZ1']=['formula'=>"'Sep26'!B11"];unset($s);
 $review=missing_monthly_review($root,$withDependency,$next,$partial);month_check($review['requiresConfirmation']&&count($review['conflicts'])>=2&&in_array('Aug26!ZZ1',$review['months'][0]['dependentCells'],true),'Saved values, formula drafts and dependent worksheets require explicit reconciliation/confirmation; no apply bypass exists');
 month_check(file_get_contents($draftFile)===$draftBytes,'Assessment preserves exact saved draft values, formulas and revision');
 // Build a missing-sheet package without rewriting any other worksheet part.
 $zip=zip_read($active);$doc=xml_doc(zip_text($zip,'xl/workbook.xml'));$x=xml_path($doc);foreach(iterator_to_array($x->query('//s:sheets/s:sheet')) as $sheet)if($sheet->getAttribute('name')==='Sep26')$sheet->parentNode->removeChild($sheet);
 file_put_contents($incoming,zip_write($zip,['xl/workbook.xml'=>$doc->saveXML()]));
 $cacheFile=$root.'/storage/template-auto.json';file_put_contents($cacheFile,'{"marker":"preserve-this-cache"}');$cacheBytes=file_get_contents($cacheFile);$activeBytes=file_get_contents($active);$incomingBytes=file_get_contents($incoming);
 // Deterministic unavailable MySQL endpoint: an unavailable source is NOT zero data.
 file_put_contents($root.'/config/monthly-source.php',"<?php return ['connection'=>['host'=>'127.0.0.1','port'=>1,'database'=>'unavailable','user'=>'unavailable','password'=>''],'sources'=>[],'worksheetMappings'=>[]];");
 $unavailable=monthly_source_availability($root,2026,9);month_check($unavailable['state']==='unavailable'&&!$unavailable['complete'],'Unreachable MySQL is reported as unavailable rather than inventing zero totals');
 storage_refresh($root,$old);
 month_check(str_contains($GLOBALS['incomingWarning'],'No data available for September 2026.')&&!str_contains($GLOBALS['incomingWarning'],'Existing worksheet was removed or renamed'),'Incoming monthly deletion is a nonfatal staged availability warning');
 month_check(str_contains($GLOBALS['incomingWarning'],'Conflict:')&&$GLOBALS['monthlyWorksheetReview']['requiresConfirmation'],'A September draft causes a visible conflict/review warning');
 month_check(file_get_contents($active)===$activeBytes&&file_get_contents($incoming)===$incomingBytes&&file_get_contents($draftFile)===$draftBytes&&file_get_contents($cacheFile)===$cacheBytes,'Failed/unsupported generation leaves active, incoming, draft and cache bytes unchanged');
 month_check(glob($root.'/excel-files/backups/*')===[]&&glob($root.'/excel-files/archive/*')===[],'An unapplied missing-month assessment neither replaces workbooks nor produces repeated replacement backups');
 $after=build_layout($active,$root.'/config/inputs.php');month_check($after['sheets']===$old['sheets']&&$after['styles']===$old['styles']&&$after['definedNames']===$old['definedNames']&&$after['externalLinks']===$old['externalLinks'],'All September formulas, formats, merges, dependencies and unrelated worksheets remain intact');
 if(in_array('--mysql',$argv,true)){
  $live=monthly_source_availability($source,2026,9);month_check($live['state']==='partial-records'&&$live['tables']['overallsalessummary']['records']>0&&$live['tables']['billadjustment']['records']>0,'Read-only live MySQL inspection finds September rows in both observed tables');
  $empty=monthly_source_availability($source,2026,8);month_check($empty['state']==='no-records'&&!$empty['complete'],'Read-only live MySQL inspection handles an unavailable source period without generating a worksheet');
 }
}finally{
 $resolved=realpath($root);$temp=realpath(sys_get_temp_dir());if($resolved&&str_starts_with($resolved,$temp.DIRECTORY_SEPARATOR)&&str_starts_with(basename($resolved),'genstat-monthly-')){$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($resolved,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){if($f->isDir())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($resolved);}
}
