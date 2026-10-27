<?php
declare(strict_types=1);
require_once __DIR__.'/build-layout.php';
require_once __DIR__.'/../lib/workbook-storage.php';
// CLI only. Read-only comparison, with full per-sheet cell and layout differences.
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$oldFile=$argv[1]??__DIR__.'/../genstat.xlsx';$newFile=$argv[2]??workbook_path(__DIR__.'/..');
$old=build_layout($oldFile);$new=build_layout($newFile);$report=['oldHash'=>$old['sha256'],'newHash'=>$new['sha256'],'oldSheets'=>array_column($old['sheets'],'name'),'newSheets'=>array_column($new['sheets'],'name'),'workbookChanges'=>[],'sheets'=>[],'externalLinks'=>$new['externalLinks'],'externalFormulaReferences'=>[],'changedPackageParts'=>[]];
foreach(['styles','themeColors','definedNames','externalLinks'] as $key)if($old[$key]!==$new[$key])$report['workbookChanges'][$key]=['before'=>$old[$key],'after'=>$new[$key]];
$byName=array_column($old['sheets'],null,'name');
foreach($new['sheets'] as $sheet){$before=$byName[$sheet['name']]??null;$diff=['added'=>$before===null,'properties'=>[],'cells'=>[]];
 foreach(['state','range','rows','columns','merges','settings','formulaRanges','comments'] as $key)if(($before[$key]??null)!==$sheet[$key])$diff['properties'][$key]=['before'=>$before[$key]??null,'after'=>$sheet[$key]];
 foreach(array_unique(array_merge(array_keys($before['cells']??[]),array_keys($sheet['cells']))) as $ref){$a=$before['cells'][$ref]??null;$b=$sheet['cells'][$ref]??null;$changes=[];foreach(['value','type','formula','formulaAttributes','style'] as $key)if(($a[$key]??null)!==($b[$key]??null))$changes[$key]=['before'=>$a[$key]??null,'after'=>$b[$key]??null];if($changes)$diff['cells'][$ref]=$changes;if(preg_match('/\[\d+\]/',$b['formula']??''))$report['externalFormulaReferences'][$sheet['name'].'!'.$ref]=$b['formula'];}
 $report['sheets'][$sheet['name']]=$diff;
}
$a=zip_read($oldFile);$b=zip_read($newFile);foreach(array_unique(array_merge(array_keys($a),array_keys($b))) as $part)if(!isset($a[$part],$b[$part])||zip_text($a,$part)!==zip_text($b,$part))$report['changedPackageParts'][]=$part;
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),"\n";
