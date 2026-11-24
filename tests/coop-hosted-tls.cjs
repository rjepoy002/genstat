'use strict';

const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),net=require('node:net');
const {spawn,execFileSync}=require('node:child_process');
const {once}=require('node:events');
const xampp=process.env.GENSTAT_TEST_XAMPP||'C:/xampp';
const root=path.resolve(__dirname,'..');
const php=process.env.PHP_BINARY||path.join(xampp,'php/php.exe');
const mysql=tool=>path.join(xampp,'mysql/bin',tool+'.exe');
const fixture=fs.mkdtempSync(path.join(os.tmpdir(),'genstat-hosted-tls-'));
const run=(file,args,options={})=>execFileSync(file,args,{cwd:fixture,windowsHide:true,stdio:['pipe','pipe','pipe'],...options});

(async()=>{
 let server;
 try{
  const listener=net.createServer();
  listener.listen(0,'127.0.0.1');
  await once(listener,'listening');
  const port=listener.address().port;
  listener.close();
  await once(listener,'close');
  const certificate=(...args)=>run(path.join(xampp,'apache/bin/openssl.exe'),args,{
   env:{...process.env,OPENSSL_CONF:path.join(xampp,'apache/conf/openssl.cnf')}
  });
  fs.writeFileSync(path.join(fixture,'extensions.cnf'),'subjectAltName=DNS:localhost\n');
  certificate('req','-x509','-newkey','rsa:2048','-nodes','-keyout','ca.key','-out','ca.pem','-subj','/CN=GENSTAT-Test-CA','-days','1');
  certificate('req','-newkey','rsa:2048','-nodes','-keyout','server.key','-out','server.csr','-subj','/CN=localhost');
  certificate('x509','-req','-in','server.csr','-CA','ca.pem','-CAkey','ca.key','-CAcreateserial','-out','server.pem','-days','1','-extfile','extensions.cnf');
  fs.writeFileSync(path.join(fixture,'extensions.cnf'),'subjectAltName=IP:127.0.0.1\n');
  certificate('x509','-req','-in','server.csr','-CA','ca.pem','-CAkey','ca.key','-CAcreateserial','-out','ip.pem','-days','1','-extfile','extensions.cnf');
  const data=path.join(fixture,'data');
  const ca=path.join(fixture,'ca.pem');
  run(mysql('mysql_install_db'),['--datadir='+data,'--silent']);
  const client=['--no-defaults','-h','127.0.0.1','-P',String(port),'-u','root'];
  async function start(options){
   server=spawn(mysql('mysqld'),['--no-defaults','--standalone','--bind-address=127.0.0.1','--port='+port,
    '--datadir='+data,'--log-error='+path.join(fixture,'mysql.log'),...options],{cwd:fixture,windowsHide:true,stdio:'ignore'});
   for(let attempt=0;attempt<30;attempt++){
    try{run(mysql('mysqladmin'),[...client,'ping'],{timeout:1000});return;}catch{}
    await new Promise(resolve=>setTimeout(resolve,150));
   }
   throw Error('TLS fixture failed to start. '+fs.readFileSync(path.join(fixture,'mysql.log'),'utf8'));
  }
  async function stop(){
   const exited=once(server,'exit');
   run(mysql('mysqladmin'),[...client,'shutdown'],{timeout:5000});
   await exited;
   server=null;
  }
  const verify=mode=>run(php,[],{
   input:`<?php
require getenv('GENSTAT_TEST_PROJECT').'/lib/coop-phase1.php';
$connection=['port'=>(int)getenv('GENSTAT_TEST_TLS_PORT'),'database'=>'coop_tls_fixture','username'=>'fixture_reader','password'=>'fixture_only'];
function expectHostedError(array $connection,string $host,string $expected): void {
 try{coop_open_pdo($connection,$host,true,false);}
 catch(DomainException $error){
  if(!str_contains($error->getMessage(),$expected))throw $error;
  echo $error->getMessage(),PHP_EOL;
  return;
 }
 throw new RuntimeException('Expected hosted connection failure');
}
$mode=getenv('GENSTAT_TEST_TLS_MODE');
if($mode==='refused'){
 expectHostedError($connection,'127.0.0.1','connection refused');
 exit;
}
if($mode==='plaintext'){
 expectHostedError($connection,'localhost','before verified TLS');
 exit;
}
if($mode==='numeric'){
 $pdo=coop_open_pdo($connection,'127.0.0.1',true,false);
 if($pdo->query('SELECT id FROM fixture')->fetchColumn()!=='7')throw new RuntimeException('Numeric TLS fixture query failed');
 echo 'Verified TLS against the numeric destination IP.',PHP_EOL;
 exit;
}
$ca=getenv('GENSTAT_COOP_HOSTED_CA_FILE');
putenv('GENSTAT_COOP_HOSTED_CA_FILE');
expectHostedError($connection,'localhost','TLS verification failed');
putenv('GENSTAT_COOP_HOSTED_CA_FILE='.$ca);
expectHostedError($connection,'127.0.0.1','TLS verification failed');
$pdo=coop_open_pdo($connection,'localhost',true,false);
if($pdo->query('SELECT id FROM fixture')->fetchColumn()!=='7')throw new RuntimeException('TLS fixture query failed');
expectHostedError(array_replace($connection,['password'=>'wrong']),'localhost','authentication failed');
$approved=['paleco.net:3306'=>['8.8.8.8']];
if(coop_approved_hosted_addresses('paleco.net',3306,$approved)!==['8.8.8.8'])
 throw new RuntimeException('Approved hostname was rejected');
foreach(['127.0.0.1','10.0.0.1','100.64.0.1','169.254.169.254','224.0.0.1','::ffff:8.8.8.8','ff02::1'] as $ip)
 if(coop_public_address($ip))throw new RuntimeException('An internal or reserved address was accepted: '.$ip);
echo 'Verified MySQL TLS and a SELECT-only query with a trusted CA and matching hostname.',PHP_EOL;
`,
   env:{...process.env,GENSTAT_TEST_PROJECT:root,GENSTAT_TEST_TLS_PORT:String(port),
    GENSTAT_TEST_TLS_MODE:mode,GENSTAT_COOP_HOSTED_CA_FILE:ca}
  }).toString();
  await start(['--ssl','--ssl-ca='+ca,'--ssl-cert='+path.join(fixture,'server.pem'),'--ssl-key='+path.join(fixture,'server.key')]);
  run(mysql('mysql'),[...client,'-e',"CREATE DATABASE coop_tls_fixture; CREATE TABLE coop_tls_fixture.fixture (id INT); INSERT INTO coop_tls_fixture.fixture VALUES (7); CREATE USER 'fixture_reader'@'%' IDENTIFIED BY 'fixture_only'; GRANT SELECT ON coop_tls_fixture.* TO 'fixture_reader'@'%';"]);
  assert.match(verify('tls'),/Verified MySQL TLS/);
  await stop();
  assert.match(verify('refused'),/connection refused/);
  await start(['--ssl','--ssl-ca='+ca,'--ssl-cert='+path.join(fixture,'ip.pem'),'--ssl-key='+path.join(fixture,'server.key')]);
  assert.match(verify('numeric'),/Verified TLS against the numeric destination IP/);
  await stop();
  await start(['--skip-ssl']);
  assert.match(verify('plaintext'),/before verified TLS/);
  await stop();
  console.log('Verified trusted TLS, certificate hostname and CA rejection, plaintext rejection, refused connections, authentication errors, and hosted address policy.');
 }finally{
  if(server?.exitCode===null){
   const exited=once(server,'exit');
   server.kill();
   await exited;
  }
  const resolved=fs.realpathSync(fixture);
  if(path.dirname(resolved)===fs.realpathSync(os.tmpdir())&&path.basename(resolved).startsWith('genstat-hosted-tls-'))
   fs.rmSync(resolved,{recursive:true,force:true});
 }
})().catch(error=>{
 process.stderr.write(error.stderr||'');
 console.error(error.message);
 process.exitCode=1;
});
