<?php
declare(strict_types=1);

function qs_stable_hash($value): string {
 $canonical=function($v)use(&$canonical){if(is_object($v))$v=(array)$v;if(!is_array($v))return $v;if(!array_is_list($v))ksort($v);foreach($v as &$entry)$entry=$canonical($entry);unset($entry);return $v;};
 return hash('sha256',json_encode($canonical($value),JSON_THROW_ON_ERROR));
}
function qs_config_hash(array $config): string {
 // Internal definition identifiers may be forked to preserve a legacy shared
 // query; the user-visible configuration and canonical report row stay stable.
 unset($config['queryKey']);return qs_stable_hash($config);
}
function qs_overlay_hash(array $values,array $formulas,array $replacements): string {return qs_stable_hash([$values,$formulas,$replacements]);}
function qs_canonical_row_id(array $l,string $key): string {
 $row=qs_rows($l)[$key]??null;if(!$row)throw new QuerySettingsError('Select an eligible report row.');
 $label=report_area_name($row['label']);$slug=trim(preg_replace('/[^a-z0-9]+/','_', $label),'_');
 $same=array_filter(qs_rows($l),fn($r)=>report_area_name($r['label'])===$label);
 if(count($same)>1){$context='';for($r=$row['row']-1;$r>=$l['report']['firstRow'];$r--){$candidate=trim((string)($l['sheets'][0]['cells']['A'.$r]['value']??''));if($candidate!==''&&report_area_name($candidate)!==$label){$context=report_area_name($candidate);break;}}$slug.='_'.substr(hash('sha256',$context),0,8);}
 return 'genstat_'.(strlen($slug)>62?substr($slug,0,48).'_'.substr(hash('sha256',$label),0,12):($slug!==''?$slug:substr(hash('sha256',$label),0,16)));
}
function qs_internal_query_key(array $l,string $key,array $records): string {
 if(isset($records[$key]))return $records[$key]['query_key'];
 $label=qs_rows($l)[$key]['label']??null;if($label===null)throw new QuerySettingsError('Select an eligible report row.');
 // Existing cross-month identifiers are retained; no rows are merged/deleted.
 $previous=array_filter($records,fn($r)=>report_area_name($r['row_label'])===report_area_name($label));
 if($previous){uasort($previous,fn($a,$b)=>strcmp($a['created_at'],$b['created_at']));return reset($previous)['query_key'];}
 $generated=qs_canonical_row_id($l,$key);
 foreach($records as $record)if($record['query_key']===$generated&&report_area_name($record['row_label'])!==report_area_name($label))return substr($generated,0,60).'_'.substr(hash('sha256',$label),0,12);
 return $generated;
}
function qs_editor_input(array $l,array $input,array $records): array {
 $key=(string)($input['rowKey']??'');if(!isset(qs_rows($l)[$key]))throw new QuerySettingsError('Select an eligible report row.');
 $input['queryKey']=qs_internal_query_key($l,$key,$records);
 foreach($records as $otherKey=>$other)if($otherKey!==$key&&$other['query_key']===$input['queryKey']&&($other['sql_text']!==($input['sql']??'')||$other['area_column']!==trim((string)($input['areaColumn']??''))||$other['sacode_column']!==trim((string)($input['sacodeColumn']??'')))){$input['queryKey']=substr(qs_canonical_row_id($l,$key),0,55).'_'.substr(hash('sha256',($input['sql']??'').'|'.trim((string)($input['areaColumn']??'')).'|'.trim((string)($input['sacodeColumn']??''))),0,12);break;}
 $input['enabled']=true;$input['allowFormulaReplacement']=sql_replacement_row_approved($l,$key);return $input;
}
function qs_result_key(array $l,string $sql): string {return hash('sha256',json_encode([$l['sha256'],qs_month($l['sheets'][0]['name']),$sql],JSON_THROW_ON_ERROR));}
function qs_tested_result(array $l,array $config): array {
 $tested=$_SESSION['queryResult']??null;
 if(!$tested||$tested['key']!==qs_result_key($l,$config['sql'])||($tested['rowKey']??'')!==$config['rowKey']||$tested['expires']<time())throw new QuerySettingsError('Test the SQL query first. The tested query must match the selected reporting month.');
 if($tested['result']['truncated'])throw new QuerySettingsError('Incomplete query results cannot be reviewed.');return $tested['result'];
}
function qs_owner_hash(): string {return hash_hmac('sha256',session_id(),sql_replacement_secret());}
function qs_receipt(mysqli $db,string $operationId): ?array {
 if(!preg_match('/^[a-f0-9]{48}$/D',$operationId))throw new QuerySettingsError('Invalid Apply & Save operation identifier.');
 $stmt=$db->prepare('SELECT * FROM genstat_query_apply_operations WHERE operation_id=?');$stmt->bind_param('s',$operationId);$stmt->execute();return $stmt->get_result()->fetch_assoc();
}
function qs_restore_receipt(array $l,array $receipt,array $records,array $body,array $draft,bool $recovery=false): array {
 $owner=qs_owner_hash();if(!hash_equals($receipt['owner_hash'],$owner)||$receipt['source_hash']!==$l['sha256']||(int)$receipt['draft_revision']!==(int)$draft['revision'])throw new QuerySettingsError('This operation cannot be recovered for the current session, template or draft version.');
 $record=$records[$receipt['template_row_key']]??null;
 if(!$record||qs_stable_hash($record)!==$receipt['record_hash'])throw new QuerySettingsError('The saved configuration changed after this operation. Review again.');
 if(qs_stable_hash(qs_area_metadata())!==$receipt['area_hash'])throw new QuerySettingsError('Area mappings changed after this operation. Review again.');
 $hash=qs_overlay_hash($body['values']??[],$body['formulas']??[],$body['sqlReplacements']??[]);
 $accepted=[$receipt['input_hash'],$receipt['output_hash']];if($recovery)$accepted[]=$receipt['baseline_hash'];
 if(!in_array($hash,$accepted,true))throw new QuerySettingsError('The report has other edits. Recover into an unchanged draft or review again; current edits were not replaced.');
 if(!$recovery){$config=qs_normalize($l,qs_editor_input($l,$body['config']??[],$records));if(!hash_equals($receipt['config_hash'],qs_config_hash($config)))throw new QuerySettingsError('The submitted configuration differs from the committed operation.');}
 $out=json_decode($receipt['response_json'],true,512,JSON_THROW_ON_ERROR);base_report_validate($l,$out['values'],$out['formulas']);qs_normalize($l,qs_record_config($record));sql_replacement_validate($l,$out['sqlReplacements'],$out['formulas']);
 foreach($out['sqlReplacements'] as $ref=>$replacement){$_SESSION['sqlReplacementGrants'][$replacement['signature']]=true;unset($_SESSION['baseUnlocked'][$l['sha256']][$ref]);}
 $out['recovered']=true;return $out;
}
function qs_archive_version(mysqli $db,array $record,string $now): void {
 $key=$record['template_row_key'];$revision=(int)$record['revision'];
 $stmt=$db->prepare("SELECT id FROM genstat_query_history WHERE template_row_key=? AND CAST(JSON_UNQUOTE(JSON_EXTRACT(snapshot_json,'$.revision')) AS UNSIGNED)=? LIMIT 1");$stmt->bind_param('si',$key,$revision);$stmt->execute();if($stmt->get_result()->fetch_assoc())return;
 $snapshot=json_encode($record,JSON_THROW_ON_ERROR);$stmt=$db->prepare('INSERT INTO genstat_query_history(template_row_key,snapshot_json,created_at) VALUES(?,?,?)');$stmt->bind_param('sss',$key,$snapshot,$now);$stmt->execute();
}
function qs_disable_configuration(array $l,string $key,int $expectedRevision): array {
 if(!isset(qs_rows($l)[$key]))throw new QuerySettingsError('Unknown report row.');$db=qs_db('writer');
 try{$db->begin_transaction();$db->query('SELECT id FROM genstat_query_definitions FOR UPDATE');$db->query('SELECT id FROM genstat_row_query_mappings FOR UPDATE');$old=qs_load($db)[$key]??null;
  if(!$old||(int)$old['revision']!==$expectedRevision)throw new QuerySettingsError('Configuration changed. Reload settings.');
  if($old['enabled']){$now=gmdate('Y-m-d H:i:s');qs_archive_version($db,$old,$now);$stmt=$db->prepare('UPDATE genstat_row_query_mappings SET enabled=0,revision=revision+1,updated_at=? WHERE template_row_key=?');$stmt->bind_param('ss',$now,$key);$stmt->execute();}
  $record=qs_load($db)[$key];qs_archive_version($db,$record,gmdate('Y-m-d H:i:s'));$db->commit();return $record;
 }catch(Throwable $e){$db->rollback();throw $e;}finally{$db->close();}
}
