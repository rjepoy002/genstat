'use strict';
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),net=require('node:net'),crypto=require('node:crypto');
const {spawn,execFileSync}=require('node:child_process');
const {once}=require('node:events');
const http=require('node:http'),https=require('node:https');
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const php='C:\\xampp\\php\\php.exe';
const root=fs.mkdtempSync(path.join(os.tmpdir(),'genstat-apply-save-'));
const fixture=action=>execFileSync(php,['tests/query-apply-fixture.php',action,root],{encoding:'utf8'});
const workbook=path.resolve('excel-files/reference/GENSTAT_BASE.xlsx');
const hash=crypto.createHash('sha256').update(fs.readFileSync(workbook)).digest('hex');
(async()=>{
 let server,devServer,browser,tlsProxy,devPort,serverLog='';
 try{
  for(const dir of ['lib','tools'])fs.cpSync(dir,path.join(root,dir),{recursive:true});
  for(const dir of ['assets','partials','excel-files','node_modules'])fs.symlinkSync(path.resolve(dir),path.join(root,dir),'junction');
  for(const file of ['base-report.php','query-settings-api.php','coop-consumption-api.php'])fs.copyFileSync(file,path.join(root,file));
  fs.mkdirSync(path.join(root,'storage','sessions'),{recursive:true});
  fixture('setup');fixture('coop-setup');
  fs.copyFileSync('config/inputs.php',path.join(root,'config','inputs.php'));
  const cert=path.join(root,'fixture.crt'),key=path.join(root,'fixture.key');
  execFileSync('C:/xampp/apache/bin/openssl.exe',['req','-x509','-newkey','rsa:2048','-nodes','-days','1','-subj','/CN=localhost',
   '-addext','subjectAltName=IP:127.0.0.1','-keyout',key,'-out',cert],
   {stdio:'pipe',env:{...process.env,OPENSSL_CONF:'C:/xampp/apache/conf/openssl.cnf'}});
  const secretPath=root+'.external.json';
  const cloudFile=path.join(root,'lib','coop-cloud.php'),cloud=fs.readFileSync(cloudFile,'utf8');
  fs.writeFileSync(cloudFile,cloud.replace('function coop_cloud(array $settings): mysqli {','function coop_native_cloud(array $settings): mysqli {')
   + "\nfunction coop_cloud(array $settings): mysqli {if(coop_local_source($settings)&&isset($settings['secret']))return coop_native_cloud($settings);if(isset($settings['secret'])&&coop_connection_password($settings)==='fixture-invalid')throw new DomainException('Database authentication failed.');return qs_db('writer');}\n");
  fs.writeFileSync(cloudFile,fs.readFileSync(cloudFile,'utf8')
   .replace('function coop_bills(mysqli $db,array $identities,string $period,?float $deadline=null): array {',
    "function coop_bills(mysqli $db,array $identities,string $period,?float $deadline=null): array {\n if(is_file(__DIR__.'/../fail-cloud'))throw new RuntimeException('Isolated billing fetch failure');"));
  fs.writeFileSync(path.join(root,'router.php'),`<?php
   if(!isset($_GET['fixture_deny'])){
    $actor=$_SERVER['HTTP_X_TEST_ACTOR']??'';
    if(in_array($actor,['operator','admin'],true))$_SERVER['REMOTE_USER']='fixture-'.$actor;
   }
   if(($_SERVER['HTTP_X_FIXTURE_TLS']??'')==='local-only')$_SERVER['HTTPS']='on';
   return false;`);
  const files=path.join(root,'lib','files.php');
  fs.writeFileSync(files,fs.readFileSync(files,'utf8').replace('    $tmp = tempnam',
   "    if(str_ends_with($path,'.external.json')&&is_file(__DIR__.'/../fail-secret-write'))throw new RuntimeException('Fixture persistence failure');\n    $tmp = tempnam"));
  const probe=net.createServer();await new Promise(resolve=>probe.listen(0,'127.0.0.1',resolve));
  const port=probe.address().port;await new Promise(resolve=>probe.close(resolve));
  const base='http://127.0.0.1:'+port;
  const fixtureEnv={...process.env,GENSTAT_COOP_DEV_MODE:'0',GENSTAT_COOP_OPERATORS:'fixture-operator',
    GENSTAT_COOP_ADMINS:'fixture-admin',GENSTAT_COOP_CLOUD_ENABLED:'1',GENSTAT_COOP_SCHEMA_REVIEWED:'1',
    GENSTAT_COOP_OWNERSHIP:'1',GENSTAT_COOP_REVIEWED_TEMPLATE:hash,GENSTAT_COOP_SECRET_FILE:secretPath,
    GENSTAT_COOP_SECRET_KEY:crypto.randomBytes(32).toString('base64'),GENSTAT_COOP_CA_FILES:cert,
    GENSTAT_COOP_ENDPOINTS:'fixture.example:3306,other.example:3306'};
  server=spawn(php,['-d','session.save_path=storage/sessions','-d','display_errors=0','-S','127.0.0.1:'+port,'-t',root,path.join(root,'router.php')],{
   cwd:root,windowsHide:true,stdio:['ignore','ignore','pipe'],env:fixtureEnv
  });
  server.stderr.on('data',data=>{serverLog+=data.toString();});
  for(let index=0;index<50;index++){
   try{if((await fetch(base+'/base-report.php?month=202607')).ok)break;}catch{}
   await new Promise(resolve=>setTimeout(resolve,100));
  }
  const devTlsMarker=crypto.randomBytes(24).toString('hex');
  tlsProxy=https.createServer({cert:fs.readFileSync(cert),key:fs.readFileSync(key)},(request,response)=>{
   const useDev=request.headers['x-fixture-dev']==='1';
   const upstream=http.request({host:'127.0.0.1',port:useDev?devPort:port,path:request.url,method:request.method,headers:{...request.headers,'x-fixture-tls':useDev?devTlsMarker:'local-only'}},reply=>{
    response.writeHead(reply.statusCode,reply.headers);reply.pipe(response);
   });
   upstream.on('error',()=>response.destroy());request.pipe(upstream);
  });
  await new Promise(resolve=>tlsProxy.listen(0,'127.0.0.1',resolve));
  const httpsBase='https://127.0.0.1:'+tlsProxy.address().port;
  const pin=crypto.createHash('sha256').update(new crypto.X509Certificate(fs.readFileSync(cert)).publicKey.export({format:'der',type:'spki'})).digest('base64');
  browser=await chromium.launch({args:['--ignore-certificate-errors-spki-list='+pin],executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
  const page=await browser.newPage({viewport:{width:1280,height:850},extraHTTPHeaders:{'X-Test-Actor':'operator'}});
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.goto(base+'/base-report.php?month=202607');
  const call=(action,extra={},authorized=true)=>page.evaluate(async({action,extra,authorized})=>{
   const report=window.genstatReport;
   const response=await fetch('coop-consumption-api.php'+(authorized?'':'?fixture_deny=1'),{method:'POST',headers:{'Content-Type':'application/json','X-Test-Actor':authorized?'operator':'unknown'},
    body:JSON.stringify({...report.snapshot(),...report.previewContext(),action,...extra})});
   const text=await response.text();
   try{return {status:response.status,body:JSON.parse(text)};}catch{throw Error(text);}
  },{action,extra,authorized});
  const enroll=(acctCode,area='main')=>call('enroll',{acctCode,area,effective:'202607'});
  for(const action of ['connection-save','enroll','apply'])assert.equal((await call(action,{},false)).status,403);
  assert.equal((await call('connection-test')).status,403);
  assert.equal((await call('local-preview',{},false)).status,403);
  assert.equal((await call('view')).body.enrollmentReady,false);
  assert.match((await enroll('001')).body.error,/saved COOP connection/);
  execFileSync(php,['-r',`require 'lib/coop-cloud.php';
   $source=coop_connection_input(['host'=>'fixture.example','port'=>3306,'database'=>'fixture','username'=>'reader','ca'=>getenv('GENSTAT_COOP_CA_FILES'),'password'=>'fixture-password'],[]);
   file_put_contents(coop_secret_path(),json_encode($source));`],{cwd:root,env:fixtureEnv});
  for(const [code,area] of [['001','main'],['002','main'],['003','aborlan']])assert.equal((await enroll(code,area)).status,200);
  assert.match((await enroll('101')).body.error,/overlapping/);
  assert.match((await enroll('006')).body.error,/Ambiguous/);
  assert.match((await enroll('999')).body.error,/Not Found/);
  const original=await page.evaluate(()=>window.genstatReport.snapshot());
  const before=await call('refresh');
  assert.equal(before.body.total,'0.300000000000');
  assert.equal(before.body.accounts[2].status,'Zero KWH');
  assert.deepEqual(await page.evaluate(()=>window.genstatReport.snapshot()),original);
  const coopCell=page.locator('td[data-ref="B26"]');
  assert.equal(await coopCell.getAttribute('data-sql-applied'),null);
  const first=(await call('view')).body.accounts[0];
  assert.equal((await call('reassign',{id:first.id,area:'narra',effective:'202608'})).status,200);
  assert.match((await call('apply',{run:before.body.id,confirmed:true})).body.error,/stale/);
  assert.equal((await enroll('004','narra')).status,200);
  const missing=await call('refresh');
  assert.equal(missing.body.complete,false);
  assert(missing.body.accounts.some(row=>row.status==='No Bill History'));
  assert.equal((await call('apply',{run:missing.body.id,confirmed:true})).status,400);
  const missingAccount=(await call('view')).body.accounts.find(row=>row.acct_code==='004');
  assert.equal((await call('deactivate',{id:missingAccount.id,effective:'202608'})).status,200);
  await page.locator('#report-month').selectOption('202608');
  await page.waitForURL('**/*month=202608');
  const august=await call('refresh');
  assert.equal(august.body.areas.narra.kwh,'12.000000000000');
  assert.equal(august.body.areas.main.kwh,'15.000000000000');
  await page.locator('#report-month').selectOption('202607');
  await page.waitForURL('**/*month=202607');
  // July remains historical. Remove the deliberately missing fixture data
  // condition by adding a synthetic bill for its effective month.
  fixture('coop-bill');
  const ready=await call('refresh');
  assert.equal(ready.body.total,'0.300000000000');
  fixture('coop-conflict');
  assert.match((await call('apply',{run:ready.body.id,confirmed:true})).body.error,/active SQL configuration/);
  const sqlCall=(action,extra={})=>page.evaluate(async({action,extra})=>{
   const report=window.genstatReport;
   const response=await fetch('query-settings-api.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({...report.snapshot(),...report.previewContext(),action,...extra})});
   return response.json();
  },{action,extra});
  const configured=await sqlCall('history',{rowKey:'genstat-base/Jul26/A26'});
  const config=configured.history[0].config;
  config.bindings=Object.fromEntries(Object.entries(config.bindings).map(([ref,binding])=>[ref.replace('11','26'),binding]));
  await sqlCall('inspect-result',{config});
  const tested=await sqlCall('test',{config});
  assert(tested.validationPassed,JSON.stringify(tested));
  assert.match((await sqlCall('apply',{config,operationId:tested.testToken})).error,/separate COOP module/);
  fixture('coop-clear-conflict');
  const failure=path.join(root,'fail-cloud');
  fs.writeFileSync(failure,'');
  const failed=await call('refresh');
  fs.unlinkSync(failure);
  assert.equal(failed.body.complete,false);
  assert.equal((await call('apply',{run:failed.body.id,confirmed:true})).status,400);
  assert.deepEqual(await page.evaluate(()=>window.genstatReport.snapshot()),original);
  await coopCell.locator('input').fill('0.3');
  await coopCell.locator('input').press('Tab');
  await page.evaluate(()=>window.genstatReport.ready());
  assert.equal(await coopCell.getAttribute('data-source-type'),'manual');
  await page.locator('#sql-settings-open').click();
  await page.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));
  await page.locator('#coop-open').click();
  const dialog=page.locator('#coop-dialog');
  await dialog.getByText('Reporting Period: July 2026').waitFor();
  for(const [width,colorScheme] of [[1280,'light'],[390,'dark']]){
   await page.setViewportSize({width,height:800});await page.emulateMedia({colorScheme});
   await page.waitForFunction(scheme=>document.documentElement.dataset.theme===scheme,colorScheme);
   const box=await dialog.boundingBox();assert(box.x>=0&&box.x+box.width<=width);
  }
  const uiResponse=page.waitForResponse(response=>response.url().endsWith('coop-consumption-api.php')&&response.request().postDataJSON().action==='refresh');
  await dialog.getByRole('button',{name:'Refresh Consumption'}).click();
  await dialog.getByText('Preview ready for review.').waitFor();
  const uiRun=await (await uiResponse).json();
  assert.equal(await coopCell.getAttribute('data-sql-applied'),null);
  await dialog.getByRole('button',{name:'Apply to Report'}).click();
  const acceptedResponse=page.waitForResponse(response=>response.url().endsWith('coop-consumption-api.php')&&response.request().postDataJSON().action==='apply');
  await page.locator('#report-confirmation').getByRole('button',{name:'Continue'}).click();
  const accepted=await acceptedResponse;
  assert.equal(accepted.status(),200,JSON.stringify((await accepted.json()).error));
  await dialog.getByText('COOP values applied. Use Save Draft to keep them.').waitFor();
  assert.equal((await call('apply',{run:uiRun.id,confirmed:true})).status,200);
  await dialog.getByRole('button',{name:'Close COOP Consumption'}).click();
  const working=await page.evaluate(()=>window.genstatReport.snapshot());
  assert.equal(working.values.B26,'0.300000000000');
  assert.equal(working.values.C26,'0.000000000000');
  assert.deepEqual(working.formulas,original.formulas);
  assert.equal(await coopCell.getAttribute('data-sql-applied'),'true');
  assert.equal(await coopCell.getAttribute('data-source-module'),'coop-consumption');
  assert.match(await coopCell.getAttribute('title'),/SQL Database\nSource: COOP Consumption/);
  assert.match(await coopCell.getAttribute('title'),/Applied: .*\nApplication \/ run: /);
  assert.equal(await coopCell.locator('.value').evaluate(el=>getComputedStyle(el,'::before').content),'"SQL "');
  for(const ref of ['Q26','R26','S26'])assert.equal(await page.locator('td[data-ref="'+ref+'"]').getAttribute('data-sql-applied'),null);
  const unsavedCheck=await browser.newPage();
  await unsavedCheck.goto(base+'/base-report.php?month=202607');
  assert.equal(await unsavedCheck.locator('td[data-ref="B26"]').getAttribute('data-sql-applied'),null);
  await unsavedCheck.close();
  const downloadPromise=page.waitForEvent('download');
  await page.locator('#report-export').click();
  await (await downloadPromise).saveAs(path.join(root,'coop-export.xlsx'));
  process.stdout.write(fixture('coop-export'));
  await page.locator('#save').click();await page.getByText('Draft saved',{exact:true}).waitFor();
  await page.reload();
  assert.equal(await page.locator('input[data-ref="B26"]').inputValue(),'0.300000000000');
  assert.equal(await coopCell.getAttribute('data-sql-applied'),'true');
  await page.locator('#restore-sql').click();
  await page.locator('#report-confirmation').getByRole('button',{name:'Restore & Recalculate'}).click();
  await page.getByText('Report formulas restored and saved configurations reapplied.').waitFor();
  assert.equal(await page.locator('input[data-ref="B26"]').inputValue(),'0.300000000000');
  assert.equal(await coopCell.getAttribute('data-sql-applied'),'true');
  await page.locator('input[data-ref="B26"]').fill('1.25');
  await page.locator('input[data-ref="B26"]').press('Tab');
  assert.equal(await coopCell.getAttribute('data-sql-applied'),null);
  await page.locator('#save').click();await page.getByText('Draft saved',{exact:true}).waitFor();
  const draft=JSON.parse(fs.readFileSync(path.join(root,'storage','base-report-'+hash+'-202607.json'),'utf8'));
  assert(draft.coopAudit.some(event=>event.action==='manual-coop-override'&&event.after==='1.25'));
  assert(draft.coopAudit.some(event=>event.action==='coop-draft-saved'));
  fs.unlinkSync(secretPath);
  const openAdmin=async(url=httpsBase)=>{
   const admin=await browser.newPage({extraHTTPHeaders:{'X-Test-Actor':'admin'}});
   admin.on('pageerror',error=>errors.push(error.message));
   await admin.goto(url+'/base-report.php?month=202607');
   return admin;
  };
  const settings=(target,action,extra={})=>target.evaluate(async({action,extra})=>{
   const response=await fetch('coop-consumption-api.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({...window.genstatReport.snapshot(),action,...extra})});
   return {status:response.status,body:await response.json()};
  },{action,extra});
  const candidate={host:'fixture.example',port:'3306',database:'fixture',username:'reader',ca:cert,password:'fixture-password'};
  const insecure=await openAdmin(base);
  assert.equal((await settings(insecure,'connection-test',{connection:candidate})).status,403);
  await insecure.locator('#sql-settings-open').click();
  await insecure.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));
  await insecure.locator('#coop-open').click();
  assert.equal(await insecure.locator('#coop-settings-open').count(),0);
  await insecure.getByText(/Configuration required. Use trusted HTTPS/).waitFor();
  assert(await insecure.locator('#coop-test').isDisabled());
  await insecure.close();

  const admin=await openAdmin();
  const other=await openAdmin();
  assert.equal((await settings(admin,'connection-save',{connection:candidate})).status,400);
  assert.match((await settings(admin,'connection-test',{connection:{...candidate,password:'fixture-invalid'}})).body.error,/authentication failed/);
  assert.equal(fs.existsSync(secretPath),false);
  assert.equal((await settings(other,'connection-test',{connection:candidate})).status,200);
  const adminDialog=admin.locator('#coop-dialog');
  await admin.locator('#sql-settings-open').click();
  await admin.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));
  await admin.locator('#coop-open').click();
  await adminDialog.locator('#coop-connection-status',{hasText:'Not Configured'}).waitFor();
  const form=adminDialog.locator('#coop-connection'),password=form.locator('[name=password]');
  assert.equal(await form.locator('[name=host]').inputValue(),'127.0.0.1');
  assert.equal(await form.locator('[name=port]').inputValue(),'3306');
  assert.equal(await form.locator('[name=database]').inputValue(),'palecxzp_pal_db');
  assert.equal(await form.locator('[name=username]').inputValue(),'');
  assert.equal(await password.inputValue(),'');
  for(const [name,value] of Object.entries(candidate))await form.locator('[name='+name+']').fill(value);
  await adminDialog.getByRole('button',{name:'Show password'}).click();
  assert.equal(await password.getAttribute('type'),'text');
  await adminDialog.getByRole('button',{name:'Hide password'}).click();
  await adminDialog.getByRole('button',{name:'Test Connection'}).click();
  await adminDialog.getByText(/Verified TLS connection and read probe succeeded/).waitFor();
  assert.equal(fs.existsSync(secretPath),false);
  await form.locator('[name=username]').fill('different');
  assert(await adminDialog.getByRole('button',{name:'Save Connection'}).isDisabled());
  await form.locator('[name=username]').fill(candidate.username);
  await adminDialog.getByRole('button',{name:'Test Connection'}).click();
  await adminDialog.getByText(/Verified TLS connection and read probe succeeded/).waitFor();
  await adminDialog.getByRole('button',{name:'Save Connection'}).click();
  await adminDialog.getByText('Connection settings saved.').waitFor();
  assert.equal(await password.inputValue(),'');
  const publicSettings=(await settings(admin,'connection-view')).body;
  assert.equal(publicSettings.status,'Connection Successful');
  assert(publicSettings.lastSuccess&&publicSettings.lastAttempt);
  assert.match((await settings(admin,'local-preview')).body.error,/approved saved loopback connection/);
  assert(!JSON.stringify(publicSettings).includes(candidate.password));
  assert.deepEqual(Object.keys(publicSettings.connection),['host','port','database','username','ca']);
  const savedSecret=fs.readFileSync(secretPath,'utf8');
  assert(!savedSecret.includes(candidate.password));
  assert.equal((await settings(other,'connection-save',{connection:candidate})).status,400);
  assert.equal(fs.readFileSync(secretPath,'utf8'),savedSecret);
  const staged=await call('refresh');
  assert(staged.body.complete);
  const replacement={...candidate,password:'fixture-replacement'};
  assert.equal((await settings(admin,'connection-test',{connection:replacement})).status,200);
  const failWrite=path.join(root,'fail-secret-write');
  fs.writeFileSync(failWrite,'');
  assert.equal((await settings(admin,'connection-save',{connection:replacement})).status,400);
  fs.unlinkSync(failWrite);
  assert.equal(fs.readFileSync(secretPath,'utf8'),savedSecret);
  assert.equal((await settings(admin,'connection-save',{connection:replacement})).status,200);
  assert.notEqual(fs.readFileSync(secretPath,'utf8'),savedSecret);
  assert.match((await call('apply',{run:staged.body.id,confirmed:true})).body.error,/stale/);
  const beforeRemove=await call('refresh');
  const members=(await call('view')).body.accounts;
  await adminDialog.locator('#coop-admin summary').click();
  const remove=adminDialog.getByRole('button',{name:'Remove Connection'});
  await remove.click();
  await admin.locator('#report-confirmation').getByRole('button',{name:'Cancel'}).click();
  assert((await settings(admin,'connection-view')).body.configured);
  await remove.click();
  await admin.locator('#report-confirmation').getByRole('button',{name:'Continue'}).click();
  await adminDialog.getByText('Connection removed.').waitFor();
  assert.equal((await settings(admin,'connection-view')).body.status,'Not Configured');
  assert.deepEqual(Object.keys(JSON.parse(fs.readFileSync(secretPath,'utf8'))),['version']);
  assert.match((await call('apply',{run:beforeRemove.body.id,confirmed:true})).body.error,/stale/);
  assert.deepEqual((await call('view')).body.accounts,members);
  assert.deepEqual(JSON.parse(fs.readFileSync(path.join(root,'storage','base-report-'+hash+'-202607.json'),'utf8')),draft);
  const listener=net.createServer();await new Promise(resolve=>listener.listen(0,'127.0.0.1',resolve));
  devPort=listener.address().port;await new Promise(resolve=>listener.close(resolve));
  const devBase='http://127.0.0.1:'+devPort;
  const local=JSON.parse(fixture('coop-local-source'));
  const sourceHash=fixture('coop-local-source-hash');
  fs.writeFileSync(path.join(root,'dev-router.php'),`<?php
   if(hash_equals('${devTlsMarker}',$_SERVER['HTTP_X_FIXTURE_TLS']??''))$_SERVER['HTTPS']='on';
   if(isset($_SERVER['HTTP_X_FIXTURE_NO_BILLING_REVIEW'])){
    putenv('GENSTAT_COOP_SCHEMA_REVIEWED=0');
    putenv('GENSTAT_COOP_REVIEWED_TEMPLATE=');
   }
   if(isset($_SERVER['HTTP_X_FIXTURE_NO_OWNERSHIP']))putenv('GENSTAT_COOP_OWNERSHIP=0');
   return require __DIR__.'/tools/coop-dev-router.php';`);
  devServer=spawn(php,['-d','session.save_path=storage/sessions','-d','display_errors=0','-S','127.0.0.1:'+devPort,'-t',root,path.join(root,'dev-router.php')],{
   cwd:root,windowsHide:true,stdio:['ignore','ignore','pipe'],env:{...fixtureEnv,GENSTAT_COOP_DEV_MODE:'1',GENSTAT_COOP_LOCAL_PORTS:String(local.port),GENSTAT_COOP_CLOUD_ENABLED:'0'}
  });
  devServer.stderr.on('data',data=>{serverLog+=data.toString();});
  for(let index=0;index<30;index++){
   try{if((await fetch(devBase+'/base-report.php?month=202607')).ok)break;}catch{}
   await new Promise(resolve=>setTimeout(resolve,100));
  }
  const dev=await browser.newPage();
  dev.on('pageerror',error=>errors.push(error.message));
  await dev.goto(devBase+'/base-report.php?month=202607');
  const devView=await settings(dev,'view');
  assert.equal(devView.status,200);
  assert.equal(devView.body.developmentMode,true);
  assert.equal((await settings(dev,'connection-view')).status,200);
  assert.equal((await settings(dev,'view',{csrf:'invalid'})).status,403);
  assert.match((await settings(dev,'local-preview')).body.error,/verified localhost HTTPS/);
  assert.match((await settings(dev,'connection-test',{connection:local})).body.error,/HTTPS/);
  for(const headers of [{'X-Forwarded-For':'127.0.0.1'},{Forwarded:'for=127.0.0.1'},{'X-Remote-User':'fixture-admin'}]){
   assert.equal((await fetch(devBase+'/',{headers})).status,403);
  }
  assert.equal((await fetch(devBase+'/storage/base-report-'+hash+'-202607.json')).status,404);
  const cooped=async entry=>{
   await dev.locator('#sql-settings-open').click();
   await dev.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));
   await dev.locator(entry).click();
  };
  await cooped('#coop-open');
  await dev.getByText(/Local development access enabled. Configure trusted HTTPS/).waitFor();
  await dev.getByText('Development Mode',{exact:true}).waitFor({state:'visible'});
  assert(await dev.locator('#coop-test').isDisabled());
  await dev.getByText('Reporting Period: July 2026').waitFor();
  await dev.locator('#coop-enrollment-section summary').click();
  assert(await dev.locator('#coop-accounts').isVisible());
  await dev.locator('#coop-close').click();
  assert.match((await settings(admin,'connection-test',{connection:local})).body.error,/verified development mode/);
  const secureDev=await browser.newPage({extraHTTPHeaders:{'X-Fixture-Dev':'1','X-Fixture-No-Billing-Review':'1','X-Fixture-No-Ownership':'1'}});
  await secureDev.goto(httpsBase+'/base-report.php?month=202607');
  assert.match((await settings(secureDev,'connection-test',{connection:{...local,port:local.port===65535?65534:65535}})).body.error,/approved loopback port/);
  assert.match((await settings(secureDev,'connection-test',{connection:{...local,host:'localhost'}})).body.error,/hostname/);
  assert.match((await settings(secureDev,'connection-test',{connection:{...local,database:'genstat_db'}})).body.error,/approved database/);
  assert.match((await settings(secureDev,'connection-test',{connection:{...local,password:'wrong-fixture-password'}})).body.error,/authentication failed/);
  fixture('coop-local-grant-write');
  try{
   assert.match((await settings(secureDev,'connection-test',{connection:local})).body.error,/Insufficient read-only permissions/);
  }finally{fixture('coop-local-revoke-write');}
  await secureDev.locator('#sql-settings-open').click();
  await secureDev.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));
  await secureDev.locator('#coop-open').click();
  const localDialog=secureDev.locator('#coop-dialog');
  await localDialog.locator('#coop-connection-status',{hasText:'Not Configured'}).waitFor();
  for(const [name,value] of Object.entries(local)){
   const field=localDialog.locator('#coop-connection [name='+name+']');
   await field.fill(String(value));
  }
  await localDialog.getByRole('button',{name:'Test Connection'}).click();
  await localDialog.getByText('Local connection and read probe succeeded.').waitFor();
  await localDialog.getByRole('button',{name:'Save Connection'}).click();
  await localDialog.getByText('Connection settings saved.').waitFor();
  assert.equal(await localDialog.locator('#coop-admin').getAttribute('open'),null);
  assert(await localDialog.locator('#coop-accounts').isVisible());
  assert(!fs.readFileSync(secretPath,'utf8').includes(local.password));
  const localState=(await settings(secureDev,'connection-view')).body;
  assert.equal(localState.sourceType,'Local');
  assert.equal(localState.status,'Connection Successful');
  assert.equal(localState.editable,true);
  const enrollmentView=(await settings(secureDev,'view')).body;
  assert.equal(enrollmentView.enrollmentReady,true);
  assert.equal(enrollmentView.localPreviewReady,true);
  assert.equal(enrollmentView.ready,false);
  assert.equal(enrollmentView.owned,false);
  assert(await localDialog.locator('#coop-enroll').isEnabled());
  assert(await localDialog.locator('#coop-refresh').isEnabled());
  assert(await localDialog.locator('#coop-apply').isDisabled());
  assert.equal(await localDialog.locator('#coop-billing-status').textContent(),'Pending Review');
  assert.match((await settings(secureDev,'refresh')).body.error,/review/);
  const member={acctCode:'004',area:'main',effective:'202608'};
  for(const changes of [{area:'invalid'},{effective:'202613'}]){
   assert.match((await settings(secureDev,'enroll',{...member,...changes})).body.error,/valid area and effective month/);
  }
  assert.equal((await settings(secureDev,'enroll',{...member,csrf:'invalid'})).status,403);
  assert.equal((await settings(secureDev,'enroll',member)).status,200);
  assert.match((await settings(secureDev,'enroll',member)).body.error,/overlapping/);
  assert.match((await settings(secureDev,'enroll',{...member,effective:'202609'})).body.error,/overlapping/);
  await localDialog.getByText('Pending verification',{exact:true}).first().waitFor();
  const beforeLocal=await secureDev.evaluate(()=>window.genstatReport.snapshot());
  const localResponse=secureDev.waitForResponse(r=>r.url().endsWith('/coop-consumption-api.php')&&r.request().postDataJSON()?.action==='local-preview');
  await localDialog.locator('#coop-refresh').click();
  const provisional=await (await localResponse).json();
  await localDialog.getByText('Provisional preview ready.',{exact:true}).waitFor();
  assert(provisional.provisional&&provisional.complete);
  assert(await localDialog.locator('#coop-apply').isDisabled());
  assert.deepEqual(await secureDev.evaluate(()=>window.genstatReport.snapshot()),beforeLocal);
  assert.equal(await secureDev.locator('td[data-ref="B26"]').getAttribute('data-sql-applied'),null);
  await localDialog.getByText('0.00 KWH',{exact:true}).first().waitFor();
  await localDialog.locator('#coop-close').click();
  await secureDev.locator('#report-month').selectOption('202608');
  await secureDev.waitForURL('**/*month=202608');
  await secureDev.locator('#sql-settings-open').click();
  await secureDev.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));
  await secureDev.locator('#coop-open').click();
  await localDialog.getByText('Pending verification',{exact:true}).first().waitFor();
  const previewContext=await dev.evaluate(()=>window.genstatReport.previewContext());
  assert.match((await settings(dev,'apply',{...previewContext,run:provisional.id,confirmed:true})).body.error,/provisional local preview cannot be applied/i);
  const devRun=await settings(dev,'refresh',previewContext);
  assert.equal(devRun.status,200);
  assert(devRun.body.complete);
  await dev.setExtraHTTPHeaders({'X-Fixture-No-Ownership':'1'});
  assert.match((await settings(dev,'apply',{...previewContext,run:devRun.body.id,confirmed:true})).body.error,/Enable COOP ownership/);
  await dev.setExtraHTTPHeaders({});
  assert.equal((await settings(dev,'apply',{...previewContext,run:devRun.body.id,confirmed:true})).status,200);
  assert.equal(fixture('coop-local-source-hash'),sourceHash);
  console.log('PASS: native loopback COOP reader tested and saved securely; source rows unchanged.');
  process.stdout.write(fixture('coop-dev-audit'));
  const production=await settings(page,'view');
  assert.equal(production.body.developmentMode,false);
  assert.equal((await call('view',{},false)).status,403);
  assert.deepEqual(errors,[]);
  assert.doesNotMatch(serverLog,/PHP (?:Warning|Fatal error|Parse error|Notice)/);
  console.log('PASS: isolated COOP authorization, enrollment, period retrieval, stale/failed preview, ownership, responsive UI, idempotent apply, restore, draft and export.');
 }finally{
  if(browser)await browser.close();
  if(tlsProxy)await new Promise(resolve=>tlsProxy.close(resolve));
  if(devServer&&devServer.exitCode===null){const closed=once(devServer,'exit');devServer.kill();await closed;}
  if(server&&server.exitCode===null){const closed=once(server,'exit');server.kill();await closed;}
  if(fs.existsSync(path.join(root,'fixture-state.json')))process.stdout.write(fixture('cleanup'));
  const resolved=path.resolve(root);
  if(path.dirname(resolved)!==path.resolve(os.tmpdir())||!path.basename(resolved).startsWith('genstat-apply-save-'))throw Error('Unsafe cleanup path');
  fs.rmSync(resolved,{recursive:true,force:true});
  for(const suffix of ['.external.json','.external.json.lock'])fs.rmSync(resolved+suffix,{force:true});
 }
})().catch(error=>{console.error(error);process.exitCode=1;});
