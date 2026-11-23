'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net'),tls=require('node:tls');
const {execFile,execFileSync}=require('node:child_process');
const {promisify}=require('node:util');
const run=promisify(execFile);
const openssl='C:\\xampp\\apache\\bin\\openssl.exe';
const php='C:\\xampp\\php\\php.exe';
const root=fs.mkdtempSync(path.join(os.tmpdir(),'genstat-coop-tls-'));
function certificate(name,ip='127.0.0.1'){
 const key=path.join(root,name+'.key'),cert=path.join(root,name+'.crt');
 execFileSync(openssl,['req','-x509','-newkey','rsa:2048','-nodes','-days','1','-subj','/CN='+name,
  '-addext','subjectAltName=IP:'+ip,'-keyout',key,'-out',cert],
 {stdio:'pipe',env:{...process.env,OPENSSL_CONF:'C:\\xampp\\apache\\conf\\openssl.cnf'}});
 return {key,cert};
}
async function probe(identity,ca){
 const secureContext=tls.createSecureContext({key:fs.readFileSync(identity.key),cert:fs.readFileSync(identity.cert)});
 let tlsRequested=false,secured=false,authAttempt=false;const clients=new Set();
 const greeting=Buffer.concat([Buffer.from([10]),Buffer.from('8.0.30\0'),Buffer.from([1,0,0,0]),
  Buffer.from('12345678\0'),Buffer.from([0x00,0x8a,45,2,0,0x08,0x00,21]),Buffer.alloc(10),
  Buffer.from('abcdefghijkl\0mysql_native_password\0')]);
 const peer=net.createServer(socket=>{
  clients.add(socket);socket.on('error',()=>{});socket.on('close',()=>clients.delete(socket));
  socket.write(Buffer.concat([Buffer.from([greeting.length,0,0,0]),greeting]));
  let buffer=Buffer.alloc(0);
  function request(chunk){
   buffer=Buffer.concat([buffer,chunk]);
   if(buffer.length<36)return;
   socket.off('data',request);
   tlsRequested=buffer[0]===32&&(buffer.readUInt32LE(4)&0x800)!==0;
   if(!tlsRequested){socket.destroy();return;}
   if(buffer.length>36)socket.unshift(buffer.subarray(36));
   const transport=new tls.TLSSocket(socket,{isServer:true,secureContext});
   transport.on('error',()=>transport.destroy());
   transport.on('secure',()=>{secured=true;});
   transport.on('data',()=>{authAttempt=true;transport.destroy();});
  }
  socket.on('data',request);socket.setTimeout(7000,()=>socket.destroy());
 });
 await new Promise(resolve=>peer.listen(0,'127.0.0.1',resolve));
 const {port}=peer.address();
 try{
  const program=`require 'lib/coop-cloud.php';
   $s=coop_connection_input(['host'=>'127.0.0.1','port'=>(int)getenv('FIXTURE_COOP_PORT'),'database'=>'fixture','username'=>'fixture','ca'=>getenv('GENSTAT_COOP_CA_FILES'),'password'=>'fixture-password'],[]);
   try { coop_cloud($s); exit(2); } catch (DomainException $e) { echo $e->getMessage(); }`;
  const {stdout}=await run(php,['-r',program],{
   timeout:12000,windowsHide:true,env:{...process.env,FIXTURE_COOP_PORT:String(port),GENSTAT_COOP_CLOUD_ENABLED:'1',
    GENSTAT_COOP_ENDPOINTS:'127.0.0.1:'+port,GENSTAT_COOP_CA_FILES:ca,GENSTAT_COOP_SECRET_KEY:Buffer.alloc(32,42).toString('base64')}
  });
  assert.match(stdout,/External connection or verified TLS failed/);
  assert(tlsRequested,'MySQL client requested TLS');
  return {secured,authAttempt};
 }finally{
  for(const client of clients)client.destroy();
  await new Promise(resolve=>peer.close(resolve));
 }
}
(async()=>{
 try{
  const identity=certificate('coop-fixture-server'),untrusted=certificate('coop-fixture-other-ca');
  assert.equal((await probe(identity,untrusted.cert)).authAttempt,false,'Untrusted CA rejected before authentication');
  const wrongName=certificate('coop-fixture-wrong-host','127.0.0.2');
  assert.equal((await probe(wrongName,wrongName.cert)).authAttempt,false,'A trusted certificate for another host is rejected');
  assert.equal((await probe(identity,identity.cert)).authAttempt,true,'Trusted control reached the TLS secure state');
  console.log('PASS: native MySQLi refused an untrusted certificate and a hostname mismatch before authentication; trusted TLS control succeeded.');
 }finally{
  const resolved=path.resolve(root);
  if(path.dirname(resolved)!==path.resolve(os.tmpdir())||!path.basename(resolved).startsWith('genstat-coop-tls-'))throw Error('Unsafe cleanup');
  fs.rmSync(resolved,{recursive:true,force:true});
 }
})().catch(error=>{console.error(error);process.exitCode=1;});
