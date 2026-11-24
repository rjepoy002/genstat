'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),https=require('node:https');
const {execFile,execFileSync}=require('node:child_process');
const {promisify}=require('node:util');
const {once}=require('node:events');
const root=path.resolve(__dirname,'..'),xampp=process.env.GENSTAT_TEST_XAMPP||'C:/xampp';
const php=process.env.PHP_BINARY||path.join(xampp,'php/php.exe');
const fixture=fs.mkdtempSync(path.join(os.tmpdir(),'genstat-https-transport-'));

(async()=>{
 let server;
 try{
  execFileSync(path.join(xampp,'apache/bin/openssl.exe'),[
   'req','-x509','-newkey','rsa:2048','-nodes','-keyout','key.pem','-out','ca.pem',
   '-subj','/CN=fixture.example','-addext','subjectAltName=DNS:fixture.example','-days','1'
  ],{cwd:fixture,windowsHide:true,stdio:'pipe',env:{...process.env,OPENSSL_CONF:path.join(xampp,'apache/conf/openssl.cnf')}});
  const ca=path.join(fixture,'ca.pem'),received=[];
  server=https.createServer({key:fs.readFileSync(path.join(fixture,'key.pem')),cert:fs.readFileSync(ca)},async(req,res)=>{
   let body='';
   for await(const chunk of req)body+=chunk;
   received.push([req.url,req.headers['x-api-key'],req.method,body]);
   res.setHeader('Content-Type','application/json');
   if(req.url==='/redirect'){res.writeHead(302,{Location:'/unexpected'});res.end('{}');return;}
   if(req.url==='/large'){res.end('x'.repeat(540000));return;}
   res.end('{"success":true,"api_version":"1.1","database_connected":true}');
  });
  server.listen(0,'127.0.0.1');await once(server,'listening');
  const script=path.join(fixture,'transport.php');
  fs.writeFileSync(script,`<?php
require getenv('GENSTAT_TEST_ROOT').'/lib/coop-phase1.php';
$port=(int)getenv('GENSTAT_TEST_PORT');
$host=$argv[1];
try{
 $body=$argv[2]==='/post'?['action'=>'account_lookup','AcctCode'=>'001']:null;
 $result=coop_https_exchange('https://'.$host.':'.$port.$argv[2],$host,$port,['127.0.0.1'],'fixture-key',$body);
 echo json_encode($result,JSON_THROW_ON_ERROR);
}catch(DomainException $error){echo $error->getMessage();}
`);
  const run=async(host,route,trust=true)=>{
   const {stdout}=await promisify(execFile)(php,[...(trust?['-d','curl.cainfo="'+ca.replaceAll('\\','/')+'"']:[]),script,host,route],{
    windowsHide:true,timeout:12000,env:{...process.env,GENSTAT_TEST_ROOT:root,GENSTAT_TEST_PORT:String(server.address().port)}
   });
   return stdout;
  };
  assert.equal(JSON.parse(await run('fixture.example','/')).database_connected,true);
  assert.equal(JSON.parse(await run('fixture.example','/post')).success,true);
  assert.match(await run('fixture.example','/redirect'),/redirected/);
  assert.match(await run('fixture.example','/large'),/too large/);
  assert.match(await run('wrong.example','/'),/certificate verification failed/);
  assert.match(await run('fixture.example','/',false),/certificate verification failed/);
  assert.deepEqual(received,[['/','fixture-key','GET',''],['/post','fixture-key','POST','{"action":"account_lookup","AcctCode":"001"}'],
   ['/redirect','fixture-key','GET',''],['/large','fixture-key','GET','']]);
  console.log('Verified local HTTPS certificate and hostname checks, pinned connections, request headers, blocked redirects, and response size limits.');
 }finally{
  if(server?.listening){server.close();await once(server,'close');}
  const resolved=fs.realpathSync(fixture);
  if(path.dirname(resolved)===fs.realpathSync(os.tmpdir())&&path.basename(resolved).startsWith('genstat-https-transport-'))
   fs.rmSync(resolved,{recursive:true,force:true});
 }
})().catch(error=>{console.error(error);process.exitCode=1;});
