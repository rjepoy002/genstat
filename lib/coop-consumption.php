<?php
declare(strict_types=1);

function coop_decimal($value): string {
 if((!is_string($value)&&!is_int($value))||!preg_match('/^-?[0-9]{1,24}(?:\.[0-9]{1,12})?$/D',(string)$value))
  throw new DomainException('Invalid KWH.');
 return bcadd((string)$value,'0',12);
}
function coop_identifier($value): string {
 if(!is_string($value)||$value===''||strlen($value)>128||preg_match('/[\x00-\x1f\x7f]/',$value)||!preg_match('//u',$value))
  throw new DomainException('Enter a valid account identifier.');
 return $value;
}
function coop_identity(array $rows,string $code): string {
 $exact=array_values(array_filter($rows,fn($row)=>$row['AcctCode']===$code));
 if(!$exact)throw new DomainException('Account Not Found');
 if(count($exact)!==1)throw new DomainException('Ambiguous Account');
 return coop_identifier($exact[0]['AcctNo']);
}
function coop_aggregate(array $accounts,array $master,array $bills,array $targets,array $working,bool $provisional=false): array {
 $areas=[];$details=[];$seen=[];$complete=(bool)$accounts;$total=coop_decimal('0');
 foreach($targets as $key=>$target){
  $areas[$key]=$target+['enrolled'=>0,'retrieved'=>0,'missing'=>0,'kwh'=>null,'current'=>$working[$target['ref']]??null,'difference'=>null];
 }
 foreach($accounts as $account){
  $key=$account['area_key'];if(!isset($areas[$key]))throw new DomainException('Enrollment area mapping requires review.');
  $areas[$key]['enrolled']++;
  $entry=['id'=>(string)$account['id'],'area'=>$areas[$key]['label'],'count'=>0,'kwh'=>null];
  try{
   $identity=coop_identity($master[$account['acct_code']]??[],$account['acct_code']);
   if($identity!==$account['canonical_acct_no'])throw new DomainException('Changed account identity');
   if(isset($seen[$identity]))throw new DomainException('Duplicate canonical account');
   $seen[$identity]=true;$billing=$bills[$identity]??null;
   if(!$billing)throw new DomainException('No Bill History');
   $entry['count']=(int)$billing['records'];
   if($provisional&&$entry['count']!==1)throw new DomainException('Multiple billing records');
   if($entry['count']!==(int)$billing['valid_records'])throw new DomainException('Invalid KWH');
   $kwh=coop_decimal($billing['kwh']);
   if($provisional&&bccomp($kwh,'0',12)<0)throw new DomainException('Negative KWH');
   $entry['kwh']=$kwh;$entry['status']=bccomp($kwh,'0',12)===0?'Zero KWH':'Retrieved';
   $areas[$key]['retrieved']++;
   $areas[$key]['kwh']=bcadd($areas[$key]['kwh']??'0',$kwh,12);
   $total=bcadd($total,$kwh,12);
  }catch(DomainException $e){
   $complete=false;$areas[$key]['missing']++;$entry['status']=$e->getMessage();
   if($provisional){
    $entry['reason']=$entry['status'];
    $entry['status']=$entry['reason']==='No Bill History'?'No billing data':'Requires billing review';
   }
  }
  $details[]=$entry;
 }
 foreach($areas as &$area){
  if($area['missing']){$area['kwh']=null;continue;}
  if($area['kwh']!==null&&strlen(trim(str_replace('.','',ltrim($area['kwh'],'-')),'0'))>15){
   $area['error']='Area total exceeds Excel numeric precision.';
   $complete=false;
  }
  if($area['kwh']!==null&&$area['current']!==null){
   try{$area['difference']=bcsub($area['kwh'],coop_decimal($area['current']),12);}catch(DomainException){}
  }
 }unset($area);
 return ['accounts'=>$details,'areas'=>$areas,'total'=>$complete?$total:null,'complete'=>$complete];
}
