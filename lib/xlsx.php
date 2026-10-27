<?php
declare(strict_types=1);
// Small OOXML ZIP adapter: retains original compressed bytes for untouched entries.
function zip_read(string $path): array {
 $data=file_get_contents($path); $end=strrpos($data,"PK\x05\x06"); if($end===false) throw new RuntimeException('Invalid XLSX ZIP.');
 $e=unpack('vdisk/vstart/vdiskCount/vcount/Vsize/Voffset/vcomment',substr($data,$end+4,18)); $pos=$e['offset']; $out=[];
 for($i=0;$i<$e['count'];$i++) {if(substr($data,$pos,4)!=="PK\x01\x02") throw new RuntimeException('Invalid ZIP directory');
 $h=unpack('vmade/vneeded/vflags/vmethod/vtime/vdate/Vcrc/Vcompressed/Vsize/vname/vextra/vcomment/vdisk/vinternal/Vexternal/Voffset',substr($data,$pos+4,42));
 $name=substr($data,$pos+46,$h['name']); $local=unpack('vname/vextra',substr($data,$h['offset']+26,4));
 $out[$name]=['header'=>$h,'compressed'=>substr($data,$h['offset']+30+$local['name']+$local['extra'],$h['compressed'])]; $pos+=46+$h['name']+$h['extra']+$h['comment']; }
 return $out;
}
function zip_text(array $zip,string $name): string {if(!isset($zip[$name])) throw new RuntimeException("Missing ZIP part: $name"); $e=$zip[$name]; return match($e['header']['method']) {0=>$e['compressed'],8=>gzinflate($e['compressed']),default=>throw new RuntimeException('Unsupported compression')};}
function zip_write(array $zip,array $changes): string {
 $body='';$directory=''; foreach($zip as $name=>$e) { $h=$e['header'];$bytes=$e['compressed'];if(array_key_exists($name,$changes)) {$raw=$changes[$name];$bytes=gzdeflate($raw);$h['method']=8;$h['crc']=crc32($raw);$h['size']=strlen($raw);}
 $offset=strlen($body);$len=strlen($bytes);$flags=$h['flags'] & ~8;
 $body.="PK\x03\x04".pack('vvvvvVVVvv',20,$flags,$h['method'],$h['time'],$h['date'],$h['crc'],$len,$h['size'],strlen($name),0).$name.$bytes;
 $directory.="PK\x01\x02".pack('vvvvvvVVVvvvvvVV',$h['made'],20,$flags,$h['method'],$h['time'],$h['date'],$h['crc'],$len,$h['size'],strlen($name),0,0,0,$h['internal'],$h['external'],$offset).$name;
 }return $body.$directory."PK\x05\x06".pack('vvvvVVv',0,0,count($zip),count($zip),strlen($directory),strlen($body),0);
}
function xml_doc(string $xml): DOMDocument {$d=new DOMDocument();$d->preserveWhiteSpace=true;if(!$d->loadXML($xml,LIBXML_NONET)) throw new RuntimeException('Invalid XML');return $d;}
function xml_path(DOMDocument $d): DOMXPath {$x=new DOMXPath($d);$x->registerNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');return $x;}
