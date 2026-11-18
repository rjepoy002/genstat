<?php
declare(strict_types=1);
require_once __DIR__.'/base-report.php';
require_once __DIR__.'/base-report-view.php';
require_once __DIR__.'/report-areas.php';
final class QuerySettingsError extends RuntimeException {}
require_once __DIR__.'/report-expression.php';
function qs_config(): array {
 $path=__DIR__.'/../config/query-settings.local.php';if(!is_file($path))throw new QuerySettingsError('SQL settings are not configured. Run the documented local setup.');return require $path;
}
function qs_db(string $role): mysqli {
 $cfg=qs_config();$c=$cfg[$role];if(($c['user']??'')==='root')throw new QuerySettingsError('SQL settings cannot use a root database account.');
 mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);$db=mysqli_init();$db->options(MYSQLI_OPT_CONNECT_TIMEOUT,2);$db->real_connect($c['host'],$c['user'],$c['password'],$c['database'],$c['port']);$db->set_charset('utf8mb4');
 if($role==='reader'){
  foreach($db->query('SHOW GRANTS')->fetch_all(MYSQLI_NUM) as [$grant]){
   if(str_contains($grant,'GRANT OPTION')||!preg_match('/^GRANT (USAGE|SELECT) ON (.*?) TO /i',$grant,$m))throw new QuerySettingsError('Reporting account permissions are unsafe. SELECT-only table grants are required.');
   if(strtoupper($m[1])==='SELECT'){$parts=explode('.',str_replace('`','',$m[2]));if(count($parts)!==2||$parts[0]!==$c['database']||!in_array($parts[1],$cfg['allowedTables'],true))throw new QuerySettingsError('Reporting account grants exceed the approved table allowlist.');}
  }
  $db->query("SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_BACKSLASH_ESCAPES'");
  // MariaDB enforces this server-side even for expensive intermediate CTEs.
  if(!str_contains(strtolower($db->server_info),'mariadb'))throw new QuerySettingsError('This executor requires verified MariaDB statement-timeout support.');
  $db->query('SET SESSION max_statement_time='.(int)$cfg['timeoutSeconds']);
 }
 return $db;
}
function qs_month(string $worksheet): string {
 if(!preg_match('/^(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)(\d{2})$/D',$worksheet,$m))throw new QuerySettingsError('The selected worksheet has no valid reporting month.');
 $month=array_search($m[1],['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],true)+1;return '20'.$m[2].sprintf('%02d',$month);
}
function qs_rows(array $l): array {
 $s=$l['sheets'][0];$out=[];$headers=report_area_headers($l);$formulas=expanded_formulas($s);
 for($r=$l['report']['firstRow']+2;$r<=$l['report']['lastRow'];$r++){
  $label=$s['cells']['A'.$r]['value']??'';if(!trim($label)||base_report_row_hidden($label))continue;$cells=[];$protected=[];
  // Only actual area columns; monthly/cumulative/historical totals are never query targets.
  foreach($headers as $c=>$header){$ref=col_name($c).$r;$cell=$s['cells'][$ref]??worksheet_cell($s,$ref);$meta=$header+['classification'=>$cell['classification']];if($cell['editable']&&$cell['formula']===null&&!$cell['formulaProtected'])$cells[$ref]=$meta;else $protected[$ref]=$meta+['formula'=>$formulas[$ref]??null,'reason'=>($cell['formulaProtected']||$cell['formula']!==null)?'Excel formula is protected':((merged_owner($s['merges'],$ref)??$ref)!==$ref?'Covered merged-cell placeholder':'Non-writable source cell')];}
  if($cells){$key='genstat-base/'.$s['name'].'/A'.$r;$out[$key]=['key'=>$key,'label'=>$label,'row'=>$r,'sheet'=>$s['name'],'cells'=>$cells,'protectedCells'=>$protected];}
 }
 return $out;
}
function qs_parse(string $sql): array {
 $cfg=qs_config();$node=$cfg['node'];if(!is_file($node)||!is_file(__DIR__.'/../node_modules/node-sql-parser/package.json'))throw new QuerySettingsError('The SQL parser runtime is unavailable. Query execution is disabled.');
 $out=tmpfile();$err=tmpfile();$proc=proc_open([$node,'--max-old-space-size=128',__DIR__.'/../tools/validate-report-sql.cjs'],[0=>['pipe','r'],1=>$out,2=>$err],$pipes,dirname(__DIR__),null,['bypass_shell'=>true]);
 if(!is_resource($proc))throw new QuerySettingsError('The SQL parser could not start.');
 try{fwrite($pipes[0],json_encode(['sql'=>$sql,'allowedTables'=>$cfg['allowedTables'],'database'=>$cfg['reader']['database']],JSON_THROW_ON_ERROR));fclose($pipes[0]);$start=microtime(true);
  do{$state=proc_get_status($proc);if(!$state['running'])break;if(microtime(true)-$start>5){proc_terminate($proc);throw new QuerySettingsError('SQL validation timed out.');}usleep(10000);}while(true);
  rewind($out);$result=json_decode(stream_get_contents($out),true);if(!$result||empty($result['ok']))throw new QuerySettingsError($result['error']??'SQL grammar validation failed. No query was executed.');return $result;
 }finally{proc_close($proc);fclose($out);fclose($err);}
}
function qs_targets(array $l,array $config): array {
 $row=qs_rows($l)[$config['rowKey']]??null;if(!$row)throw new QuerySettingsError('Select an eligible SQL report row.');$targets=$row['cells'];
 if(sql_replacement_row_approved($l,$config['rowKey']))foreach($row['protectedCells'] as $ref=>$meta){
  if($meta['formula']!==null&&sql_replacement_cell_allowed($l['sheets'][0],$ref))$targets[$ref]=$meta+['formulaReplacement'=>true];
 }
 uksort($targets,fn($a,$b)=>cell_coordinates($a)[0]<=>cell_coordinates($b)[0]);return $targets;
}
function qs_execute(string $sql,string $month): array {
 $parsed=qs_parse($sql);$cfg=qs_config();$db=qs_db('reader');$start=microtime(true);$stmt=null;
 try{$db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
  $limit=(int)$cfg['resultLimit'];$stmt=$db->prepare('SELECT * FROM ('.$parsed['prepared'].') AS genstat_preview LIMIT '.($limit+1));
  $params=array_fill(0,count($parsed['params']),$month);$stmt->bind_param(str_repeat('s',count($params)),...$params);$stmt->execute();$metadata=$stmt->result_metadata();
  $fields=$metadata->fetch_fields();$columns=array_column($fields,'name');$numericColumns=[];foreach($fields as $field)if(in_array($field->type,[0,1,2,3,4,5,8,9,13,246],true))$numericColumns[]=$field->name;if(count($columns)>40||count($columns)!==count(array_unique($columns)))throw new QuerySettingsError('Return at most 40 uniquely named output columns.');
  // Fetch unbuffered, bounding bytes while reading rather than buffering a large
  // response before checking its size. Never truncate a value silently.
  $slots=array_fill(0,count($columns),null);$refs=[];foreach($slots as &$slot)$refs[]=&$slot;unset($slot);$stmt->bind_result(...$refs);$rows=[];$bytes=0;$truncated=false;
  while($stmt->fetch()){
   if(count($rows)===$limit){$truncated=true;break;}$row=[];
   foreach($columns as $i=>$column){$value=$slots[$i];$length=strlen((string)$value);$bytes+=$length;if($length>4096||$bytes>1000000)throw new QuerySettingsError('Result data exceeds the bounded preview size. Refine the informational columns.');$row[$column]=$value;}$rows[]=$row;
  }$stmt->close();$stmt=null;
  return ['columns'=>$columns,'numericColumns'=>$numericColumns,'rows'=>$rows,'truncated'=>$truncated,'durationMs'=>round((microtime(true)-$start)*1000,1),'month'=>$month];
 }finally{if($stmt instanceof mysqli_stmt)$stmt->close();$db->rollback();$db->close();}
}
function qs_load(mysqli $db): array {
 $rows=$db->query('SELECT m.*,q.query_key,q.sql_text,q.area_column,q.sacode_column,q.revision AS query_revision FROM genstat_row_query_mappings m JOIN genstat_query_definitions q ON q.id=m.query_definition_id')->fetch_all(MYSQLI_ASSOC);$out=[];
 foreach($rows as $row){$row['bindings']=json_decode($row['bindings_json'],true,512,JSON_THROW_ON_ERROR);$row['excluded']=json_decode($row['excluded_json'],true,512,JSON_THROW_ON_ERROR);unset($row['bindings_json'],$row['excluded_json']);$out[$row['template_row_key']]=$row;}return $out;
}
function qs_normalize(array $l,array $input): array {
 $rows=qs_rows($l);$key=$input['rowKey']??'';if(!isset($rows[$key]))throw new QuerySettingsError('Select an eligible, visible report row.');
 $row=$rows[$key];$out=['rowKey'=>$key,'queryKey'=>trim((string)($input['queryKey']??'')),'sql'=>(string)($input['sql']??''),'areaColumn'=>trim((string)($input['areaColumn']??'')),'valueColumn'=>trim((string)($input['valueColumn']??'')),'sacodeColumn'=>trim((string)($input['sacodeColumn']??'')),'bindings'=>[],'excluded'=>array_values(array_unique(array_map('strval',$input['excluded']??[]))),'enabled'=>!empty($input['enabled']),'allowFormulaReplacement'=>($input['allowFormulaReplacement']??false)===true];
 $out+=qs_calculation_settings($input);$out['canonicalRowId']=qs_canonical_row_id($l,$key);
 if($out['formulaEnabled']&&($row['row']!==18||$out['formulaTarget']!=='total_kwh_sold'))throw new QuerySettingsError('Report formulas currently support Total KWH Sold, row 18 only.');
 $out['allowFormulaReplacement']=$out['enabled']&&sql_replacement_row_approved($l,$key);
 if($out['allowFormulaReplacement']&&!$out['enabled'])throw new QuerySettingsError('Enable this SQL row mapping before opting into formula replacement.');$targets=qs_targets($l,$out);
 if(!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/D',$out['queryKey']))throw new QuerySettingsError('Use a query identifier starting with a letter (letters, digits, _ or -).');
 foreach(['areaColumn','valueColumn'] as $field)if(!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/D',$out[$field]))throw new QuerySettingsError('Enter valid area and result column names.');
 if($out['sacodeColumn']!==''&&!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/D',$out['sacodeColumn']))throw new QuerySettingsError('Invalid SACode column.');
 $used=[];foreach($targets as $ref=>$cell){$b=$input['bindings'][$ref]??null;if(!$b||!preg_match('/^[1-9][0-9]*$/D',(string)($b['areaId']??'')))throw new QuerySettingsError('Choose a verified AreaID for '.$ref.' ('.$cell['label'].').');$sa=trim((string)($b['sacode']??''));if($out['sacodeColumn']!==''&&!preg_match('/^\d{2}$/D',$sa))throw new QuerySettingsError('Enter a two-digit SACode for '.$ref);$identity=$out['sacodeColumn']!==''?$sa.'|'.$b['areaId']:(string)$b['areaId'];if(isset($used[$identity])||in_array($identity,$out['excluded'],true))throw new QuerySettingsError('Duplicate or excluded area binding: '.$identity);$used[$identity]=true;$out['bindings'][$ref]=['areaId'=>(string)$b['areaId'],'sacode'=>$out['sacodeColumn']!==''?$sa:''];}
 foreach(array_diff(array_keys($input['bindings']??[]),array_keys($targets)) as $ref){
  if(!isset($l['sheets'][0]['cells'][$ref]))throw new QuerySettingsError('Mapping contains an unknown workbook cell: '.$ref);
  if(cell_coordinates($ref)[1]!==$row['row'])throw new QuerySettingsError('Mapping cell is outside the selected authorized SQL input row: '.$ref);
  throw new QuerySettingsError('Mapping contains a protected or non-input cell outside approved SQL targets: '.$ref);
 }

 ksort($out['bindings']);sort($out['excluded']);return $out;
}
function qs_fingerprint(array $config,array $l): string{return hash('sha256',json_encode([$l['sha256'],qs_month($l['sheets'][0]['name']),$config],JSON_THROW_ON_ERROR));}
function qs_target_comparison(array $l,string $ref,string $proposed,$before=null): array {
 $cell=$l['sheets'][0]['cells'][$ref];if(func_num_args()<4)$before=$cell['value'];$current=$cell;$current['value']=(string)$before;$current['type']=is_numeric($before)?'n':'str';$after=$cell;$after['value']=$proposed;$after['type']='n';
 $difference=is_numeric($before)?(float)$proposed-(float)$before:null;
 return ['before'=>$before,'value'=>$proposed,'difference'=>$difference,'currentDisplay'=>$before===null?'Unavailable':display_value($current,$l),'proposedDisplay'=>display_value($after,$l),'differenceDisplay'=>$difference===null?'Unavailable':number_format($difference,2,'.',',')];
}
function qs_review_target(array $l,array $change,array $live,array $values,array $formulas,array $replacements): array {
 $ref=$change['ref'];$before=$live['cells'][$ref]['value'];
 $engineKey=$l['sheets'][0]['name'].'!'.$ref;
 $cacheFallback=false;foreach($live['warnings']??[] as $warning)if(($warning['cell']??'')===$engineKey)$cacheFallback=true;
 if(!empty($live['cells'][$ref]['error'])||($cacheFallback&&!is_numeric($l['sheets'][0]['cells'][$ref]['value']??null)))$before=null;$manual=array_key_exists($ref,$values)||array_key_exists($ref,$formulas);$sourceFormula=expanded_formulas($l['sheets'][0])[$ref]??null;
 $firstReplacement=!empty($change['formulaReplacement'])&&!isset($replacements[$ref]);$same=is_numeric($before)&&(float)$before===(float)$change['value'];
 $type=$firstReplacement?'Formula replacement':($same?'Unchanged':($manual?'Manual value conflict':'Normal SQL update'));
 return array_replace($change,qs_target_comparison($l,$ref,$change['value'],$before),['rowLabel'=>qs_rows($l)[$change['rowKey']]['label'],'manualConflict'=>$manual,'existingFormula'=>$sourceFormula!==null&&!isset($replacements[$ref])?($formulas[$ref]??$sourceFormula):null,'changeType'=>$type]);
}
function qs_match(array $l,array $config,array $result,array $areas,array $areaMappings): array {
 $errors=[];$notes=[];$changes=[];$blocked=[];$seen=[];$seenCombos=[];$expected=[];$known=array_column($areas,'AreaName','AreaID');
 $row=qs_rows($l)[$config['rowKey']];$targets=qs_targets($l,$config);$aliases=[];
 foreach(report_area_headers($l) as $column=>$header){$id=report_area_alias($header['label'],$areas,$areaMappings);if($id!==null)$aliases[$id]=['ref'=>col_name($column).$row['row'],'label'=>$header['label']];}
 foreach($config['bindings'] as $ref=>$b){if(!isset($known[$b['areaId']]))$errors[]='Unknown master AreaID '.$b['areaId'];$key=$config['sacodeColumn']!==''?$b['sacode'].'|'.$b['areaId']:$b['areaId'];$expected[$key]=$ref;
  if(isset($aliases[$b['areaId']])&&$aliases[$b['areaId']]['ref']!==$ref)$errors[]='Area '.$b['areaId'].' belongs to '.$aliases[$b['areaId']]['label'].' at '.$aliases[$b['areaId']]['ref'].', not '.$ref;
  foreach($aliases as $id=>$alias)if($alias['ref']===$ref&&(string)$id!==$b['areaId'])$errors[]=$alias['label'].' at '.$ref.' requires verified AreaID '.$id;
  if($config['sacodeColumn']!==''){$found=false;foreach($areaMappings as $m)if($m['SACode']===$b['sacode']&&in_array($b['areaId'],[(string)$m['AreaID'],(string)$m['GenstatAreaID'],(string)$m['kwhdiffAreaID']],true))$found=true;if(!$found)$errors[]='Unverified SACode/AreaID binding '.$key;}
 }
 $required=array_filter([$config['areaColumn'],$config['valueColumn'],$config['sacodeColumn']]);foreach($required as $column)if(!in_array($column,$result['columns'],true))$errors[]='Missing output column: '.$column;
 if($result['truncated'])$errors[]='Result exceeds the preview limit. Refine the query; incomplete results cannot be saved or applied.';
 if($errors)return ['errors'=>array_values(array_unique($errors)),'notes'=>[],'changes'=>[],'blockedTargets'=>[]];
 try{$computed=qs_formula_values($config,$result);}catch(QuerySettingsError $e){return ['errors'=>[$e->getMessage()],'notes'=>[],'changes'=>[],'blockedTargets'=>[]];}
 foreach($result['rows'] as $i=>$r){$id=(string)($r[$config['areaColumn']]??'');$sa=$config['sacodeColumn']!==''?(string)($r[$config['sacodeColumn']]??''):'';$key=$config['sacodeColumn']!==''?$sa.'|'.$id:$id;
  if($id===''||!isset($known[$id])){$errors[]='Unmapped master area at result row '.($i+1).': '.$id;continue;}
  if(isset($seen[$key])){$errors[]='Duplicate area identifier: '.$key;continue;}$seen[$key]=true;
  if(isset($r['SACode'])){$combo=(string)$r['SACode'].'|'.$id;if(isset($seenCombos[$combo]))$errors[]='Duplicate SACode/AreaID combination: '.$combo;$seenCombos[$combo]=true;}
  $value=!empty($config['formulaEnabled'])?$computed[$i]:$r[$config['valueColumn']];if($value===null||!is_numeric($value)||!is_finite((float)$value)){$errors[]='Null or invalid numeric result for area '.$key;continue;}
  if(in_array($key,$config['excluded'],true)){$notes[]='Explicitly excluded result area '.$key;continue;}
  if(!isset($expected[$key])){
   $alias=$aliases[$id]??null;$protected=$alias?($row['protectedCells'][$alias['ref']]??null):null;
   if($protected){$ref=$alias['ref'];$reason='Result area '.$key.' ('.$alias['label'].') resolves to '.$l['sheets'][0]['name'].'!'.$ref.': '.$protected['reason'].($protected['formula']?' '.$protected['formula']:'').'. SQL cannot overwrite this cell.';if(empty($config['formulaEnabled']))$errors[]=$reason;else $notes[]=$reason;$raw=is_float($value)?sprintf('%.17g',$value):(string)$value;$blocked[]=qs_target_comparison($l,$ref,$raw)+['ref'=>$ref,'areaId'=>$id,'areaName'=>$known[$id],'reason'=>$reason,'rowLabel'=>$row['label']];}
   else $errors[]='Result area '.$key.' has no Excel mapping. Map or explicitly exclude it.';continue;
  }
  $ref=$expected[$key];$target=$targets[$ref]??null;if(!$target){$errors[]='Unauthorized SQL target '.$ref;continue;}$changes[$ref]=['ref'=>$ref,'rowKey'=>$config['rowKey'],'areaId'=>$id,'areaName'=>$known[$id],'value'=>is_float($value)?sprintf('%.17g',$value):(string)$value,'formulaReplacement'=>!empty($target['formulaReplacement']),'originalFormula'=>$target['formula']??null,'excelArea'=>$target['label'],'mappingSource'=>$config['mappingSources'][$ref]??'saved','formulaUsed'=>!empty($config['formulaEnabled'])?$config['formulaExpression']:$config['valueColumn']];
 }
 foreach($expected as $key=>$ref)if(!isset($seen[$key]))$errors[]='Missing expected area '.$key.' for '.$ref;
 return ['errors'=>array_values(array_unique($errors)),'notes'=>$notes,'changes'=>$changes,'blockedTargets'=>$blocked];
}
function qs_area_metadata(): array {
 $db=qs_db('reader');try{return ['areas'=>$db->query('SELECT AreaID,AreaName FROM area_master ORDER BY AreaName')->fetch_all(MYSQLI_ASSOC),'areaMappings'=>$db->query('SELECT SACode,Area,AreaID,GenstatAreaID,kwhdiffAreaID FROM area_mapping')->fetch_all(MYSQLI_ASSOC)];}finally{$db->close();}
}
function qs_record_config(array $record): array {
 return ['rowKey'=>$record['template_row_key'],'queryKey'=>$record['query_key'],'sql'=>$record['sql_text'],'areaColumn'=>$record['area_column'],'sacodeColumn'=>$record['sacode_column'],'valueColumn'=>$record['value_column'],'bindings'=>$record['bindings'],'excluded'=>$record['excluded'],'enabled'=>(bool)$record['enabled'],'allowFormulaReplacement'=>(bool)($record['allow_formula_replacement']??false)]+qs_calculation_settings(json_decode($record['calculation_settings_json']??'{}',true,512,JSON_THROW_ON_ERROR)??[]);
}
function qs_save(array $l,array $config,int $expectedRevision,?mysqli $connection=null): array {
 $owned=$connection===null;$db=$connection??qs_db('writer');$now=gmdate('Y-m-d H:i:s');$key=$config['rowKey'];
 try{if($owned)$db->begin_transaction();$db->query('SELECT id FROM genstat_query_definitions FOR UPDATE');$db->query('SELECT id FROM genstat_row_query_mappings FOR UPDATE');$records=qs_load($db);$old=$records[$key]??null;if((int)($old['revision']??0)!==$expectedRevision)throw new QuerySettingsError('Configuration changed in another window. Reload settings.');
  if($old&&$old['template_hash']===$l['sha256']&&qs_config_hash(qs_record_config($old))===qs_config_hash($config)){if($owned)$db->commit();return $old;}
  // A legacy definition can be shared by multiple rows. Fork only when editing
  // its SQL would otherwise change another row; retain its original identifier.
  if($old){foreach($records as $otherKey=>$other)if($otherKey!==$key&&(int)$other['query_definition_id']===(int)$old['query_definition_id']&&($old['sql_text']!==$config['sql']||$old['area_column']!==$config['areaColumn']||$old['sacode_column']!==$config['sacodeColumn'])){$config['queryKey']=substr(qs_canonical_row_id($l,$key),0,55).'_'.substr(hash('sha256',$config['sql'].'|'.$config['areaColumn'].'|'.$config['sacodeColumn']),0,12);break;}}
  $stmt=$db->prepare('SELECT * FROM genstat_query_definitions WHERE query_key=?');$stmt->bind_param('s',$config['queryKey']);$stmt->execute();$definition=$stmt->get_result()->fetch_assoc();
  if($definition){$id=(int)$definition['id'];$changed=$definition['sql_text']!==$config['sql']||$definition['area_column']!==$config['areaColumn']||$definition['sacode_column']!==$config['sacodeColumn'];
   if($changed){foreach($records as $otherKey=>$other)if((int)$other['query_definition_id']===$id&&$otherKey!==$key)throw new QuerySettingsError('This query is reused by another row. Use a new query identifier to change its SQL or matching columns.');
    $stmt=$db->prepare('UPDATE genstat_query_definitions SET sql_text=?,area_column=?,sacode_column=?,revision=revision+1,updated_at=? WHERE id=?');$stmt->bind_param('ssssi',$config['sql'],$config['areaColumn'],$config['sacodeColumn'],$now,$id);$stmt->execute();}
  }else{$stmt=$db->prepare('INSERT INTO genstat_query_definitions(query_key,sql_text,area_column,sacode_column,created_at,updated_at) VALUES(?,?,?,?,?,?)');$stmt->bind_param('ssssss',$config['queryKey'],$config['sql'],$config['areaColumn'],$config['sacodeColumn'],$now,$now);$stmt->execute();$id=$db->insert_id;}
  if($old)qs_archive_version($db,$old,$now);
  $label=qs_rows($l)[$key]['label'];$bindings=json_encode($config['bindings'],JSON_THROW_ON_ERROR);$excluded=json_encode($config['excluded'],JSON_THROW_ON_ERROR);$enabled=(int)$config['enabled'];$hash=$l['sha256'];$revision=(int)($old['revision']??0)+1;
  $allow=(int)($config['allowFormulaReplacement']??false);$stmt=$db->prepare('INSERT INTO genstat_row_query_mappings(template_row_key,template_hash,row_label,query_definition_id,value_column,bindings_json,excluded_json,enabled,revision,created_at,updated_at,allow_formula_replacement) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE template_hash=VALUES(template_hash),row_label=VALUES(row_label),query_definition_id=VALUES(query_definition_id),value_column=VALUES(value_column),bindings_json=VALUES(bindings_json),excluded_json=VALUES(excluded_json),enabled=VALUES(enabled),revision=VALUES(revision),updated_at=VALUES(updated_at),allow_formula_replacement=VALUES(allow_formula_replacement)');
  $stmt->bind_param('sssisssiissi',$key,$hash,$label,$id,$config['valueColumn'],$bindings,$excluded,$enabled,$revision,$now,$now,$allow);$stmt->execute();$settings=json_encode(qs_calculation_settings($config),JSON_THROW_ON_ERROR);$stmt=$db->prepare('UPDATE genstat_row_query_mappings SET calculation_settings_json=? WHERE template_row_key=?');$stmt->bind_param('ss',$settings,$key);$stmt->execute();$saved=qs_load($db)[$key];qs_archive_version($db,$saved,$now);if($owned)$db->commit();return $saved;
 }catch(Throwable $e){if($owned)$db->rollback();throw $e;}finally{if($owned)$db->close();}
}

function qs_calculation_settings(array $input): array {
 $method=(string)($input['matchingMethod']??'normalized');
 if(!in_array($method,['normalized','exact','manual'],true))throw new QuerySettingsError('Invalid area matching method.');
 $sources=$input['mappingSources']??[];if(!is_array($sources))throw new QuerySettingsError('Invalid mapping sources.');
 foreach($sources as $source)if(!in_array($source,['auto','manual','saved'],true))throw new QuerySettingsError('Invalid mapping source.');
 $column=(string)($input['autoMatchColumn']??'');if(strlen($column)>64)throw new QuerySettingsError('Invalid automatic matching column.');
 $expression=trim((string)($input['formulaExpression']??''));if(strlen($expression)>2048)throw new QuerySettingsError('Formula exceeds 2048 characters.');
 return ['canonicalRowId'=>(string)($input['canonicalRowId']??''),'autoMatchColumn'=>$column,'matchingMethod'=>$method,'mappingSources'=>$sources,'formulaTarget'=>(string)($input['formulaTarget']??'total_kwh_sold'),'formulaExpression'=>$expression,'formulaEnabled'=>!empty($input['formulaEnabled'])];
}

require_once __DIR__.'/query-apply-save.php';
