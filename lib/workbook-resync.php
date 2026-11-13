<?php
declare(strict_types=1);
require_once __DIR__.'/../tools/build-layout.php';
require_once __DIR__.'/sync-mapping.php';

function workbook_sync_time(): ?string {
 $file=ROOT.'/storage/workbook-sync.json';
 if(is_file($file)){$record=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);if(is_string($record['synchronizedAt']??null))return $record['synchronizedAt'];}
 $times=[];foreach(glob(ROOT.'/storage/draft-*.json')?:[] as $draft){$d=json_decode(file_get_contents($draft),true);if(is_string($d['synchronizedAt']??null))$times[]=$d['synchronizedAt'];}
 rsort($times);return $times[0]??null;
}

// Preview deliberately does not call layout(): Cancel must not promote an
// incoming upload, refresh caches, save a draft or create a workbook backup.
function resync_snapshot(string $p): array {
 template_guard();period($p);
 $baselineRaw=file_get_contents(ROOT.'/config/layout.json');$baseline=json_decode($baselineRaw,true,512,JSON_THROW_ON_ERROR);
 $cacheFile=ROOT.'/storage/template-auto.json';$cacheRaw=is_file($cacheFile)?file_get_contents($cacheFile):null;$cache=$cacheRaw!==null?json_decode($cacheRaw,true,512,JSON_THROW_ON_ERROR):null;
 $verified=($cache['baseline']??null)===$baseline['sha256']?($cache['layout']??$baseline):$baseline;
 $active=workbook_path(ROOT);$hash=hash_file('sha256',$active);$inputs=hash_file('sha256',ROOT.'/config/inputs.php');
 if($inputs!==$verified['inputDefinitionsHash'])throw new DomainException('Input rules changed. Resolve the configuration before resyncing.');
 workbook_validate($active);$next=sync_reconcile($verified,build_layout($active,ROOT.'/config/inputs.php'));
 $name=$_GET['sheet']??$verified['sheets'][0]['name'];if(!is_string($name))throw new InvalidArgumentException('Invalid worksheet.');sheet_named($next,$name);
 $path=ROOT.'/storage/draft-'.$p.($name===$verified['sheets'][0]['name']?'':'--'.substr(hash('sha256',$name),0,16)).'.json';
 $draftRaw=is_file($path)?file_get_contents($path):null;$draft=$draftRaw!==null?json_decode($draftRaw,true,512,JSON_THROW_ON_ERROR):['revision'=>0,'values'=>[],'formulas'=>[]];$drafts=[];
 foreach(glob(ROOT.'/storage/draft-*.json')?:[] as $file){$raw=file_get_contents($file);$drafts[basename($file)]=hash('sha256',$raw);if(realpath($file)!==realpath($path))sync_project_draft(json_decode($raw,true,512,JSON_THROW_ON_ERROR),$baseline,$next);}
 ksort($drafts);
 if($hash!==$next['sha256']||$hash!==hash_file('sha256',$active)||$inputs!==hash_file('sha256',ROOT.'/config/inputs.php'))throw new DomainException('Excel changed while preparing Resync. Review a fresh confirmation.');
 return ['layout'=>$next,'baseline'=>$baseline,'path'=>$path,'draft'=>$draft,'draftRaw'=>$draftRaw,'cacheRaw'=>$cacheRaw,'sheet'=>$name,
  'fingerprint'=>['workbook'=>$hash,'drafts'=>$drafts,'inputs'=>$inputs,'baseline'=>hash('sha256',$baselineRaw),'cache'=>hash('sha256',json_bytes($verified))],
  'synchronizedAt'=>workbook_sync_time(),'verifiedAt'=>$cache['status']['syncedAt']??null];
}
function resync_preview(string $p): array {
 $snapshot=resync_snapshot($p);$token=bin2hex(random_bytes(32));
 $_SESSION['resyncConfirmation']=['token'=>$token,'period'=>$p,'sheet'=>$snapshot['sheet'],'fingerprint'=>$snapshot['fingerprint'],'expires'=>time()+300];
 return ['token'=>$token,'hash'=>$snapshot['fingerprint']['workbook'],'workbook'=>basename(workbook_path(ROOT)),
  'synchronizedAt'=>$snapshot['synchronizedAt'],'verifiedAt'=>$snapshot['verifiedAt'],
  'draftSavedAt'=>$snapshot['draft']['savedAt']??null,'draftRevision'=>$snapshot['draft']['revision'],
  'draftChanges'=>count((array)($snapshot['draft']['values']??[]))+count((array)($snapshot['draft']['formulas']??[]))];
}
function resync_confirm(string $p,string $token): array {
 $confirmation=$_SESSION['resyncConfirmation']??null;
 if(!$confirmation||!hash_equals($confirmation['token'],$token)||$confirmation['expires']<time()||$confirmation['period']!==$p)throw new DomainException('Resync confirmation expired. Review a fresh confirmation.');
 $snapshot=resync_snapshot($p);
 if($confirmation['sheet']!==$snapshot['sheet']||$confirmation['fingerprint']!==$snapshot['fingerprint'])throw new DomainException('Conflict: Excel or a saved draft changed after confirmation opened. Nothing was discarded. Review Resync again.');
 $lock=fopen(ROOT.'/storage/sync.lock','c');if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Cannot lock Resync.');
 $written=false;
 try {
  $backup=ROOT.'/storage/backups/resync-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6));if(!mkdir($backup,0777,true))throw new RuntimeException('Cannot back up the discarded draft.');
  foreach(['draft.json'=>$snapshot['draftRaw'],'template-auto.json'=>$snapshot['cacheRaw']] as $name=>$raw)if($raw!==null){atomic_file_write($backup.'/'.$name,$raw);if(hash_file('sha256',$backup.'/'.$name)!==hash('sha256',$raw))throw new RuntimeException('Resync backup verification failed.');}
  $workbookBackup=workbook_backup(ROOT,workbook_path(ROOT),'before-resync');
  // Recheck the entire snapshot after backup, including concurrent external edits.
  $check=resync_snapshot($p);if($check['fingerprint']!==$snapshot['fingerprint'])throw new DomainException('Conflict: Excel or drafts changed during Resync backup. Nothing was discarded.');
  $next=$snapshot['layout'];$draft=['period'=>$p,'sheet'=>$snapshot['sheet'],'templateHash'=>$next['sha256'],'values'=>(object)[],'formulas'=>(object)[],
   'formulaAudit'=>$snapshot['draft']['formulaAudit']??[],'sourceCells'=>[], 'revision'=>$snapshot['draft']['revision']+1,'savedAt'=>null,'resyncedAt'=>gmdate('c'),'resyncBackup'=>basename($backup)];
  $stat=stat(workbook_path(ROOT));$signature=auto_template_content(ROOT,$next,null,workbook_path(ROOT))['signature'];
  $status=['state'=>'ready','message'=>'Resynced from the active Excel workbook.','workbook'=>basename(workbook_path(ROOT)),'syncedAt'=>$snapshot['synchronizedAt']??$snapshot['verifiedAt'],'changes'=>[]];
  $cache=['baseline'=>$snapshot['baseline']['sha256'],'layout'=>$next,'observedHash'=>$next['sha256'],'checkedAt'=>time(),
   'extractor'=>hash('sha256',hash_file('sha256',__DIR__.'/../tools/build-layout.php').hash_file('sha256',__DIR__.'/sync-mapping.php').hash_file('sha256',__DIR__.'/workbook-policy.php')),
   'stamp'=>[$stat['mtime'],$stat['ctime'],$stat['size'],$next['inputDefinitionsHash']],'contentSignature'=>$signature,'status'=>$status];
  if(hash_file('sha256',workbook_path(ROOT))!==$next['sha256'])throw new DomainException('Conflict: Excel changed before Resync. Nothing was discarded.');
  atomic_file_write($snapshot['path'],json_bytes($draft));$written=true;atomic_file_write(ROOT.'/storage/template-auto.json',json_bytes($cache));
  if(hash_file('sha256',workbook_path(ROOT))!==$next['sha256'])throw new DomainException('Conflict: Excel changed while completing Resync. Draft restored; reload and try again.');
  unset($_SESSION['resyncConfirmation'],$_SESSION['formulaCells']);
  return ['hash'=>$next['sha256'],'message'=>'Resynced from the active workbook. Previous draft retained in backup.','draftBackup'=>basename($backup),'workbookBackup'=>basename($workbookBackup)];
 }catch(Throwable $e){
  if($written){if($snapshot['draftRaw']!==null)atomic_file_write($snapshot['path'],$snapshot['draftRaw']);else if(is_file($snapshot['path']))unlink($snapshot['path']);if($snapshot['cacheRaw']!==null)atomic_file_write(ROOT.'/storage/template-auto.json',$snapshot['cacheRaw']);else if(is_file(ROOT.'/storage/template-auto.json'))unlink(ROOT.'/storage/template-auto.json');}
  throw $e;
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}

