<?php
declare(strict_types=1);
require_once __DIR__.'/report-areas.php';
require_once __DIR__.'/base-report-view.php';
// SQL replacement provenance is separate from ordinary editable values and
// manual formula overlays. Source formulas/protection metadata never change.
function sql_replacement_secret(): string {
 $file=__DIR__.'/../storage/sql-replacement.key';$stream=fopen($file,'c+b');if(!$stream||!flock($stream,LOCK_EX))throw new RuntimeException('Cannot secure SQL replacement approvals.');
 try{$secret=stream_get_contents($stream);if($secret===''){$secret=random_bytes(32);rewind($stream);if(fwrite($stream,$secret)!==32||!fflush($stream))throw new RuntimeException('Cannot persist SQL replacement approval key.');}if(strlen($secret)!==32)throw new RuntimeException('SQL replacement approval key is invalid.');return $secret;}finally{flock($stream,LOCK_UN);fclose($stream);}
}
function sql_replacement_signature(array $record): string {unset($record['signature']);ksort($record);return hash_hmac('sha256',json_encode($record,JSON_THROW_ON_ERROR),sql_replacement_secret());}
function sql_replacement_cell_allowed(array $sheet,string $ref): bool {
 $cell=$sheet['cells'][$ref]??null;if(!$cell||!in_array($cell['formulaKind']??'ordinary',['ordinary','shared'],true)||!empty($cell['readOnly'])||!empty($sheet['readOnly']))return false;
 $owner=merged_owner($sheet['merges'],$ref);if($owner!==null&&$owner!==$ref)return false;
 foreach($sheet['formulaRanges']??[] as $range)if(in_array($range['kind'],['array','dataTable'],true)&&range_contains($range['range'],$ref))return false;
 return true;
}
// Approval is tied to the current visible business row, never a browser flag.
function sql_replacement_row_approved(array $layout,string $rowKey): bool {
 $sheet=$layout['sheets'][0];$key='genstat-base/'.$sheet['name'].'/A18';
 return $rowKey===$key&&trim((string)($sheet['cells']['A18']['value']??''))==='Total KWH Sold'
  &&!base_report_row_hidden((string)$sheet['cells']['A18']['value'])&&range_contains($layout['report']['range'],'A18');
}
function sql_replacement_validate(array $layout,array $replacements,array $formulas=[]): void {
 $sheet=$layout['sheets'][0];$source=expanded_formulas($sheet);
 foreach($replacements as $ref=>$record){
  if(!is_array($record)||!isset($source[$ref])||!range_contains($layout['report']['range'],$ref)||!sql_replacement_cell_allowed($sheet,$ref))throw new InvalidArgumentException('Invalid SQL formula replacement: '.$ref);
  if(($record['sourceHash']??'')!==$layout['sha256']||($record['sheet']??'')!==$sheet['name']||($record['ref']??'')!==$ref||($record['originalFormula']??null)!==$source[$ref]||!is_string($record['value']??null)||!is_numeric($record['value'])||!is_finite((float)$record['value']))throw new InvalidArgumentException('SQL replacement provenance is invalid: '.$ref);
  if(!isset(report_area_headers($layout)[cell_coordinates($ref)[0]])||!sql_replacement_row_approved($layout,(string)($record['rowKey']??'')))throw new InvalidArgumentException('SQL formula replacement is outside approved input rows: '.$ref);
  if(($record['rowKey']??'')!=='genstat-base/'.$sheet['name'].'/A'.cell_coordinates($ref)[1])throw new InvalidArgumentException('SQL replacement belongs to a different report row: '.$ref);
  if(isset($formulas[$ref]))throw new InvalidArgumentException('Restore the SQL replacement before manually changing its formula: '.$ref);
  if(!is_string($record['signature']??null)||!hash_equals(sql_replacement_signature($record),$record['signature']))throw new InvalidArgumentException('Unapproved SQL replacement: '.$ref);
 }
}
function sql_replacement_issue(array $layout,string $ref,string $value,array $config,int $revision,?string $workingFormula=null): array {
 if(!sql_replacement_row_approved($layout,$config['rowKey'])||cell_coordinates($ref)[1]!==18||!isset(report_area_headers($layout)[cell_coordinates($ref)[0]])||!isset(expanded_formulas($layout['sheets'][0])[$ref])||!sql_replacement_cell_allowed($layout['sheets'][0],$ref))throw new InvalidArgumentException('Unapproved SQL formula replacement target: '.$ref);
 $sheet=$layout['sheets'][0];$record=['sourceHash'=>$layout['sha256'],'sheet'=>$sheet['name'],'ref'=>$ref,'rowKey'=>$config['rowKey'],'queryKey'=>$config['queryKey'],'mappingRevision'=>$revision,'value'=>$value,'originalFormula'=>expanded_formulas($sheet)[$ref],'replacedFormula'=>$workingFormula??expanded_formulas($sheet)[$ref],'approvedAt'=>gmdate('c'),'approvalId'=>bin2hex(random_bytes(16))];
 $record['signature']=sql_replacement_signature($record);return $record;
}
function sql_replacement_working_layout(array $layout,array $replacements): array {
 if(!$replacements)return $layout;
 // Materialize shared expressions only in the in-memory working copy, so
 // replacing a shared anchor cannot drop its protected sibling calculations.
 $sheet=&$layout['sheets'][0];foreach(expanded_formulas($sheet) as $ref=>$formula){$sheet['cells'][$ref]['formula']=substr($formula,1);$sheet['cells'][$ref]['formulaAttributes']=[];}
 foreach($replacements as $ref=>$record){$sheet['cells'][$ref]['formula']=null;$sheet['cells'][$ref]['formulaAttributes']=null;$sheet['cells'][$ref]['value']=$record['value'];$sheet['cells'][$ref]['type']='n';}unset($sheet);return $layout;
}
function sql_replacement_submission(array $layout,array $incoming,array $latest): void {
 sql_replacement_validate($layout,$incoming);
 foreach($incoming as $ref=>$record)if(($latest[$ref]['signature']??null)!==$record['signature']&&empty($_SESSION['sqlReplacementGrants'][$record['signature']]))throw new InvalidArgumentException('SQL replacement requires a confirmed review: '.$ref);
 foreach($latest as $ref=>$record)if(!isset($incoming[$ref])&&empty($_SESSION['sqlRestoreGrants'][$layout['sha256']][$ref]))throw new InvalidArgumentException('Use Restore template formula before removing an SQL replacement: '.$ref);
}
