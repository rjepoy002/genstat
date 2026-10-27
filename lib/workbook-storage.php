<?php
declare(strict_types=1);
require_once __DIR__.'/files.php';
require_once __DIR__.'/xlsx.php';
function workbook_path(string $root): string {
 $active=$root.'/excel-files/active/genstat_2026.xlsx';
 return is_file($active)?$active:$root.'/genstat.xlsx';
}
function workbook_validate(string $file): array {
 if(strtolower(pathinfo($file,PATHINFO_EXTENSION))!=='xlsx'||is_link($file))throw new RuntimeException('Only regular .xlsx workbooks are accepted.');
 if(filesize($file)>50000000)throw new RuntimeException('Workbook exceeds 50 MB.');
 $zip=zip_read($file);$total=0;
 foreach($zip as $name=>$entry){
  if(str_contains($name,'..')||str_starts_with($name,'/')||str_contains($name,'\\'))throw new RuntimeException('Unsafe workbook part path.');
  $total+=$entry['header']['size'];if($total>200000000||$entry['header']['size']>50000000)throw new RuntimeException('Workbook expansion exceeds limits.');
  if(preg_match('~vbaProject|macrosheets/|embeddings/|activeX/~i',$name))throw new RuntimeException('Executable workbook content is not accepted.');
  $raw=zip_text($zip,$name);
  if(strlen($raw)!==$entry['header']['size']||sprintf('%u',crc32($raw))!==sprintf('%u',$entry['header']['crc']))throw new RuntimeException('Workbook CRC verification failed.');
  if(str_ends_with($name,'.xml')||str_ends_with($name,'.rels')){if(stripos($raw,'<!DOCTYPE')!==false||stripos($raw,'<!ENTITY')!==false)throw new RuntimeException('XML entities are prohibited.');xml_doc($raw);}
 }
 foreach(['[Content_Types].xml','xl/workbook.xml','xl/_rels/workbook.xml.rels'] as $required)if(!isset($zip[$required]))throw new RuntimeException('Not an Excel workbook.');
 if(stripos(zip_text($zip,'[Content_Types].xml'),'macroEnabled')!==false)throw new RuntimeException('Macro-enabled content is prohibited.');
 return $zip;
}
function workbook_backup(string $root,string $file,string $label): string {
 $raw=file_get_contents($file);$dest=$root.'/excel-files/backups/'.$label.'-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6)).'.xlsx';
 atomic_file_write($dest,$raw);if(!hash_equals(hash('sha256',$raw),hash_file('sha256',$dest)))throw new RuntimeException('Workbook backup verification failed.');return $dest;
}
// Called while the existing request-wide exclusive template lock is held.
function storage_refresh(string $root,array $baseline): void {
 foreach(['active','incoming','backups','archive'] as $dir)if(!is_dir($root.'/excel-files/'.$dir)&&!mkdir($root.'/excel-files/'.$dir,0777,true))throw new RuntimeException('Cannot create workbook storage.');
 $active=$root.'/excel-files/active/genstat_2026.xlsx';
 if(!is_file($active)){workbook_validate($root.'/genstat.xlsx');workbook_backup($root,$root.'/genstat.xlsx','legacy');atomic_file_write($active,file_get_contents($root.'/genstat.xlsx'));}
 $files=glob($root.'/excel-files/incoming/*.xlsx')?:[];sort($files);
 if(!$files)return;
 // Require an unambiguous candidate; never guess which of several uploads wins.
 if(count($files)!==1){$GLOBALS['incomingWarning']='Multiple incoming workbooks await validation. Keep only the intended candidate in incoming; the active workbook is unchanged.';return;}
 $incoming=$files[0];$cache=$root.'/storage/template-auto.json';$prior=is_file($cache)?file_get_contents($cache):null;
 try {
  if(!preg_match('/^GENSTAT[_ ]2026(?:\(\d+\))?\.xlsx$/i',basename($incoming)))throw new RuntimeException('Incoming filename must identify GENSTAT year 2026.');
  $oldHash=hash_file('sha256',$active);$backup=workbook_backup($root,$active,'before-refresh');
  workbook_validate($incoming);$hash=hash_file('sha256',$incoming);
  $next=auto_template_layout($root,$baseline,true,$incoming);
  if(($GLOBALS['templateRefresh']['state']??'')!=='ready'||$next['sha256']!==$hash)throw new RuntimeException($GLOBALS['templateRefresh']['message']??'Unsafe structural migration.');
  if(hash_file('sha256',$active)!==$oldHash||hash_file('sha256',$incoming)!==$hash)throw new RuntimeException('Workbook changed during validation. Retry after Excel finishes saving.');
  $raw=file_get_contents($incoming);if(hash('sha256',$raw)!==$hash)throw new RuntimeException('Incoming workbook changed.');
  // Keep the upload as historical evidence outside the watched incoming folder.
  $dest=$root.'/excel-files/archive/source-2026-'.substr($hash,0,16).'.xlsx';atomic_file_write($dest,$raw);
  if(hash_file('sha256',$active)!==$oldHash)throw new RuntimeException('Active workbook changed before replacement.');
  atomic_file_write($active,$raw);
  if(hash_file('sha256',$incoming)===$hash)@unlink($incoming);
 } catch(Throwable $e){
  if($prior!==null)atomic_file_write($cache,$prior);elseif(is_file($cache))unlink($cache);
  $GLOBALS['incomingWarning']='Workbook update deferred: '.$e->getMessage().' Active workbook and drafts are preserved.';
 }
}


