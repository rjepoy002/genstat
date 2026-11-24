<?php
declare(strict_types=1);

/** Run both views over the same validated, account-grain monthly query. */
function coop_account_preview(PDO $pdo,string $source,array $params,array $columns,string $month,?array $detail): array {
 $required=['areaid','areaname','accountid','kwh'];
 $names=array_change_key_case($columns);
 foreach($required as $name)
  if(!isset($names[$name]))throw new DomainException('Account preview requires AreaID, AreaName, AccountID and KWH aliases. AccountName is optional.');
 $field=static fn(string $alias): string=>'q.`'.str_replace('`','``',$names[$alias]['name']).'`';
 if(!in_array($names['kwh']['native_type']??'', ['DECIMAL','NEWDECIMAL','TINY','SHORT','LONG','LONGLONG','INT24'],true))
  throw new DomainException('Account KWH must use an exact DECIMAL or integer SQL type.');
 $id=$field('accountid');$area=$field('areaid');$label=$field('areaname');$kwh=$field('kwh');
 $name=isset($names['accountname'])?$field('accountname'):'NULL';
 $run=static function(string $sql,array $extra=[])use($pdo,$params): array {
  $statement=$pdo->prepare($sql);
  $statement->execute([...$params,...$extra]);
  $rows=$statement->fetchAll(PDO::FETCH_ASSOC);
  $statement->closeCursor();
  return $rows;
 };
 $groups=$run("SELECT MIN($area) AS id, MIN($label) AS label, COUNT(*) AS accountCount,
  COUNT(DISTINCT BINARY $label) AS labels,
  COUNT(DISTINCT BINARY $id) AS distinctAccounts, SUM($kwh) AS total,
  SUM(CASE WHEN $area IS NULL OR TRIM($area)='' OR OCTET_LENGTH($area)>128
   OR $label IS NULL OR TRIM($label)='' OR OCTET_LENGTH($label)>256
   OR $id IS NULL OR TRIM($id)='' OR OCTET_LENGTH($id)>128
   OR OCTET_LENGTH($name)>512 OR $kwh IS NULL THEN 1 ELSE 0 END) AS invalid
  FROM ($source) AS q GROUP BY BINARY $area ORDER BY MIN($label),BINARY $area LIMIT 501");
 if(count($groups)>500)throw new DomainException('Account preview exceeds 500 areas. Check the area mapping.');
 foreach($groups as $group){
  if((int)$group['invalid'] || (int)$group['labels']!==1 || (int)$group['accountCount']!==(int)$group['distinctAccounts'])
   throw new DomainException('Account results require one valid row per account and a consistent name for each area. Review the SQL grouping and mapping.');
 }
 if(count($groups)>1){
  [$global]=$run("SELECT COUNT(DISTINCT BINARY $id) AS accounts FROM ($source) AS q");
  if((int)$global['accounts']!==array_sum(array_column($groups,'accountCount')))
   throw new DomainException('An account appears in multiple areas. Review the SQL area assignment.');
 }
 $areas=[];$total='0';$scale=0;$count=0;
 foreach($groups as $group){
  $amount=$group['total'];
  $scale=max($scale,strlen(explode('.',$amount,2)[1]??''));
  $total=bcadd($total,$amount,$scale);
  $count+=(int)$group['accountCount'];
  $areas[]=['id'=>$group['id'],'label'=>$group['label'],'count'=>(int)$group['accountCount'],'total'=>$amount];
 }
 if($detail===null)return [
  'month'=>$month,'monthLabel'=>base_report_month_label($month),'areas'=>$areas,'count'=>$count,'total'=>$total,'retrievedAt'=>gmdate('c')
 ];
 $selected=null;
 foreach($areas as $item)if($item['id']===$detail['area'])$selected=$item;
 if($selected===null)throw new DomainException('The area is no longer in this monthly preview. Refresh the results.');
 $search=$detail['search'];$size=50;$page=$detail['page'];
 $where=" WHERE BINARY $area = BINARY ?";
 $bind=[$selected['id']];
 if($search!==''){
  $where.=" AND (LOCATE(LOWER(?),LOWER($id))>0 OR LOCATE(LOWER(?),LOWER($name))>0)";
  array_push($bind,$search,$search);
 }
 [$matches]=$run("SELECT COUNT(*) AS matches FROM ($source) AS q".$where,$bind);
 $maxPage=max(1,(int)ceil((int)$matches['matches']/$size));
 $page=min($page,$maxPage);$offset=($page-1)*$size;
 $rows=$run("SELECT $id AS account, $name AS name, $kwh AS kwh FROM ($source) AS q".$where.
  " ORDER BY BINARY $id LIMIT $size OFFSET $offset",$bind);
 return ['month'=>$month,'area'=>$selected,'rows'=>$rows,'page'=>$page,'pages'=>$maxPage,'matches'=>(int)$matches['matches']];
}

function coop_account_detail_input(array $body): array {
 $area=$body['area']??null;$search=$body['search']??'';$page=$body['page']??1;
 if(!is_string($area)||$area===''||strlen($area)>128||!is_string($search)||strlen($search)>200||
   !is_int($page)||$page<1||$page>100000000)
  throw new DomainException('Enter a valid area, account search and page.');
 return ['area'=>$area,'search'=>trim($search),'page'=>$page];
}
