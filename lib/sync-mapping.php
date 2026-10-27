<?php
declare(strict_types=1);

final class SyncConflict extends RuntimeException {}
function sync_conflict(string $sheet,string $cell,string $reason): never {
 throw new SyncConflict($sheet.'!'.$cell.': '.$reason.' Correct the workbook and select Sync Now.');
}
function sync_context(array $sheet,string $ref): array {
 [$col,$row]=cell_coordinates($ref);$left='';$above='';
 for($c=$col-1;$c>=1;$c--){$v=$sheet['cells'][formula_col($c).$row]??null;if($v&&$v['formula']===null&&$v['value']!==''&&!is_numeric($v['value'])){$left=$v['value'];break;}}
 for($r=$row-1;$r>=1;$r--){$v=$sheet['cells'][formula_col($col).$r]??null;if($v&&$v['formula']===null&&$v['value']!==''&&!is_numeric($v['value'])){$above=$v['value'];break;}}
 return [$left,$above];
}
function sync_axis_map(array $old,array $new): array {
 $a=[];$b=[];foreach($old['cells'] as $ref=>$c)if($c['formula']===null&&$c['value']!==''&&!is_numeric($c['value']))$a[$c['value']][]=$ref;
 foreach($new['cells'] as $ref=>$c)if($c['formula']===null&&$c['value']!==''&&!is_numeric($c['value']))$b[$c['value']][]=$ref;
 $rows=[];$cols=[];foreach($a as $label=>$refs)if(count($refs)===1&&count($b[$label]??[])===1){[$c,$r]=cell_coordinates($refs[0]);[$nc,$nr]=cell_coordinates($b[$label][0]);$rows[$r][$nr]=true;$cols[$c][$nc]=true;}
 return [$rows,$cols];
}
function sync_axis_coordinate(array $anchors,int $position): ?int {
 if(isset($anchors[$position]))return count($anchors[$position])===1?(int)array_key_first($anchors[$position]):null;
 $deltas=[];foreach($anchors as $from=>$targets)if(count($targets)===1)$deltas[]=(int)array_key_first($targets)-(int)$from;
 // Identity is safe when all recognizable anchors stayed in place. Otherwise
 // require bracketing anchors with the same displacement for an unlabeled cell.
 if(!$deltas||count(array_unique($deltas))===1&&$deltas[0]===0)return $position;
 $below=null;$above=null;ksort($anchors);
 foreach($anchors as $from=>$targets){if(count($targets)!==1)continue;$delta=(int)array_key_first($targets)-(int)$from;if($from<$position)$below=$delta;elseif($from>$position){$above=$delta;break;}}
 return $below!==null&&$above!==null&&$below===$above?$position+$below:null;
}
function sync_coordinate_axes(array $old,array $next): array {
 $axes=[];foreach($old['sheets'] as $before){
  $after=array_values(array_filter($next['sheets'],fn($s)=>$s['name']===$before['name']))[0]??null;if(!$after)continue;
  [$rows,$cols]=sync_axis_map($before,$after);$end=explode(':',$before['range']);[$maxCol,$maxRow]=cell_coordinates(end($end));$rowMap=[];$colMap=[];
  for($r=1;$r<=$maxRow;$r++)$rowMap[$r]=sync_axis_coordinate($rows,$r);
  for($c=1;$c<=$maxCol;$c++)$colMap[$c]=sync_axis_coordinate($cols,$c);
  $axes[$before['name']]=['rows'=>$rowMap,'columns'=>$colMap];
 }
 $history=$old['coordinateAxes']??[];
 foreach($history as &$version)foreach($version as $name=>&$mapping)foreach(['rows','columns'] as $axis)foreach($mapping[$axis] as &$target)$target=$target===null?null:($axes[$name][$axis][$target]??null);
 unset($version,$mapping,$target);
 $history[$old['sha256']]=$axes;return $history;
}
function sync_axis_cell(array $axes,string $sheet,string $ref): ?string {
 [$col,$row]=cell_coordinates($ref);$r=$axes[$sheet]['rows'][$row]??null;$c=$axes[$sheet]['columns'][$col]??null;
 return $r!==null&&$c!==null?formula_col($c).$r:null;
}
function sync_cell_map(array $old,array $new,string $ref,array $axes): string {
 [$col,$row]=cell_coordinates($ref);[$rows,$cols]=$axes;
 $rowTargets=array_keys($rows[$row]??[$row=>true]);$colTargets=array_keys($cols[$col]??[$col=>true]);
 $comment=$old['comments'][$ref]??null;$candidates=[];
 if($comment!==null){$copies=count(array_filter($new['comments'],fn($text)=>$text===$comment));foreach($new['comments'] as $candidate=>$text)if($text===$comment){[$c,$r]=cell_coordinates($candidate);$rowMatch=($copies>1&&!isset($rows[$row]))||in_array($r,$rowTargets,true);$colMatch=($copies>1&&!isset($cols[$col]))||in_array($c,$colTargets,true);if($rowMatch&&$colMatch)$candidates[]=$candidate;}
  if(count($candidates)>1){$context=sync_context($old,$ref);$candidates=array_values(array_filter($candidates,fn($r)=>sync_context($new,$r)===$context));}
  if(count($candidates)!==1)sync_conflict($old['name'],$ref,'Input identity is missing or ambiguous');
  return $candidates[0];
 }
 if(count($rowTargets)!==1||count($colTargets)!==1)sync_conflict($old['name'],$ref,'Surrounding field context is ambiguous');
 $candidate=formula_col($colTargets[0]).$rowTargets[0];
 if(!isset($new['cells'][$candidate]))sync_conflict($old['name'],$ref,'Mapped cell was deleted');
 return $candidate;
}
function sync_reconcile(array $old,array $next): array {
 $byName=[];foreach($next['sheets'] as $sheet){if(isset($byName[$sheet['name']])||$sheet['name']===''||strlen($sheet['name'])>124||preg_match('~[\x00-\x1f:/\\\\?*\[\]]~',$sheet['name']))throw new SyncConflict('Invalid or duplicate worksheet identifier.');$byName[$sheet['name']]=$sheet;}
 // Keep the existing report as primary even if Excel reorders tabs.
 $ordered=[];foreach($old['sheets'] as $sheet){if(!isset($byName[$sheet['name']]))sync_conflict($sheet['name'],'A1','Existing worksheet was removed or renamed');$ordered[]=$byName[$sheet['name']];unset($byName[$sheet['name']]);}$next['sheets']=array_merge($ordered,array_values($byName));
 foreach($next['sheets'] as $i=>&$sheet)if($i!==0){foreach($sheet['cells'] as &$cell){$cell['editable']=false;$cell['inputType']=null;$cell['evidence']=null;}unset($cell);}unset($sheet);
 $before=$old['sheets'][0];$after=&$next['sheets'][0];$axes=sync_axis_map($before,$after);$map=[];$approvals=[];
 foreach($old['approvedInputDefinitions'] as $ref=>$evidence){
  // Legacy annotations must not block a new template. Actual saved fields are
  // checked separately, and an unmappable saved coordinate still conflicts.
  try{$target=sync_cell_map($before,$after,$ref,$axes);if(isset($approvals[$target]))sync_conflict($before['name'],$ref,'Multiple inputs map to '.$target);$map[$ref]=$target;$approvals[$target]=$evidence;}
  catch(SyncConflict $e){$map[$ref]=null;}
 }
 foreach($after['cells'] as &$cell){$cell['editable']=false;$cell['inputType']=null;$cell['evidence']=null;}unset($cell);
 foreach($approvals as $ref=>$evidence){
  if(!isset($after['cells'][$ref]))sync_conflict($before['name'],$ref,'Mapped input cell was removed from the worksheet');
  $cell=&$after['cells'][$ref];$owner=merged_owner($after['merges'],$ref);if($owner!==null&&$owner!==$ref)sync_conflict($before['name'],$ref,'Input is covered by a merged cell');$cell['evidence']=$evidence;$cell['editable']=!$cell['formulaProtected'];$cell['inputType']=$cell['editable']?'number':null;$cell['classification']=$cell['formulaProtected']?'formula':'manual-input';unset($cell);
 }
 $after['analysis']['inputCount']=count(array_filter($after['cells'],fn($c)=>$c['editable']));$next['approvedInputDefinitions']=$approvals;
 // Track other cells too, so saved authorized formula overlays can be reconciled.
 foreach($before['cells'] as $ref=>$cell)if(!isset($map[$ref])){
  try{$map[$ref]=sync_cell_map($before,$after,$ref,$axes);}catch(SyncConflict $e){/* Unmapped protected cells do not own data. Draft checks below reject their use. */}
 }
 $maps=$old['coordinateMaps']??[];foreach($maps as &$origin)foreach($origin as $ref=>&$target)$target=$map[$target]??null;unset($origin,$target);
 $maps[$old['sha256']]=$map;$next['coordinateMaps']=$maps;
 $next['coordinateAxes']=sync_coordinate_axes($old,$next);
 foreach($old['sheets'] as $prior){
  $i=array_search($prior['name'],array_column($next['sheets'],'name'),true);
  if($i===false)continue;
  if($prior['readOnly']??false)$next['sheets'][$i]['readOnly']=true;
  foreach($prior['cells'] as $ref=>$cell)if($cell['readOnly']??false){
   $target=$i===0&&array_key_exists($ref,$map)?$map[$ref]:sync_axis_cell($next['coordinateAxes'][$old['sha256']],$prior['name'],$ref);
   if(!$target||!($current=worksheet_cell($next['sheets'][$i],$target)))sync_conflict($prior['name'],$ref,'Explicit read-only rule cannot be mapped safely');
   $current['readOnly']=true;$next['sheets'][$i]['cells'][$target]=$current;
  }
 }
 return apply_browser_edit_policy($next);
}
function sync_project_draft(array $draft,array $old,array $next): array {
 $name=$draft['sheet']??$old['sheets'][0]['name'];
 $oldSheet=array_values(array_filter($old['sheets'],fn($s)=>$s['name']===$name))[0]??null;
 $sheet=array_values(array_filter($next['sheets'],fn($s)=>$s['name']===$name))[0]??null;
 if(!$sheet)sync_conflict($name,'A1','Saved worksheet was removed or renamed');
 $map=$next['coordinateMaps'][$draft['templateHash']??'']??null;
 if(($draft['templateHash']??null)===$next['sha256'])return $draft;
 if(!(array)($draft['values']??[])&&!(array)($draft['formulas']??[])) {
  // An empty draft owns no coordinates to migrate. Keep its bytes/revision on
  // disk, but do not let a missing historical cache lock the current workbook.
  $draft['templateHash']=$next['sha256'];return $draft;
 }
 if($map===null)sync_conflict($name,'A1','Saved draft belongs to an unknown workbook version');
 $projected=$draft;$projected['values']=[];$projected['formulas']=[];$projected['sourceCells']=[];$used=[];
 foreach(['values','formulas'] as $kind)foreach((array)($draft[$kind]??[]) as $ref=>$value){
  $axes=$next['coordinateAxes'][$draft['templateHash']??'']??null;
  $target=$name===$next['sheets'][0]['name']&&array_key_exists($ref,$map)?$map[$ref]:($axes!==null?sync_axis_cell($axes,$name,$ref):$ref);
  $after=$target?worksheet_cell($sheet,$target):null;if(!$after)sync_conflict($sheet['name'],$ref,'Saved field was deleted or cannot be mapped');
  if(isset($used[$target]))sync_conflict($sheet['name'],$target,'Saved field mappings collide');$used[$target]=true;
  $before=$draft['sourceCells'][$ref]??($oldSheet?worksheet_cell($oldSheet,$ref):null);
  if(!$before)sync_conflict($sheet['name'],$ref,'Saved field has no source identity');
  if($kind==='values'){
   if(!$after['editable']||$after['formulaProtected'])sync_conflict($sheet['name'],$target,'Saved input became protected or a formula');
   if($before['value']!==$after['value']&&$value!==$after['value'])sync_conflict($sheet['name'],$target,'Both Excel and saved web values changed');
  }else{
   // Rewriting a user expression requires more than moving its owning cell.
   if($target!==$ref)sync_conflict($sheet['name'],$ref,'Saved formula moved; its references cannot be safely rewritten');
   if($before['formula']!==$after['formula']||($before['attributes']??$before['formulaAttributes']??null)!==$after['formulaAttributes'])sync_conflict($sheet['name'],$target,'Excel and saved web formulas changed');
  }
  $projected[$kind][$target]=$value;$projected['sourceCells'][$target]=['value'=>$after['value'],'formula'=>$after['formula'],'attributes'=>$after['formulaAttributes']];
 }
 $projected['templateHash']=$next['sha256'];return $projected;
}

