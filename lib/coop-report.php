<?php
declare(strict_types=1);

const COOP_LOCAL_DEV_ACTOR='GENSTAT_LOCAL_DEV';

function coop_forwarding_headers(array $server): bool {
 foreach($server as $name=>$unused){
  if(str_starts_with($name,'HTTP_X_FORWARDED')||str_starts_with($name,'HTTP_X_ORIGINAL')||
     in_array($name,['HTTP_FORWARDED','HTTP_VIA','HTTP_PROXY','HTTP_PROXY_CONNECTION','HTTP_X_REAL_IP',
      'HTTP_CF_CONNECTING_IP','HTTP_TRUE_CLIENT_IP','HTTP_X_CLIENT_IP','HTTP_X_ENVOY_EXTERNAL_ADDRESS',
      'HTTP_X_REMOTE_USER','HTTP_REMOTE_USER','HTTP_X_AUTHENTICATED_USER'],true))return true;
 }
 return false;
}
function coop_local_authority(array $server): bool {
 $authority=$server['HTTP_HOST']??'';
 return is_string($authority)&&preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]{1,5})?$/iD',$authority)===1;
}
function coop_is_local_dev_request(array $server,string $sapi,bool $enabled): bool {
 $loopback=['127.0.0.1','::1'];
 if(!$enabled||($server['REMOTE_USER']??'')!==''||
    !in_array($server['REMOTE_ADDR']??null,$loopback,true)||coop_forwarding_headers($server)||!coop_local_authority($server))return false;
 return match($sapi){
  'cli-server'=>($server['GENSTAT_COOP_DEV_DIRECT']??null)==='cli-router'&&in_array($server['SERVER_NAME']??null,$loopback,true),
  'apache2handler'=>($server['GENSTAT_COOP_DEV_DIRECT']??null)==='apache-loopback'&&in_array($server['SERVER_ADDR']??null,$loopback,true),
  default=>false,
 };
}
function coop_development_active(): bool {
 return coop_is_local_dev_request($_SERVER,PHP_SAPI,getenv('GENSTAT_COOP_DEV_MODE')==='1');
}
function coop_owned(): bool {
 return getenv('GENSTAT_COOP_OWNERSHIP')==='1';
}
function coop_row_candidates(array $layout): array {
 $report=$layout['report'];$sheet=$layout['sheets'][0];$matches=[];
 for($row=$report['firstRow']+2;$row<=$report['lastRow'];$row++){
  $label=trim($sheet['cells']['A'.$row]['value']??'');
  if(report_area_name(preg_replace('/^[a-z]\.\)\s*/i','',$label))==='coop consumption')$matches[]=$row;
 }
 return $matches;
}
function coop_row(array $layout): int {
 $matches=coop_row_candidates($layout);
 if(count($matches)!==1)throw new DomainException('COOP row mapping requires review.');
 return $matches[0];
}
function coop_row_key(array $layout): string {
 return 'genstat-base/'.$layout['sheets'][0]['name'].'/A'.coop_row($layout);
}
function coop_blocks_generic(array $layout,string $key): bool {
 if(!coop_owned())return false;
 $prefix='genstat-base/'.$layout['sheets'][0]['name'].'/A';
 foreach(coop_row_candidates($layout) as $row)if($key===$prefix.$row)return true;
 return false;
}
function coop_targets(array $layout): array {
 $sheet=$layout['sheets'][0];$row=coop_row($layout);$targets=[];
 if(in_array($sheet['rows'][$row]['hidden']??'0',['1','true'],true))throw new DomainException('COOP row mapping requires review.');
 foreach(report_area_headers($layout) as $header){
  $key=$header['normalized'];$ref=$header['column'].$row;$cell=$sheet['cells'][$ref]??null;
  if(isset($targets[$key])||empty($cell['editable'])||$cell['classification']!=='database_input'||
     $cell['metadata']['effectiveFormula']!==null||merged_owner($sheet['merges'],$ref)!==null)
   throw new DomainException('COOP area mapping requires review.');
  $targets[$key]=['label'=>$header['label'],'ref'=>$ref];
 }
 if(!$targets)throw new DomainException('COOP area mapping requires review.');
 return $targets;
}
function coop_conflict(array $layout,array $targets,array $records): bool {
 $rowKey=coop_row_key($layout);
 $refs=array_flip(array_column($targets,'ref'));
 foreach($records as $key=>$record){
  if(!empty($record['enabled'])&&($key===$rowKey||array_intersect_key($record['bindings']??[],$refs)))return true;
 }
 return false;
}
function coop_source(array $layout,string $ref,string $value,string $run,?string $appliedAt=null): array {
 $proof=['kind'=>'coop','scope'=>base_report_scope($layout),'ref'=>$ref,'value'=>$value,'run'=>$run];
 $source=['run'=>$run,'value'=>$value];
 if($appliedAt!==null)$source['appliedAt']=$proof['appliedAt']=$appliedAt;
 return $source+['signature'=>sql_replacement_signature($proof)];
}
function coop_verified_sources(array $layout,$sources): array {
 if(!is_array($sources)||!$sources)return [];
 $refs=array_column(coop_targets($layout),'ref');$verified=[];
 foreach($sources as $ref=>$source){
  if(!in_array($ref,$refs,true)||!is_array($source)||!is_string($source['value']??null)||!is_string($source['run']??null)||
     !preg_match('/^[a-f0-9]{32}$/D',$source['run'])||!is_string($source['signature']??null))continue;
  $appliedAt=$source['appliedAt']??null;
  if($appliedAt!==null&&(!is_string($appliedAt)||!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D',$appliedAt)))continue;
  $expected=coop_source($layout,$ref,$source['value'],$source['run'],$appliedAt);
  if(hash_equals($expected['signature'],$source['signature']))$verified[$ref]=$expected;
 }
 return $verified;
}
