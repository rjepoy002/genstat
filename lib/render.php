<?php
declare(strict_types=1);
function child(array $node,string $tag): array {foreach($node['children']??[] as $c)if($c['tag']===$tag)return $c['data'];return [];}
function color(array $n,array $theme,string $default='#000000'): string {
 if(isset($n['rgb']))$hex=substr($n['rgb'],-6);elseif(isset($n['theme']))$hex=$theme[(int)$n['theme']]??'000000';elseif(isset($n['indexed'])){$palette=['000000','FFFFFF','FF0000','00FF00','0000FF','FFFF00','FF00FF','00FFFF'];$i=(int)$n['indexed'];$hex=$palette[$i<8?$i:$i-8]??ltrim($default,'#');}else return $default;
 if(isset($n['tint'])){$t=(float)$n['tint'];$a=[];for($i=0;$i<6;$i+=2){$v=hexdec(substr($hex,$i,2));$a[]=max(0,min(255,(int)round($t<0?$v*(1+$t):$v*(1-$t)+255*$t)));}$hex=sprintf('%02x%02x%02x',...$a);}return '#'.$hex;
}
function cell_css(array $l,int $id): array {
 $st=$l['styles'];$s=$st['cellXfs'][$id];$font=$st['fonts'][(int)$s['fontId']];$fill=$st['fills'][(int)$s['fillId']];$border=$st['borders'][(int)$s['borderId']];$a=child($s,'alignment');$theme=$l['themeColors'];
 $css=['font-family'=>'"'.str_replace('"','',child($font,'name')['val']??'Arial').'", serif','font-size'=>(child($font,'sz')['val']??12).'pt','font-weight'=>child_exists($font,'b')?'bold':'normal','font-style'=>child_exists($font,'i')?'italic':'normal','color'=>color(child($font,'color'),$theme),'text-align'=>$a['horizontal']??'general','align-items'=>match($a['vertical']??'bottom'){'center'=>'center','top'=>'flex-start',default=>'flex-end'},'white-space'=>($a['wrapText']??'0')==='1'?'pre-wrap':'pre','text-decoration'=>child_exists($font,'u')?'underline':(child_exists($font,'strike')?'line-through':'none')];
 $pat=child($fill,'patternFill');if(($pat['patternType']??'')==='solid')$css['background-color']=color(child($pat,'fgColor'),$theme,'#ffffff');
 foreach(['left','right','top','bottom'] as $side){$b=child($border,$side);if(isset($b['style'])){$width=match($b['style']){'medium','mediumDashed','mediumDashDot','mediumDashDotDot'=>2,'thick','double'=>3,'hair'=>0.5,default=>1};$line=match($b['style']){'double'=>'double','dotted'=>'dotted','dashed','mediumDashed','dashDot','dashDotDot','mediumDashDot','mediumDashDotDot'=>'dashed',default=>'solid'};$css['border-'.$side]="$width".'px '.$line.' '.color(child($b,'color'),$theme);}}
 if(isset($a['indent']))$css['padding-left']=(2+(int)$a['indent']*10).'px';return $css;
}
function child_exists(array $node,string $tag): bool {foreach($node['children']??[] as $c)if($c['tag']===$tag)return ($c['data']['val']??'1')!=='0';return false;}
function css_text(array $css): string {$s='';foreach($css as $k=>$v)$s.=$k.':'.$v.';';return $s;}
function cell_number_format(array $c, array $l): string {
 $id=(int)$l['styles']['cellXfs'][$c['style']]['numFmtId'];
 // Custom definitions take precedence, even when they override a built-in ID.
 foreach($l['styles']['numFmts'] as $format)if((int)$format['numFmtId']===$id)return $format['formatCode'];
 return [0=>'General',1=>'0',2=>'0.00',3=>'#,##0',4=>'#,##0.00',5=>'"$"#,##0;("$"#,##0)',6=>'"$"#,##0;[Red]("$"#,##0)',7=>'"$"#,##0.00;("$"#,##0.00)',8=>'"$"#,##0.00;[Red]("$"#,##0.00)',9=>'0%',10=>'0.00%',14=>'m/d/yy',15=>'d-mmm-yy',16=>'d-mmm',17=>'mmm-yy',18=>'h:mm AM/PM',19=>'h:mm:ss AM/PM',20=>'h:mm',21=>'h:mm:ss',22=>'m/d/yy h:mm',37=>'#,##0;(#,##0)',38=>'#,##0;[Red](#,##0)',39=>'#,##0.00;(#,##0.00)',40=>'#,##0.00;[Red](#,##0.00)',45=>'mm:ss',46=>'[h]:mm:ss'][$id]??'General';
}

// Compile the fixed-point, percentage and accounting formats used by this
// workbook. The same spec is sent to JS; custom IDs have no fixed semantics.
function number_format_spec(string $code): array {
 static $cache=[];if(isset($cache[$code]))return $cache[$code];
 if(strcasecmp($code,'General')===0)return $cache[$code]=['general'=>true];
 $unquoted=preg_replace('/"[^"]*"|\\\\.|_.|\*./','',$code);
 if(!preg_match('/[0#?]/',$unquoted)&&preg_match('/[ymdhs]/i',$unquoted))return $cache[$code]=['general'=>false,'temporal'=>$code];
 $parts=[[]];$part=0;$length=strlen($code);
 for($i=0;$i<$length;$i++){
  $char=$code[$i];
  if($char===';'){$parts[++$part]=[];continue;}
  if($char==='"'){$literal='';while(++$i<$length&&$code[$i]!=='"')$literal.=$code[$i];$parts[$part][]=['literal',$literal];continue;}
  if($char==='\\'){$parts[$part][]=['literal',$code[++$i]??''];continue;}
  if($char==='_'||$char==='*'){++$i;continue;} // Excel pixel padding/fill, not numeric text.
  if($char==='['){while(++$i<$length&&$code[$i]!==']'){}continue;}
  $parts[$part][]=[str_contains('0#?.,',$char)?'pattern':'literal',$char];
 }
 $sections=[];
 foreach($parts as $tokens){
  $first=null;$last=null;$hasDigits=false;$percent=0;
  foreach($tokens as $i=>[$kind,$text]){
   if($kind==='pattern'&&str_contains('0#?',$text)){$first??=$i;$last=$i;if($text!=='?')$hasDigits=true;}
   if($kind==='literal'&&$text==='%')$percent++;
  }
  $prefix='';$suffix='';$pattern='';
  foreach($tokens as $i=>[$kind,$text]){
   if($first!==null&&$i>=$first&&$i<=$last){if($kind==='pattern')$pattern.=$text;continue;}
   if($kind==='literal'){if($first===null||$i<$first)$prefix.=$text;else $suffix.=$text;}
  }
  $decimal=str_contains($pattern,'.')?explode('.',$pattern,2)[1]:'';
  $sections[]=['literal'=>!$hasDigits,'prefix'=>trim($prefix),'suffix'=>trim($suffix),'minDecimals'=>substr_count($decimal,'0'),'maxDecimals'=>strlen($decimal),'grouping'=>str_contains(explode('.',$pattern)[0],','),'percent'=>$percent];
 }
 return $cache[$code]=['general'=>false,'sections'=>$sections];
}

function general_number_display(float $n): string {
 if($n==0)return '0';
 // Excel General shows at most 15 significant digits, rather than the full
 // OOXML serialization of the binary floating-point value.
 $rounded=(float)sprintf('%.15g',$n);$magnitude=abs($rounded);
 if($magnitude>=1e11||$magnitude<1e-9){
  [$mantissa,$exponent]=explode('e',sprintf('%.14e',$rounded));
  return rtrim(rtrim($mantissa,'0'),'.').'E'.((int)$exponent<0?'-':'+').str_pad((string)abs((int)$exponent),2,'0',STR_PAD_LEFT);
 }
 $decimals=max(0,14-(int)floor(log10($magnitude)));
 $text=number_format($rounded,$decimals,'.','');return $decimals>0?rtrim(rtrim($text,'0'),'.'):$text;
}

function format_numeric_display(string $raw, array $spec): string {
 if($raw===''||!is_numeric($raw)||!is_finite((float)$raw))return $raw;
 $n=(float)$raw;if($spec['general'])return general_number_display($n);
 if(isset($spec['temporal']))return temporal_number_display($n,$spec['temporal']);
 $sections=$spec['sections'];$index=$n<0&&count($sections)>1?1:($n==0&&count($sections)>2?2:0);$section=$sections[$index];
 if($section['literal'])return $section['prefix'].$section['suffix'];
 $value=abs($n)*100**$section['percent'];
 $text=number_format($value,$section['maxDecimals'],'.',$section['grouping']?',':'');
 if($section['maxDecimals']>$section['minDecimals']){
  [$integer,$fraction]=explode('.',$text);$fraction=rtrim($fraction,'0');$fraction=str_pad($fraction,$section['minDecimals'],'0');$text=$integer.($fraction!==''?'.'.$fraction:'');
 }
 return ($n<0&&$index===0?'-':'').$section['prefix'].$text.$section['suffix'];
}

function display_value(array $c,array $l): string {
 $v=$c['value'];if($v===''||!is_numeric($v)||!in_array($c['type'],['','n']))return $v;
 return format_numeric_display($v,number_format_spec(cell_number_format($c,$l)));
}

// Editing text is a presentation value, never a replacement for stored OOXML
// data. Percent inputs remain unscaled numeric ratios; their previews show %.
function numeric_editing_text(string $raw, array $spec): string {
 if($raw===''||!is_numeric($raw)||!is_finite((float)$raw))return $raw;
 if($spec['general']||isset($spec['temporal']))return general_number_display((float)$raw);
 $section=$spec['sections'][0];
 if($section['literal']||$section['percent']>0)return general_number_display((float)$raw);
 $section['prefix']='';$section['suffix']='';$section['grouping']=false;
 return format_numeric_display($raw,['general'=>false,'sections'=>[$section]]);
}

function temporal_number_display(float $serial,string $code): string {
 if($serial<0||$serial>2958465)return '########';
 $days=(int)floor($serial);$seconds=(int)round(($serial-$days)*86400);$days+=intdiv($seconds,86400);$seconds%=86400;
 $date=(new DateTimeImmutable('1899-12-31',new DateTimeZone('UTC')))->modify('+'.($days>=60?$days-1:$days).' days');
 $year=(int)$date->format('Y');$month=(int)$date->format('n');$day=$days===60?29:(int)$date->format('j');$hour=intdiv($seconds,3600);$minute=intdiv($seconds%3600,60);$second=$seconds%60;$am=str_contains(strtoupper($code),'AM/PM');
 return preg_replace_callback('/"[^"]*"|\\\\.|AM\/PM|\[h\]|yyyy|mmmm|dddd|mmm|ddd|yy|mm|dd|hh|ss|[ymdhs]/i',function($match)use($code,$date,$year,$month,$day,$hour,$minute,$second,$serial,$am){
  $token=$match[0][0];$offset=$match[0][1];$lower=strtolower($token);
  if($token[0]==='"')return substr($token,1,-1);if($token[0]==='\\')return substr($token,1);
  $minutes=preg_match('/h|:/i',substr($code,0,$offset))||preg_match('/^m{1,2}:s/i',substr($code,$offset));
  return match($lower){'yyyy'=>(string)$year,'yy'=>sprintf('%02d',$year%100),'mmmm'=>$date->format('F'),'mmm'=>$date->format('M'),'dddd'=>$date->format('l'),'ddd'=>$date->format('D'),'m'=>(string)($minutes?$minute:$month),'mm'=>sprintf('%02d',$minutes?$minute:$month),'d'=>(string)$day,'dd'=>sprintf('%02d',$day),'h'=>(string)($am?($hour%12?:12):$hour),'hh'=>sprintf('%02d',$am?($hour%12?:12):$hour),'s'=>(string)$second,'ss'=>sprintf('%02d',$second),'am/pm'=>$hour<12?'AM':'PM','[h]'=>(string)(int)floor($serial*24),default=>$token};
 },$code,-1,$count,PREG_OFFSET_CAPTURE);
}
