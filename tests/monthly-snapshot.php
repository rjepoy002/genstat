<?php
declare(strict_types=1);

if(PHP_SAPI!=='cli')exit;

require_once __DIR__.'/../lib/files.php';
require_once __DIR__.'/../lib/base-report-view.php';

function check(bool $result,string $description): void {
 if(!$result)throw new RuntimeException($description);
 echo 'PASS: '.$description.PHP_EOL;
}

$root=sys_get_temp_dir().'/genstat-snapshot-test-'.bin2hex(random_bytes(12));
mkdir($root,0700);
mkdir($root.'/lib',0700);
mkdir($root.'/storage',0700);
copy(__DIR__.'/../lib/monthly-snapshot.php',$root.'/lib/monthly-snapshot.php');
require $root.'/lib/monthly-snapshot.php';

try{
 $layout=['reportMonth'=>'202607','sha256'=>str_repeat('a',64),'sheets'=>[['name'=>'Jul26']]];
 $state=['draftRevision'=>2,'values'=>['B11'=>'25'],'formulas'=>[],'sqlReplacements'=>[],
  'sqlSources'=>[],'formulaDefinitions'=>['Q11'=>'=SUM(B11:P11)'],
  'calculation'=>['cells'=>['Q11'=>['value'=>25,'display'=>'25.00','error'=>null]],'recoveredSales'=>new stdClass()],
  'context'=>['total'=>['B18'=>'101.00']],'effectiveTotals'=>['B18'=>'101.00']];
 $draftPath=$root.'/storage/base-report-'.base_report_scope($layout).'.json';
 atomic_file_write($draftPath,json_bytes(['revision'=>8,'savedAt'=>gmdate('c')]));
 check(monthly_snapshot_read('202607')===null&&monthly_snapshot_months()===[],
  'Draft revision and timestamp do not establish an explicit snapshot');
 $first=monthly_snapshot_save($layout,0,$state);
 check($first['revision']===1&&monthly_snapshot_read('202607',$layout)===$first,
  'Explicit Save persists the complete state and revision');
 $path=monthly_snapshot_path('202607');
 $bytes=file_get_contents($path);
 try{
  monthly_snapshot_save($layout,0,$state);
  throw new LogicException('A stale Save unexpectedly succeeded.');
 }catch(MonthlySnapshotError){
  check(file_get_contents($path)===$bytes,'A stale Save preserves existing snapshot bytes');
 }
 $august=array_replace($layout,['reportMonth'=>'202608']);
 monthly_snapshot_save($august,0,array_replace($state,['effectiveTotals'=>['B18'=>'201.00']]));
 check(monthly_snapshot_read('202608')['effectiveTotals']['B18']==='201.00'&&
  monthly_snapshot_read('202607')['effectiveTotals']['B18']==='101.00','Snapshots are isolated by year and month');
 atomic_file_write($path,str_replace('101.00','999.00',$bytes));
 try{
  monthly_snapshot_read('202607',$layout);
  throw new LogicException('A snapshot with a wrong checksum was accepted.');
 }catch(MonthlySnapshotError){
  check(true,'Checksum mismatch raises a snapshot error');
 }
 check(is_file($draftPath),'Snapshot operations preserve the draft file');
}finally{
 foreach(glob($root.'/storage/*')?:[] as $file)unlink($file);
 unlink($root.'/lib/monthly-snapshot.php');
 rmdir($root.'/lib');rmdir($root.'/storage');rmdir($root);
}
