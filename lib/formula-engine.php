<?php
declare(strict_types=1);
require_once __DIR__.'/workbook-policy.php';

function formula_overlay_expression(string $value): string {
 if(str_starts_with($value,'='))return $value;
 if($value!==''&&is_numeric($value))return '='.$value;
 return '="'.str_replace('"','""',$value).'"';
}

final class FormulaError extends RuntimeException {}

// A bounded expression parser/interpreter. Spreadsheet text is never executed
// as PHP, JavaScript, shell commands, network requests or file operations.
final class FormulaParser {
 public array $tokens=[]; private int $pos=0; private int $depth=0;
 public function __construct(string $formula){
  if(strlen($formula)>2048)throw new InvalidArgumentException('Formula exceeds 2048 characters.');
  $s=str_starts_with($formula,'=')?substr($formula,1):$formula;$offset=0;
  $reference="(?:(?:'(?:[^']|'')+'|(?:\\[\\d+\\])?[A-Za-z_][A-Za-z0-9_.]*)!)?\\$?[A-Za-z]{1,3}\\$?[1-9][0-9]*";
  while($offset<strlen($s)){
   if(preg_match('/\G\s+/A',$s,$m,0,$offset)){$offset+=strlen($m[0]);continue;}
   if(!preg_match('/\G(?:'.$reference.'(?![A-Za-z0-9_(])|(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?|"(?:[^"]|"")*"|[A-Za-z_][A-Za-z0-9_.]*|<>|<=|>=|[+\-*\/^%=<>(),:&])/A',$s,$m,0,$offset))throw new InvalidArgumentException('Unsupported formula token at position '.($offset+1).'.');
   $this->tokens[]=$m[0];$offset+=strlen($m[0]);if(count($this->tokens)>512)throw new InvalidArgumentException('Formula is too complex.');
  }
 }
 private function peek(): ?string{return $this->tokens[$this->pos]??null;}
 private function take(): string{if($this->peek()===null)throw new InvalidArgumentException('Unexpected end of formula.');return $this->tokens[$this->pos++];}
 public function parse(): array{$ast=$this->expression(0);if($this->peek()!==null)throw new InvalidArgumentException('Unexpected token: '.$this->peek());return $ast;}
 public static function ref(string $token): ?array {
  if(!preg_match("/^(?:(('(?:[^']|'')+'|(?:\\[\\d+\\])?[A-Za-z_][A-Za-z0-9_.]*))!)?(\\$?[A-Za-z]{1,3}\\$?[1-9][0-9]*)$/D",$token,$m))return null;
  $sheet=$m[2]??'';if(str_starts_with($sheet,"'"))$sheet=str_replace("''","'",substr($sheet,1,-1));
  $ref=strtoupper($m[3]);try{cell_coordinates($ref);}catch(RuntimeException $e){throw new InvalidArgumentException($e->getMessage());}return ['ref',$sheet,$ref];
 }
 private function expression(int $minimum): array {
  if(++$this->depth>64)throw new InvalidArgumentException('Formula nesting exceeds 64 levels.');
  try{
   $t=$this->take();
   if(in_array($t,['+','-']))$left=['unary',$t,$this->expression(6)];
   elseif($t==='('){$left=$this->expression(0);if($this->take()!==')')throw new InvalidArgumentException('Missing closing parenthesis.');}
   elseif(is_numeric($t)){$n=(float)$t;if(!is_finite($n))throw new InvalidArgumentException('Formula contains a nonfinite number.');$left=['literal',$n];}
   elseif(str_starts_with($t,'"'))$left=['literal',str_replace('""','"',substr($t,1,-1))];
   elseif(($ref=self::ref($t))!==null)$left=$ref;
   elseif(in_array(strtoupper($t),['TRUE','FALSE'])&&$this->peek()!=='(')$left=['literal',strtoupper($t)==='TRUE'];
   else{
    $name=strtoupper($t);if(!in_array($name,['SUM','IF','ROUND','MIN','MAX','AVERAGE','ABS','COUNT','AND','OR','NOT','IFERROR']))throw new InvalidArgumentException('Unsupported function or name: '.$t);
    if($this->take()!=='(')throw new InvalidArgumentException('Expected function arguments.');$args=[];
    if($this->peek()!==')'){do{$args[]=$this->expression(0);if($this->peek()!==',')break;$this->take();}while(true);}
    if($this->take()!==')')throw new InvalidArgumentException('Missing closing parenthesis.');
    $count=count($args);if(($name==='IF'&&($count<2||$count>3))||($name==='ROUND'&&$count!==2)||(in_array($name,['ABS','NOT'])&&$count!==1)||($name==='IFERROR'&&$count!==2)||$count===0)throw new InvalidArgumentException('Wrong number of arguments for '.$name);
    $left=['call',$name,$args];
   }
   $precedence=['='=>1,'<>' =>1,'<' =>1,'>'=>1,'<='=>1,'>='=>1,'&'=>2,'+'=>3,'-'=>3,'*'=>4,'/'=>4,'^'=>5,':'=>7,'%'=>8];
   while(($op=$this->peek())!==null&&isset($precedence[$op])&&$precedence[$op]>=$minimum){
    $this->take();if($op==='%'){$left=['unary','%',$left];continue;}
    $right=$this->expression($precedence[$op]+1);
    if($op===':'&&($left[0]!=='ref'||$right[0]!=='ref'))throw new InvalidArgumentException('A range needs two cell references.');
    $left=['binary',$op,$left,$right];
   }
   return $left;
  }finally{$this->depth--;}
 }
}

function formula_col(int $col): string {$s='';while($col>0){$col--;$s=chr(65+$col%26).$s;$col=intdiv($col,26);}return $s;}
function shifted_formula(string $formula,string $anchor,string $target): string {
 [$ac,$ar]=cell_coordinates($anchor);[$tc,$tr]=cell_coordinates($target);$tokens=(new FormulaParser($formula))->tokens;
 foreach($tokens as &$token){$ref=FormulaParser::ref($token);if(!$ref)continue;$prefix=str_contains($token,'!')?substr($token,0,strrpos($token,'!')+1):'';
  preg_match('/^(\$?)([A-Z]+)(\$?)([0-9]+)$/',$ref[2],$m);[$col,$row]=cell_coordinates($ref[2]);$col+=$m[1]==='$'?0:$tc-$ac;$row+=$m[3]==='$'?0:$tr-$ar;
  if($col<1||$row<1||$col>16384||$row>1048576)throw new FormulaError('#REF!');$token=$prefix.$m[1].formula_col($col).$m[3].$row;
 }unset($token);return implode('',$tokens);
}
function expanded_formulas(array $sheet): array {
 $groups=[];$out=[];
 foreach($sheet['cells'] as $ref=>$cell)if($cell['formula']!==null&&$cell['formula']!==''){
  $out[$ref]='='.$cell['formula'];if(($cell['formulaAttributes']['t']??'')==='shared')$groups[$cell['formulaAttributes']['si']]=[$ref,$cell['formula']];
 }
 foreach($sheet['cells'] as $ref=>$cell)if($cell['formula']!==null&&$cell['formula']===''&&($cell['formulaAttributes']['t']??'')==='shared'){
  $g=$groups[$cell['formulaAttributes']['si']]??null;
  if($g)try{$out[$ref]='='.shifted_formula($g[1],$g[0],$ref);}catch(Throwable $e){$out[$ref]='=UNSUPPORTED()';}
 }
 return $out;
}

final class FormulaEngine {
 private array $sheets=[],$formulas=[],$values=[],$cache=[],$active=[],$overlays=[],$asts=[];
 public array $warnings=[],$errors=[];
 private int $steps=0;
 public function __construct(array $layout,array $values=[],array $formulas=[]){
  foreach($layout['sheets'] as $s){$this->sheets[$s['name']]=$s;$this->formulas[$s['name']]=expanded_formulas($s);$this->values[$s['name']]=[];}
  $formulas=array_map('formula_overlay_expression',$formulas);
  $first=$layout['sheets'][0]['name'];$this->values[$first]=$values;foreach($formulas as $ref=>$formula)$this->overlays[$first.'!'.$ref]=$formula;$this->formulas[$first]=array_replace($this->formulas[$first],$formulas);
 }
 public function validate(string $formula,string $sheet): array {
  if(!str_starts_with($formula,'=')||strlen($formula)<2)throw new InvalidArgumentException('A formula must begin with = and contain an expression.');
  $ast=(new FormulaParser($formula))->parse();$this->checkReferences($ast,$sheet);return $ast;
 }
 private function sheetName(string $name): string {foreach(array_keys($this->sheets) as $sheet)if(strcasecmp($name,$sheet)===0)return $sheet;return $name;}
 private function checkReferences(array $ast,string $sheet): void {
  if($ast[0]==='binary'&&$ast[1]===':'){
   $name=$ast[2][1]?:$sheet;$left=$ast[2];$right=$ast[3];$left[1]=$name;$right[1]=$right[1]?:$name;
   if(strcasecmp($left[1],$right[1])!==0)throw new InvalidArgumentException('Ranges across different sheets are not supported.');
   $this->checkReferences($left,$sheet);$this->checkReferences($right,$sheet);return;
  }
  if($ast[0]==='ref'){
   $name=$this->sheetName($ast[1]?:$sheet);if(!isset($this->sheets[$name]))throw new InvalidArgumentException('Unknown or external worksheet: '.$name);
   if(!range_contains($this->sheets[$name]['range'],str_replace('$','',$ast[2])))throw new InvalidArgumentException('Reference outside the used worksheet range: '.$ast[2]);return;
  }
  foreach(array_slice($ast,1) as $child)if(is_array($child)){if(isset($child[0])&&is_string($child[0]))$this->checkReferences($child,$sheet);else foreach($child as $node)$this->checkReferences($node,$sheet);}
 }
 public function value(string $sheet,string $ref){
  $sheet=$this->sheetName($sheet);
  if(++$this->steps>200000)throw new FormulaError('#LIMIT!');$ref=str_replace('$','',$ref);$key=$sheet.'!'.$ref;
  if(array_key_exists($key,$this->cache))return $this->cache[$key];
  if(isset($this->active[$key]))throw new FormulaError('#CIRC!');
  if(!isset($this->sheets[$sheet]))throw new FormulaError('#EXTERNAL!');
  $s=$this->sheets[$sheet];$cell=$s['cells'][$ref]??['value'=>'','type'=>''];
  if(isset($this->formulas[$sheet][$ref])){
   if(count($this->active)>256)throw new FormulaError('#LIMIT!');$this->active[$key]=true;
   try{
    $formula=$this->formulas[$sheet][$ref];$ast=$this->asts[$formula]??= (new FormulaParser($formula))->parse();$result=$this->evaluate($ast,$sheet);if($result===null)$result=0.0;
    if(is_array($result))throw new FormulaError('#VALUE!');if(is_float($result)&&!is_finite($result))throw new FormulaError('#NUM!');
    return $this->cache[$key]=$result;
   }catch(InvalidArgumentException $e){$this->warnings[$key]='Unsupported source formula; original cached value retained.';return $this->cache[$key]=$this->scalar($cell['value'],$cell['type']);
   }catch(FormulaError $e){
    if($e->getMessage()==='#EXTERNAL!'&&!isset($this->overlays[$key])){$this->warnings[$key]='External workbook unavailable; original cached value retained.';return $this->cache[$key]=$this->scalar($cell['value'],$cell['type']);}
    $this->errors[$key]=$e->getMessage();throw $e;
   }finally{unset($this->active[$key]);}
  }
  if(array_key_exists($ref,$this->values[$sheet]))return $this->cache[$key]=$this->scalar($this->values[$sheet][$ref],'');
  return $this->cache[$key]=$this->scalar($cell['value'],$cell['type']);
 }
 private function scalar(string $value,string $type){if($type==='e')throw new FormulaError($value);if($type==='b')return $value==='1';if($value===''&&in_array($type,['','n']))return null;return is_numeric($value)&&in_array($type,['','n'])?(float)$value:$value;}
 private function number($value): float {if($value===''||$value===null)return 0.0;if(is_bool($value))return $value?1.0:0.0;if(!is_numeric($value))throw new FormulaError('#VALUE!');return (float)$value;}
 private function evaluate(array $a,string $sheet){
  if(++$this->steps>200000)throw new FormulaError('#LIMIT!');
  if($a[0]==='literal')return $a[1];
  if($a[0]==='ref')return $this->value($a[1]?:$sheet,$a[2]);
  if($a[0]==='unary'){$n=$this->number($this->evaluate($a[2],$sheet));return match($a[1]){'-'=>-$n,'%'=>$n/100,default=>$n};}
  if($a[0]==='binary'){
   $op=$a[1];
   if($op===':'){
    $name=$this->sheetName($a[2][1]?:$sheet);if($this->sheetName($a[3][1]?:$name)!==$name)throw new FormulaError('#REF!');
    [$c1,$r1]=cell_coordinates($a[2][2]);[$c2,$r2]=cell_coordinates($a[3][2]);if($c2<$c1||$r2<$r1||($c2-$c1+1)*($r2-$r1+1)>20000)throw new FormulaError('#LIMIT!');
    $out=[];for($r=$r1;$r<=$r2;$r++)for($c=$c1;$c<=$c2;$c++)$out[]=$this->value($name,formula_col($c).$r);return $out;
   }
   $l=$this->evaluate($a[2],$sheet);$r=$this->evaluate($a[3],$sheet);
   if($op==='&')return (string)$l.(string)$r;
   if(in_array($op,['=','<>','<','>','<=','>=']))return match($op){'='=>$l==$r,'<>'=>$l!=$r,'<'=>$l<$r,'>'=>$l>$r,'<='=>$l<=$r,'>='=>$l>=$r};
   $l=$this->number($l);$r=$this->number($r);if($op==='/'&&$r==0)throw new FormulaError('#DIV/0!');
   return match($op){'+'=>$l+$r,'-'=>$l-$r,'*'=>$l*$r,'/'=>$l/$r,'^'=>$l**$r,default=>throw new FormulaError('#VALUE!')};
  }
  $name=$a[1];$args=$a[2];
  if($name==='IF')return $this->evaluate($args[$this->evaluate($args[0],$sheet)?1:2]??['literal',false],$sheet);
  if($name==='IFERROR')try{return $this->evaluate($args[0],$sheet);}catch(FormulaError $e){if($e->getMessage()==='#CIRC!')throw $e;return $this->evaluate($args[1],$sheet);}
  $flat=[];foreach($args as $arg){$value=$this->evaluate($arg,$sheet);if($arg[0]==='literal'&&(is_bool($value)||is_numeric($value))&&in_array($name,['SUM','COUNT','MIN','MAX','AVERAGE']))$value=(float)$value;foreach(is_array($value)?$value:[$value] as $v)$flat[]=$v;}
  $numbers=array_values(array_filter($flat,fn($v)=>is_int($v)||is_float($v)));
  return match($name){
   'SUM'=>array_sum($numbers),'COUNT'=>count($numbers),'MIN'=>$numbers?min($numbers):0,'MAX'=>$numbers?max($numbers):0,
   'AVERAGE'=>$numbers?array_sum($numbers)/count($numbers):throw new FormulaError('#DIV/0!'),
   'ROUND'=>round($this->number($flat[0]),max(-15,min(15,(int)$this->number($flat[1]))),PHP_ROUND_HALF_UP),
   'ABS'=>abs($this->number($flat[0])),'AND'=>!in_array(false,array_map(fn($v)=>(bool)$v,$flat),true),'OR'=>in_array(true,array_map(fn($v)=>(bool)$v,$flat),true),'NOT'=>!(bool)$flat[0],
   default=>throw new FormulaError('#NAME?')};
 }
 public function results(): array {
  $out=[];foreach($this->formulas as $sheet=>$formulas)foreach($formulas as $ref=>$formula){try{$value=$this->value($sheet,$ref);$out[$sheet][$ref]=['value'=>$value,'error'=>null];}catch(FormulaError $e){$out[$sheet][$ref]=['value'=>null,'error'=>$e->getMessage()];}}
  return $out;
 }
}
