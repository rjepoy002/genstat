<?php
declare(strict_types=1);
require_once __DIR__.'/../tools/build-layout.php';

function monthly_sheet_period(string $name,int $year=2026): ?array {
 $months=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
 if(!preg_match('/^([A-Z][a-z]{2})(\d{2})$/D',$name,$m)||$m[2]!==substr((string)$year,-2))return null;
 $month=array_search($m[1],$months,true);if($month===false)return null;
 return ['year'=>$year,'month'=>$month+1,'period'=>sprintf('%04d%02d',$year,$month+1),
  'label'=>DateTimeImmutable::createFromFormat('!Y-n-j',$year.'-'.($month+1).'-1')->format('F Y')];
}
function missing_monthly_sheets(array $active,array $incoming): array {
 $names=array_column($incoming['sheets'],'name');$missing=[];
 foreach($active['sheets'] as $sheet)if(!in_array($sheet['name'],$names,true)&&($period=monthly_sheet_period($sheet['name'])))$missing[$sheet['name']]=$period;
 return $missing;
}
function monthly_sql_identifier(string $name): string {
 if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D',$name))throw new RuntimeException('Invalid source identifier.');return '`'.$name.'`';
}
function monthly_source_availability(string $root,int $year,int $month): array {
 $result=['state'=>'unconfigured','period'=>sprintf('%04d%02d',$year,$month),'tables'=>[],'complete'=>false,
  'reason'=>'No complete reviewed MySQL-to-worksheet mappings are configured.'];
 $file=$root.'/config/monthly-source.php';if(!is_file($file))return $result;
 $cfg=require $file;$result['mappingCount']=count($cfg['worksheetMappings']??[]);
 if(!extension_loaded('mysqli')){$result['state']='unavailable';$result['reason']='The MySQL driver is unavailable; completeness cannot be verified.';return $result;}
 $db=null;
 try{
  if($month<1||$month>12||$year!==2026)throw new RuntimeException('Unsupported source period.');
  mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$db=mysqli_init();$db->options(MYSQLI_OPT_CONNECT_TIMEOUT,2);$c=$cfg['connection'];
  $db->real_connect($c['host'],$c['user'],$c['password'],$c['database'],(int)$c['port']);$db->set_charset('utf8mb4');
  $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);$records=0;
  foreach($cfg['sources'] as $table=>$definition){
   $id=monthly_sql_identifier($table);$periodColumn=monthly_sql_identifier($definition['period']);
   $statement=$db->prepare('SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$statement->bind_param('s',$table);$statement->execute();$columns=array_column($statement->get_result()->fetch_all(MYSQLI_ASSOC),'COLUMN_NAME');$statement->close();
   $required=array_merge([$definition['period']],$definition['columns'],isset($definition['referencePeriod'])?[$definition['referencePeriod']]:[]);
   $missing=array_values(array_diff($required,$columns));if($missing){$result['tables'][$table]=['state'=>'schema-mismatch','missingColumns'=>$missing];continue;}
   $period=$result['period'];$statement=$db->prepare("SELECT COUNT(*) AS records FROM $id WHERE $periodColumn=?");$statement->bind_param('s',$period);$statement->execute();$count=(int)$statement->get_result()->fetch_assoc()['records'];$statement->close();
   $entry=['state'=>'inspected','records'=>$count,'periodColumn'=>$definition['period']];$records+=$count;
   if(isset($definition['referencePeriod'])){$reference=monthly_sql_identifier($definition['referencePeriod']);$statement=$db->prepare("SELECT $reference AS referencePeriod,COUNT(*) AS records FROM $id WHERE $periodColumn=? GROUP BY $reference ORDER BY $reference");$statement->bind_param('s',$period);$statement->execute();$entry['referencePeriods']=$statement->get_result()->fetch_all(MYSQLI_ASSOC);$statement->close();}
   $result['tables'][$table]=$entry;
  }
  $db->rollback();$result['state']=$records>0?'partial-records':'no-records';
  foreach($result['tables'] as $table)if($table['state']==='schema-mismatch')$result['state']='schema-mismatch';
  // Generation remains unsupported even with rows or a newly edited config:
  // this checkout has no reviewed multi-source calculation/OOXML generator.
  $result['reason']=$records>0?'Partial MySQL records exist, but complete source coverage, adjustment rules and cell mappings are not implemented.':'No source rows were found for the requested BillMonth; complete mappings are also unavailable.';
 }catch(Throwable $e){$result['state']='unavailable';$result['reason']='MySQL availability could not be verified. No worksheet will be generated.';$result['errorCode']=$e->getCode();}
 finally{if($db){try{$db->close();}catch(Throwable $ignored){}}}
 return $result;
}
function missing_monthly_review(string $root,array $active,array $incoming,?callable $probe=null): array {
 $missing=missing_monthly_sheets($active,$incoming);$review=['state'=>'pending','canApply'=>false,'canRegenerate'=>false,'requiresConfirmation'=>false,'months'=>[],'unrelatedChanges'=>[],'conflicts'=>[]];
 if(!$missing)return $review;
 $baseline=is_file($root.'/config/layout.json')?json_decode(file_get_contents($root.'/config/layout.json'),true):null;
 foreach($missing as $name=>$period){
  $sheet=array_values(array_filter($active['sheets'],fn($s)=>$s['name']===$name))[0];
  $entry=['sheet'=>$name]+$period+['message'=>'No data available for '.$period['label'].'.','source'=>$probe?$probe($period['year'],$period['month']):monthly_source_availability($root,$period['year'],$period['month']),
   'template'=>['range'=>$sheet['range'],'formulaNodes'=>count(array_filter($sheet['cells'],fn($c)=>$c['formula']!==null)),'mergedRanges'=>count($sheet['merges'])],
   'drafts'=>[],'dependentCells'=>[],'definedNames'=>[],'preserveExistingSheet'=>true];
  foreach(glob($root.'/storage/draft-*.json')?:[] as $file){
   try{$draft=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);$owner=$draft['sheet']??$baseline['sheets'][0]['name']??'Jun26';if($owner!==$name)continue;
    $values=(array)($draft['values']??[]);$formulas=(array)($draft['formulas']??[]);$entry['drafts'][]=['file'=>basename($file),'valueCells'=>array_keys($values),'formulaCells'=>array_keys($formulas),'revision'=>$draft['revision']??null];
   }catch(Throwable $e){$entry['drafts'][]=['file'=>basename($file),'state'=>'unreadable-preserved'];}
  }
  foreach($active['sheets'] as $other)if($other['name']!==$name)foreach($other['cells'] as $ref=>$cell){$f=$cell['formula']??'';if(stripos($f,$name.'!')!==false||stripos($f,"'$name'!")!==false)$entry['dependentCells'][]=$other['name'].'!'.$ref;}
  foreach($active['definedNames'] as $defined)if(str_contains($defined['value'],$name.'!')||str_contains($defined['value'],"'$name'!"))$entry['definedNames'][]=$defined;
  foreach($entry['drafts'] as $draft)if(!empty($draft['valueCells'])||!empty($draft['formulaCells'])||isset($draft['state']))$review['conflicts'][]=['sheet'=>$name,'reason'=>'Saved edits must be preserved and explicitly reconciled before regeneration.','draft'=>$draft['file']];
  if($entry['dependentCells'])$review['conflicts'][]=['sheet'=>$name,'reason'=>'Other worksheets depend on this month; dependency preservation requires review.','cells'=>$entry['dependentCells']];
  $review['months'][]=$entry;
 }
 foreach($active['sheets'] as $sheet){$after=array_values(array_filter($incoming['sheets'],fn($s)=>$s['name']===$sheet['name']))[0]??null;if(!$after)continue;
  $properties=[];foreach(['cells','rows','columns','merges','settings'] as $key)if($sheet[$key]!==$after[$key])$properties[]=$key;
  if($properties)$review['unrelatedChanges'][$sheet['name']]=$properties;
 }
 $review['requiresConfirmation']=true;
 $review['reason']='Regeneration is unsupported until complete multi-source mappings and an edit/dependency preservation plan are reviewed. Confirmation alone cannot authorize incomplete generation.';
 return $review;
}
