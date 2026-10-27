<?php
declare(strict_types=1);
require __DIR__.'/../lib/workbook-policy.php';require __DIR__.'/../lib/formula-engine.php';require __DIR__.'/../lib/sync-mapping.php';
function editing_check(bool $ok,string $label):void {if(!$ok)throw new RuntimeException($label);echo 'PASS: '.$label,"\n";}
function fixture_sheet(string $name,int $shift=0,int $column=0):array {
 $s=['name'=>$name,'state'=>'visible','path'=>'xl/worksheets/'.$name.'.xml','range'=>'A1:D10','rows'=>[],'columns'=>[['min'=>'1','max'=>'4','style'=>'5','width'=>'12']],'merges'=>[],'settings'=>[],'comments'=>[],'formulaRanges'=>[],'cells'=>[],'analysis'=>[]];
 foreach([[2+$column,1,'Area North'],[1+$column,3+$shift,'Metered sales'],[2+$column,3+$shift,'10'],[1+$column,7+$shift,'Total']] as [$c,$r,$v])$s['cells'][formula_col($c).$r]=['style'=>5,'value'=>$v,'type'=>is_numeric($v)?'':'inlineStr','formula'=>null,'formulaAttributes'=>null,'formulaProtected'=>false,'formulaKind'=>null];
 return $s;
}
function fixture_layout(string $hash,array $jan):array {return apply_browser_edit_policy(['sha256'=>$hash,'styles'=>[],'themeColors'=>[],'definedNames'=>[],'approvedInputDefinitions'=>[],'sheets'=>[fixture_sheet('Jun26'),$jan]]);}
$s=fixture_sheet('Jun26');$s['merges']=['A1:B1'];$s['formulaRanges']=[['range'=>'C2:C4','anchor'=>'C2','kind'=>'array']];$s['cells']['D3']=['style'=>5,'value'=>'7','formula'=>'1+6','formulaProtected'=>true,'formulaKind'=>'ordinary'];$s['cells']['D4']=['style'=>5,'value'=>'restricted','formula'=>null,'readOnly'=>true];
editing_check(worksheet_cell($s,'B5')['editable']&&worksheet_cell($s,'B5')['style']===5,'Implicit blanks inherit column formatting and remain editable');
editing_check(!worksheet_cell($s,'B1')['editable']&&!worksheet_cell($s,'D4')['editable'],'Covered merges and explicit application read-only flags stay protected');
editing_check(!worksheet_cell($s,'D3')['editable']&&worksheet_cell($s,'D3')['formula']==='1+6','Underlying formula protects a cell even when the cached result is numeric');
editing_check(!worksheet_cell($s,'C3')['editable']&&worksheet_cell($s,'C3')['formulaKind']==='array','Implicit array result cells retain array protection');
$a=fixture_layout('version-a',fixture_sheet('Jan26'));$a['sheets'][1]['cells']['B4']=worksheet_cell($a['sheets'][1],'B4');$a['sheets'][1]['cells']['B4']['readOnly']=true;$b=sync_reconcile($a,fixture_layout('version-b',fixture_sheet('Jan26',1)));
editing_check(!worksheet_cell($b['sheets'][1],'B5')['editable'],'Explicit read-only rules survive template row changes');
$draft=['sheet'=>'Jan26','templateHash'=>'version-a','values'=>['B3'=>'20','B5'=>'99'],'formulas'=>[],'revision'=>9,'sourceCells'=>[]];foreach(['B3','B5'] as $ref){$c=worksheet_cell($a['sheets'][1],$ref);$draft['sourceCells'][$ref]=['value'=>$c['value'],'formula'=>$c['formula'],'attributes'=>$c['formulaAttributes']??null];}
$raw=serialize($draft);$projected=sync_project_draft($draft,$a,$b);
editing_check($projected['values']===['B4'=>'20','B6'=>'99'],'Monthly drafts map explicit and implicit blank inputs safely across inserted rows');
$c=sync_reconcile($b,fixture_layout('version-c',fixture_sheet('Jan26',1,1)));$projected=sync_project_draft($draft,$a,$c);
editing_check($projected['values']===['C4'=>'20','C6'=>'99'],'Monthly draft coordinates compose across multiple workbook versions and inserted columns');
editing_check(serialize($draft)===$raw,'Draft projection preserves the original saved draft and revision');


// An empty historical draft must not require a coordinate map.
$empty=['sheet'=>'Jan26','templateHash'=>'unknown-history','values'=>[],'formulas'=>[],'revision'=>3];
editing_check(sync_project_draft($empty,$a,$c)['templateHash']==='version-c','An empty stale draft cannot disable editing when historical cache metadata is missing');
