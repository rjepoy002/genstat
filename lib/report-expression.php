<?php
declare(strict_types=1);
// Restricted arithmetic grammar. Identifiers containing punctuation use [column name]
// (escape a closing bracket as ]]). NULL is always an error. BCMath uses base-10
// arithmetic; intermediates use 24 decimal places, rounded to 12 on output.
final class ReportExpression {
 private array $tokens=[]; private int $position=0; private ?array $numericColumns=null;
 public function __construct(string $expression) {
  if(strlen($expression)>2048)throw new QuerySettingsError('Formula exceeds 2048 characters.');
  $offset=0;
  while($offset<strlen($expression)) {
   if(preg_match('/\G\s+/A',$expression,$m,0,$offset)){$offset+=strlen($m[0]);continue;}
   if(!preg_match('/\G(?:([0-9]+(?:\.[0-9]+)?|\.[0-9]+)|([A-Za-z_][A-Za-z0-9_]*)|\[((?:[^\]]|\]\])+)\]|([+*\/()\-]))/A',$expression,$m,0,$offset))throw new QuerySettingsError('Invalid formula token at character '.($offset+1).'.');
   $offset+=strlen($m[0]);
   $this->tokens[]=!empty($m[1])||($m[1]??'')==='0'?['number',$m[1]]:(!empty($m[2])?['column',$m[2]]:(!empty($m[3])?['column',str_replace(']]',']',$m[3])]:[$m[4],$m[4]]));
   if(count($this->tokens)>256)throw new QuerySettingsError('Formula exceeds 256 tokens.');
  }
  if(!$this->tokens)throw new QuerySettingsError('Enter a calculation formula.');
 }
 public function evaluate(array $row,array $columns,?array $numericColumns=null): string {
  if(!extension_loaded('bcmath'))throw new QuerySettingsError('Report formulas require the BCMath PHP extension.');
  $this->position=0;$this->numericColumns=$numericColumns;$value=$this->expression($row,$columns,0,0);
  if($this->position!==count($this->tokens))throw new QuerySettingsError('Invalid formula: unexpected token or unsupported function.');
  // Round half away from zero, retaining ample input precision for the workbook.
  $rounded=bcadd($value,bccomp($value,'0',24)<0?'-0.0000000000005':'0.0000000000005',12);
  $rounded=rtrim(rtrim($rounded,'0'),'.');return $rounded===''||$rounded==='-0'?'0':$rounded;
 }
 private function expression(array $row,array $columns,int $minimum,int $depth): string {
  if($depth>32)throw new QuerySettingsError('Formula nesting exceeds 32 levels.');
  [$type,$token]=$this->tokens[$this->position++]??['',''];
  if($type==='number')$left=$this->decimal($token);
  elseif($type==='column') {
   if(!in_array($token,$columns,true))throw new QuerySettingsError('Formula references a missing SQL column: '.$token);
   if($this->numericColumns!==null&&!in_array($token,$this->numericColumns,true))throw new QuerySettingsError('Non-numeric SQL column: '.$token);
   if(!array_key_exists($token,$row)||$row[$token]===null)throw new QuerySettingsError('NULL formula operand: '.$token.'. NULL values are not treated as zero.');
   try{$left=$this->decimal((string)$row[$token]);}catch(QuerySettingsError $e){throw new QuerySettingsError('Non-numeric or invalid formula column: '.$token);}
  }elseif($type==='+'||$type==='-'){$left=$this->expression($row,$columns,30,$depth+1);if($type==='-')$left=bcsub('0',$left,24);}
  elseif($type==='('){$left=$this->expression($row,$columns,0,$depth+1);if(($this->tokens[$this->position++][0]??'')!==')')throw new QuerySettingsError('Unbalanced formula parentheses.');}
  else throw new QuerySettingsError('Invalid formula: expected a column, constant or parenthesis.');
  while(isset($this->tokens[$this->position])) {
   $op=$this->tokens[$this->position][0];$precedence=in_array($op,['+','-'],true)?10:(in_array($op,['*','/'],true)?20:-1);if($precedence<$minimum)break;
   $this->position++;$right=$this->expression($row,$columns,$precedence+1,$depth+1);
   if($op==='/'&&bccomp($right,'0',24)===0)throw new QuerySettingsError('Formula division by zero.');
   $left=match($op){'+'=>bcadd($left,$right,24),'-'=>bcsub($left,$right,24),'*'=>bcmul($left,$right,24),'/'=>bcdiv($left,$right,24)};
   if(strlen(ltrim(explode('.',$left)[0],'-'))>30)throw new QuerySettingsError('Formula result exceeds the supported numeric range.');
  }
  return $left;
 }
 private function decimal(string $value): string {
  if(!preg_match('/^[+-]?(?:\d{1,30}(?:\.\d{1,24})?|\.\d{1,24})$/D',$value))throw new QuerySettingsError('Invalid decimal value. Use at most 30 integer and 24 decimal digits; exponent notation is unsupported.');return $value;
 }
}
function qs_formula_values(array $config,array $result): array {
 if(empty($config['formulaEnabled']))return [];
 $parser=new ReportExpression($config['formulaExpression']);$values=[];
 if(!$result['rows'])throw new QuerySettingsError('Formula requires at least one tested result row.');
 foreach($result['rows'] as $i=>$row){try{$values[]=$parser->evaluate($row,$result['columns'],$result['numericColumns']??null);}catch(QuerySettingsError $e){throw new QuerySettingsError('Result row '.($i+1).': '.$e->getMessage());}}
 return $values;
}
function qs_auto_bindings(array $l,array $config,array $result,array $areas,array $mappings): array {
 $column=$config['autoMatchColumn']??'';$bindings=$config['bindings']??[];$statuses=[];
 $known=array_column($areas,'AreaName','AreaID');
 foreach(qs_targets($l,$config) as $ref=>$cell){
  $source=$config['mappingSources'][$ref]??(isset($bindings[$ref])?'saved':'auto');
  if(in_array($source,['manual','saved'],true)){$statuses[$ref]=$source==='manual'?'Manually mapped':'Previously saved';continue;}
  $candidates=[];$label=report_area_name($cell['label']);$alias=report_area_alias($cell['label'],$areas,$mappings);
  if(($config['matchingMethod']??'normalized')!=='manual'&&in_array($column,$result['columns'],true))foreach($result['rows'] as $row){
   $id=(string)($row[$config['areaColumn']]??'');if(!isset($known[$id]))continue;
   $value=(string)($row[$column]??'');$same=($config['matchingMethod']??'normalized')==='exact'?trim($value)===trim($cell['label']):report_area_name($value)===$label;
   if(!$same&&($alias===null||$alias!==$id))continue;
   $sa=$config['sacodeColumn']!==''?(string)($row[$config['sacodeColumn']]??''):'';
   // Count result rows, not only distinct IDs: repeated identities are ambiguous too.
   $candidates[]=['areaId'=>$id,'sacode'=>$sa];
  }
  $statuses[$ref]=count($candidates)===1?'Auto-matched':(count($candidates)>1?'Ambiguous':'Unmatched');
  $bindings[$ref]=count($candidates)===1?$candidates[0]:['areaId'=>'','sacode'=>''];
 }
 return ['bindings'=>$bindings,'statuses'=>$statuses];
}
