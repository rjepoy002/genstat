<?php
declare(strict_types=1);

function coop_audit(mysqli $db,string $operation,string $action,string $actor,string $period,array $details=[]): void {
 if($actor===COOP_LOCAL_DEV_ACTOR)$details['developmentMode']=true;
 coop_query_write($db,'INSERT INTO coop_audit(operation_id,action,actor,period,details) VALUES(?,?,?,?,?)',[$operation,$action,$actor,$period,json_encode($details,JSON_THROW_ON_ERROR)]);
}
function coop_query_write(mysqli $db,string $sql,array $params): void {
 $stmt=$db->prepare($sql);
 try{$stmt->execute($params);}finally{$stmt->close();}
}
function coop_effective(mysqli $db,string $period,bool $lock=false): array {
 return coop_query($db,'SELECT * FROM coop_consumption_accounts WHERE effective_from<=? AND (effective_to IS NULL OR effective_to>?) ORDER BY id LIMIT 501'.($lock?' FOR UPDATE':''),[$period,$period]);
}
function coop_validate_enrollment_input(string $action,array $body,array $targets): void {
 $period=$body['effective']??null;$area=$body['area']??null;
 if(!base_report_valid_month($period)||($action!=='deactivate'&&(!is_string($area)||!isset($targets[$area]))))
  throw new DomainException('Pick a valid area and effective month.');
}
function coop_require_enrollment_storage(mysqli $db): void {
 if(!coop_query($db,'SELECT id FROM coop_enrollment_state WHERE id=1'))
  throw new DomainException('COOP enrollment storage requires configuration.');
 coop_query($db,'SELECT account_no FROM coop_account_lock LIMIT 0');
 coop_query($db,'SELECT id FROM coop_consumption_accounts LIMIT 0');
 coop_query($db,'SELECT id FROM coop_audit LIMIT 0');
}
function coop_change_enrollment(mysqli $db,string $action,array $body,array $targets,string $actor,?string $resolved): void {
 coop_validate_enrollment_input($action,$body,$targets);
 $period=$body['effective'];$area=$body['area']??null;
 $record=null;
 if($action==='enroll'){
  $code=coop_identifier($body['acctCode']??null);$identity=$resolved;
 }else{
  $id=filter_var($body['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
  if(!$id||!($record=coop_query($db,'SELECT * FROM coop_consumption_accounts WHERE id=?',[$id])[0]??null))throw new DomainException('Enrollment was not found.');
  $identity=$record['canonical_acct_no'];$code=$record['acct_code'];
 }
 $db->begin_transaction();
 try{
  if(!coop_query($db,'SELECT id FROM coop_enrollment_state WHERE id=1 FOR UPDATE'))
   throw new DomainException('COOP enrollment storage requires configuration.');
  coop_query_write($db,'INSERT INTO coop_account_lock(account_no) VALUES(?) ON DUPLICATE KEY UPDATE account_no=account_no',[$identity]);
  if($record){
   $current=coop_query($db,'SELECT * FROM coop_consumption_accounts WHERE id=? FOR UPDATE',[$record['id']])[0];
   if($current!==$record||$period<=$record['effective_from']||($record['effective_to']!==null&&$period>=$record['effective_to']))
    throw new DomainException('The effective month is outside the editable enrollment interval.');
   coop_query_write($db,'UPDATE coop_consumption_accounts SET effective_to=?,status=?,updated_by=? WHERE id=?',[$period,$action==='reassign'?'reassigned':'deactivated',$actor,$record['id']]);
  }
  if($action!=='deactivate'){
   $end=$record['effective_to']??null;
   $overlap=coop_query($db,'SELECT id FROM coop_consumption_accounts WHERE canonical_acct_no=? AND (? IS NULL OR effective_from<?) AND (effective_to IS NULL OR effective_to>?) LIMIT 1 FOR UPDATE',[$identity,$end,$end,$period]);
   if($overlap)throw new DomainException('This canonical account already has overlapping enrollment.');
   coop_query_write($db,'INSERT INTO coop_consumption_accounts(area_key,acct_code,canonical_acct_no,effective_from,effective_to,status,created_by,updated_by) VALUES(?,?,?,?,?,?,?,?)',[$area,$code,$identity,$period,$end,'enrolled',$actor,$actor]);
  }
  coop_audit($db,bin2hex(random_bytes(16)),$action,$actor,$period,['enrollment'=>$record['id']??$db->insert_id,'before'=>$record['area_key']??null,'after'=>$action==='deactivate'?null:$area]);
  $db->commit();
 }catch(Throwable $e){$db->rollback();throw $e;}
}
