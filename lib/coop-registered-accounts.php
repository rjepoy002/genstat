<?php
declare(strict_types=1);

function coop_registered_areas(): array {
 $db=qs_db('reader');
 try{
  $areas=[];
  foreach($db->query('SELECT AreaID,AreaName FROM area_master ORDER BY AreaName, AreaID') as $area)
   $areas[(int)$area['AreaID']]=['id'=>(int)$area['AreaID'],'name'=>$area['AreaName'],'count'=>0,'total'=>'0.00','incomplete'=>0];
  return $areas;
 }finally{$db->close();}
}

function coop_lookup_accounts(?array $connection,array $codes): array {
 if(!$codes)return [];
 $matches=[];
 try{
  if(!$connection)throw new DomainException('COOP connection is unavailable.');
  $pdo=coop_pdo($connection);
  $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
  $pdo->exec('START TRANSACTION READ ONLY');
  try{
   foreach(array_chunk($codes,100) as $chunk){
    $parameters=implode(',',array_fill(0,count($chunk),'?'));
    $exact="AcctCode IN ($parameters) AND BINARY AcctCode IN ($parameters)";
    $statement=$pdo->prepare("SELECT AcctCode, AcctNo, Name FROM master WHERE $exact AND Status = 'A'");
    $statement->execute([...$chunk,...$chunk]);
    while($row=$statement->fetch(PDO::FETCH_ASSOC)){
     $code=$row['AcctCode'];
     $number=$row['AcctNo'];
     $matches[$code]=isset($matches[$code])?['status'=>'Multiple active account matches']:
      ($number===null||trim($number)===''||strlen($number)>128
       ?['status'=>'No active account']:['AcctNo'=>$number,'Name'=>$row['Name']]);
    }
    $statement->closeCursor();
    $unmatched=array_values(array_filter($chunk,fn($code)=>!isset($matches[$code])));
    if($unmatched){
     $parameters=implode(',',array_fill(0,count($unmatched),'?'));
     // Existence check only. Canonical details come exclusively from active rows.
     $statement=$pdo->prepare("SELECT DISTINCT BINARY AcctCode FROM master WHERE AcctCode IN ($parameters) AND BINARY AcctCode IN ($parameters)");
     $statement->execute([...$unmatched,...$unmatched]);
     foreach($statement->fetchAll(PDO::FETCH_COLUMN) as $code)$matches[$code]=['status'=>'No active account'];
     $statement->closeCursor();
    }
   }
  }finally{$pdo->rollBack();}
 }catch(PDOException|DomainException){
  return array_fill_keys($codes,['status'=>'Unable to retrieve data']);
 }
 return $matches+array_fill_keys($codes,['status'=>'Account not found']);
}

function coop_resolve_registered_accounts(mysqli $db,array $matches): array {
 $changed=false;
 $db->begin_transaction();
 try{
  $accounts=$db->query('SELECT ID,AcctCode,AcctNo,AccountName,GenstatAreaID FROM coop_registered_accounts ORDER BY ID FOR UPDATE')->fetch_all(MYSQLI_ASSOC);
  foreach($accounts as &$row){
   $match=$matches[$row['AcctCode']]??['status'=>'Unable to retrieve data'];
   if($row['AcctNo']===null||$row['AcctNo']===($match['AcctNo']??null)||($match['status']??null)==='Unable to retrieve data')continue;
   $db->execute_query('UPDATE coop_registered_accounts SET AcctNo=NULL,AccountName=NULL WHERE ID=?',[$row['ID']]);
   $row['AcctNo']=$row['AccountName']=null;
   $changed=true;
  }
  unset($row);
  foreach($accounts as &$row){
   $match=$matches[$row['AcctCode']]??['status'=>'Unable to retrieve data'];
   $row['status']=$match['status']??'';
   if($row['status']!==''||($row['AcctNo']===$match['AcctNo']&&$row['AccountName']===$match['Name']))continue;
   try{
    $db->execute_query('UPDATE coop_registered_accounts SET AcctNo=?,AccountName=? WHERE ID=?',
     [$match['AcctNo'],$match['Name'],$row['ID']]);
    $row['AcctNo']=$match['AcctNo'];$row['AccountName']=$match['Name'];
    $changed=true;
   }catch(mysqli_sql_exception $error){
    if($error->getCode()!==1062)throw $error;
    $row['status']='Canonical account already registered';
   }
  }
  unset($row);
  $db->commit();
 }catch(Throwable $error){$db->rollback();throw $error;}
 return [$accounts,$changed];
}

function coop_registered_active_numbers(array $accounts): array {
 return array_column(array_filter($accounts,fn($account)=>$account['status']===''),'AcctNo');
}

function coop_registered_check_duplicate(mysqli $db,array $areas,string $code,?string $number=null): void {
 $registered=$db->execute_query('SELECT GenstatAreaID FROM coop_registered_accounts WHERE AcctCode=? OR AcctNo=? ORDER BY ID',
  [$code,$number])->fetch_all(MYSQLI_ASSOC);
 $assigned=array_unique(array_map(fn($row)=>$areas[$row['GenstatAreaID']]['name']??'Unknown area',$registered));
 if($assigned)throw new DomainException('Account already registered in '.implode(', ',$assigned).'. Use Edit Area to transfer it.',409);
}

function coop_registered_mutate(mysqli $db,array $areas,?array $connection,string $month,string $action,array $input): array {
 $area=$input['area']??null;
 if($action!=='accounts-remove'&&(!is_int($area)||!isset($areas[$area])))
  throw new DomainException('Select an available GENSTAT area.');
 if($action==='accounts-add'){
  $code=$input['code']??null;
  if(!is_string($code)||trim($code)===''||strlen($code)>128||preg_match('/[\x00-\x1f\x7f]/',$code))
   throw new DomainException('Enter a valid COOP account code.');
  $code=trim($code);
  coop_registered_check_duplicate($db,$areas,$code);
  if(($connection['type']??null)==='https'){
   try{$account=coop_https_lookup($connection,$code);}
   catch(DomainException){$account=['status'=>'Unable to retrieve data'];}
  }else $account=coop_lookup_accounts($connection,[$code])[$code];
 }else{
  $id=$input['id']??null;$expected=$input['expectedArea']??null;
  if(($input['confirmed']??false)!==true||!is_string($id)||!ctype_digit($id)||strlen($id)>20||!is_int($expected))
   throw new DomainException('Confirm the selected account action.');
 }
 $db->begin_transaction();
 try{
  if($action==='accounts-add'){
   try{
    $db->execute_query('INSERT INTO coop_registered_accounts (AcctCode,AcctNo,AccountName,GenstatAreaID) VALUES (?,?,?,?)',
     [$code,$account['AcctNo']??null,$account['Name']??null,$area]);
   }catch(mysqli_sql_exception $error){
    if($error->getCode()!==1062)throw $error;
    coop_registered_check_duplicate($db,$areas,$code,$account['AcctNo']??null);
    throw new DomainException('Account already registered.',409);
   }
   $details=['id'=>(string)$db->insert_id,'toArea'=>$area];
   $message='Account added.';
  }else{
   $row=$db->execute_query('SELECT GenstatAreaID FROM coop_registered_accounts WHERE ID=? FOR UPDATE',[$id])->fetch_assoc();
   if(!$row||(int)$row['GenstatAreaID']!==$expected)
    throw new DomainException('The account changed. Refresh the list and try again.',409);
   $details=['id'=>$id,'fromArea'=>$expected];
   if($action==='accounts-transfer'){
    if($area===$expected)throw new DomainException('Select a different GENSTAT area.');
    $db->execute_query('UPDATE coop_registered_accounts SET GenstatAreaID=? WHERE ID=?',[$area,$id]);
    $details['toArea']=$area;$message='Account transferred.';
   }else{
    $db->execute_query('DELETE FROM coop_registered_accounts WHERE ID=?',[$id]);
    $message='Account removed.';
   }
  }
  $actor=substr(($_SERVER['REMOTE_USER']??'')?:'GENSTAT_REPORT_SESSION',0,190);
  $db->execute_query('INSERT INTO coop_audit (operation_id,action,actor,period,details) VALUES (?,?,?,?,?)',
    [bin2hex(random_bytes(16)),$action,$actor,$month,json_encode($details,JSON_THROW_ON_ERROR)]);
  $db->commit();
  return ['message'=>$message];
 }catch(Throwable $error){$db->rollback();throw $error;}
}

function coop_registered_billing(?array $connection,array $accounts,string $month,bool $verifyEmpty=false): array {
 $accounts=array_values(array_filter($accounts,fn($number)=>$number!==null));
 if(!$accounts&&!$verifyEmpty)return [];
 if(!$connection)throw new DomainException('Save a COOP connection to retrieve billing.');
 $pdo=coop_pdo($connection);
 $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
 $pdo->exec('START TRANSACTION READ ONLY');
 $bills=[];
 try{
  if(!$accounts){
   $statement=$pdo->prepare('SELECT SUM(KWH) FROM billhistory WHERE BillMonth=? AND 1=0');
   $statement->execute([$month]);
   if(!in_array($statement->getColumnMeta(0)['native_type']??'', ['DECIMAL','NEWDECIMAL'],true))
    throw new DomainException('COOP billing KWH requires an exact decimal source.');
   $statement->closeCursor();
  }
  foreach(array_chunk($accounts,100) as $chunk){
   $parameters=implode(',',array_fill(0,count($chunk),'?'));
   $statement=$pdo->prepare("SELECT BINARY b.AcctNo AS identity, COUNT(*) AS records, COUNT(b.KWH) AS validRecords, SUM(b.KWH) AS kwh
    FROM billhistory b WHERE b.BillMonth=? AND b.AcctNo IN ($parameters)
    AND EXISTS (SELECT 1 FROM master m WHERE m.AcctNo=b.AcctNo AND BINARY m.AcctNo=BINARY b.AcctNo AND m.Status = 'A')
    GROUP BY BINARY b.AcctNo");
   $statement->execute([$month,...$chunk]);
   if(!in_array($statement->getColumnMeta(3)['native_type']??'', ['DECIMAL','NEWDECIMAL'],true))
    throw new DomainException('COOP billing KWH requires an exact decimal source.');
   while($bill=$statement->fetch(PDO::FETCH_ASSOC))$bills[$bill['identity']]=$bill;
   $statement->closeCursor();
  }
  return $bills;
 }finally{$pdo->rollBack();}
}

function coop_registered_amount(?array $bill): array {
 if(!$bill)return [null,'No billing record'];
 if((int)$bill['records']!==1)return [null,'Incomplete billing data'];
 $value=$bill['kwh'];
 if((int)$bill['validRecords']!==1||!is_string($value)||!preg_match('/^-?\d+(?:\.(\d{1,60}))?$/D',$value,$parts))
  return [null,'Incomplete billing data'];
 if(bccomp($value,'0',strlen($parts[1]??''))<0)return [null,'Incomplete billing data'];
 return [bccomp($value,'0',strlen($parts[1]??''))===0?'0.00':$value,''];
}

function coop_registered_month(mysqli $db,?array $connection,string $month,bool $verifyEmpty=false): array {
 $codes=array_column($db->query('SELECT AcctCode FROM coop_registered_accounts')->fetch_all(MYSQLI_ASSOC),'AcctCode');
 $https=($connection['type']??null)==='https';
 $failed=false;$bills=[];
 if($https)[$matches,$failed]=coop_https_month($connection,$codes,$month);
 else $matches=coop_lookup_accounts($connection,$codes);
 [$accounts,$changed]=coop_resolve_registered_accounts($db,$matches);
 if(!$https){
  try{$bills=coop_registered_billing($connection,coop_registered_active_numbers($accounts),$month,$verifyEmpty);}
  catch(PDOException|DomainException){$failed=true;}
 }
 foreach($accounts as &$account){
  $matched=$matches[$account['AcctCode']]??[];
  [$account['kwh'],$account['billingStatus']]=match(true){
   $account['status']!==''=>[null,$account['status']],
   $https=>[$matched['kwh'],$matched['billingStatus']],
   $failed=>[null,'Unable to retrieve data'],
   default=>coop_registered_amount($bills[$account['AcctNo']]??null),
  };
 }
 unset($account);
 return [$accounts,$changed,$failed||in_array('Unable to retrieve data',array_column($accounts,'status'),true)];
}

function coop_registered_view(mysqli $db,array $areas,?array $connection,string $month,array $input): array {
 $area=$input['area']??null;$search=$input['search']??'';$page=$input['page']??1;
 if(($area!==null&&(!is_int($area)||!isset($areas[$area])))||!is_string($search)||strlen($search)>200||!is_int($page)||$page<1)
  throw new DomainException('Enter a valid account filter.');
 [$assigned,$resolved,$failed]=coop_registered_month($db,$connection,$month);
 $where=[];$params=[];$search=trim($search);
 if($area!==null){$where[]='GenstatAreaID=?';$params[]=$area;}
 if($search!==''){
  $where[]='(LOCATE(LOWER(?),LOWER(CONVERT(AcctCode USING utf8mb4)))>0 OR LOCATE(LOWER(?),LOWER(CONVERT(AcctNo USING utf8mb4)))>0 OR LOCATE(LOWER(?),LOWER(AccountName))>0)';
  array_push($params,$search,$search,$search);
 }
 $filter=$where?' WHERE '.implode(' AND ',$where):'';
 $db->query('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
 $db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY);
 try{
  $matching=(int)$db->execute_query('SELECT COUNT(*) FROM coop_registered_accounts'.$filter,$params)->fetch_row()[0];
  $pages=max(1,(int)ceil($matching/25));$page=min($pages,$page);$offset=($page-1)*25;
  $rows=$db->execute_query('SELECT ID,AcctCode,AcctNo,AccountName,GenstatAreaID FROM coop_registered_accounts'.$filter.
   " ORDER BY AcctCode,ID LIMIT 25 OFFSET $offset",$params)->fetch_all(MYSQLI_ASSOC);
  $db->commit();
 }catch(Throwable $error){$db->rollback();throw $error;}
 $billingError=$failed?'Some monthly billing data could not be retrieved. Check the COOP connection and try again.':null;
 $amounts=[];
 foreach($assigned as $entry){
  $key=$entry['AcctNo'];$id=(int)$entry['GenstatAreaID'];$group=&$areas[$id];
  if(++$group['count']===1)$group['total']=null;
  $amount=$entry['kwh'];$status=$entry['billingStatus'];
  $amounts[$entry['ID']]=[$key,$amount,$status];
  if($amount===null)$group['incomplete']++;
  else{
   $total=$group['total']??'0.00';
   $scale=max(strlen(explode('.',$amount,2)[1]??''),strlen(explode('.',$total,2)[1]??''));
   $group['total']=bcadd($total,$amount,$scale);
  }
  unset($group);
 }
 foreach($rows as &$row){
  [$number,$amount,$status]=$amounts[$row['ID']]??[null,null,'Unable to retrieve data'];
  [$row['kwh'],$row['status']]=$row['AcctNo']===$number?[$amount,$status]:[null,'Unable to retrieve data'];
  $row['ID']=(string)$row['ID'];
  $row['GenstatAreaID']=(int)$row['GenstatAreaID'];
 }
 unset($row);
 return ['month'=>$month,'areas'=>array_values($areas),'rows'=>$rows,'registered'=>count($assigned),'matching'=>$matching,
  'page'=>$page,'pages'=>$pages,'billingError'=>$billingError,'resolved'=>$resolved];
}

function coop_registered_request(string $action,array $input,string $month,?array $connection): array {
 try{
  $areas=coop_registered_areas();$db=qs_db('writer');
  try{
   return $action==='accounts-list'?coop_registered_view($db,$areas,$connection,$month,$input):
    coop_registered_mutate($db,$areas,$connection,$month,$action,$input);
  }finally{$db->close();}
 }catch(mysqli_sql_exception $error){
  error_log(sprintf('COOP registry %s failed (MariaDB %d, SQLSTATE %s): %s',
   $action,$error->getCode(),$error->getSqlState(),str_replace(["\r","\n"],' ',$error->getMessage())));
  $message=$error->getMessage();
  if($action==='accounts-add'&&$error->getCode()===1048&&str_contains($message,"'AcctNo'"))
   throw new DomainException('This account needs to be saved without a canonical number. Apply GENSTAT migration 006 to allow pending registrations.',503);
  if(in_array($action,['accounts-add','accounts-transfer'],true)&&$error->getCode()===1452&&str_contains($message,'coop_registered_area_fk'))
   throw new DomainException('The selected GENSTAT area is no longer available. Refresh the account list and select an area.',409);
  if($action==='accounts-add'&&in_array($error->getCode(),[1366,1406],true))
   throw new DomainException('The account details contain characters or lengths the registry cannot store.');
  throw new DomainException('GENSTAT could not access the account registry. Check the server log for details.',503);
 }
}
