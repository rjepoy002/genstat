<?php
require_once __DIR__.'/build-layout.php';
require_once __DIR__.'/../lib/render.php';
$l=build_layout($argv[1]);
foreach($l['sheets'] as $s){
 echo $s['name'].' '.$s['range'].PHP_EOL;
 if($s['name']!=='Jul26')continue;
 foreach($s['cells'] as $ref=>$c)if(preg_match('/^A\d+$/',$ref)||preg_match('/^[A-Z]+[89]$/',$ref))echo "$ref ".json_encode([$c['value'],$c['formula'],cell_css($l,$c['style'])['background-color']??'',cell_css($l,$c['style'])['color']]).PHP_EOL;
 echo 'MERGES '.json_encode($s['merges']).PHP_EOL;
}
file_put_contents(sys_get_temp_dir().'/genstat-base-layout.json',json_encode($l));
