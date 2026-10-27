'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const fs=require('fs'),path=require('path'),assert=require('assert'),crypto=require('crypto'),{execFileSync}=require('child_process');
const php='C:\\xampp\\php\\php.exe',base='http://localhost/genstat/';
(async()=>{
 const draft=path.resolve('storage/draft-2026-06.json'),auth=path.resolve('storage/formula-auth.json');
 if(fs.existsSync(draft)||fs.existsSync(auth))throw new Error('Refusing to replace an existing draft or administrator configuration.');
 const artifacts=['storage/formula-auth-attempts.json','storage/formula-auth-attempts.json.lock'],originals=new Map(artifacts.map(f=>[f,fs.existsSync(f)?fs.readFileSync(f):null]));
 let browser;
 try{
  assert(execFileSync(php,['tools/formula-admin.php','status'],{encoding:'utf8'}).includes('missing'));
  browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
  const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base);await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('no saved draft'));
  assert.equal(await page.locator('#period').inputValue(),'2026-06');assert.equal(await page.locator('#workbook-warning-count').textContent(),'4 warnings');assert(!await page.locator('#workbook-notes').evaluate(el=>el.open));
  assert.equal(await page.locator('select#period, #worksheet-select, .workspace-toolbar').count(),0);
  const headerHeight=await page.locator('.compact-header').evaluate(el=>el.getBoundingClientRect().height);assert(headerHeight>=90&&headerHeight<=105);
  for(const id of ['open','save','export','formula-unlock','sync-now']){const button=page.locator('#'+id);assert(await button.getAttribute('aria-label'));assert(await button.getAttribute('title'));}
  await page.locator('#open').focus();assert.equal(await page.locator('#open').evaluate(el=>getComputedStyle(el,'::after').visibility),'visible');
  await page.locator('#zoom-menu summary').click();await page.selectOption('#zoom','1');assert.equal(await page.locator('#sheet').evaluate(el=>getComputedStyle(el).transform),'matrix(1, 0, 0, 1, 0, 0)');assert(!await page.locator('#zoom-menu').evaluate(el=>el.open));await page.locator('#zoom-menu summary').click();await page.selectOption('#zoom','0.7');
  console.log('PASS: two-row header is 90–105px; icon labels, keyboard tooltips and zoom popup work');
  await page.locator('#workbook-notes summary').click();assert((await page.locator('#workbook-warning-list').textContent()).includes('RecovBill26'));await page.keyboard.press('Escape');
  await page.locator('input[data-ref="B11"]').fill('1000.123');await page.click('#save');await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft saved'));
  assert.equal(await page.locator('.cell[data-ref="B11"] .input-preview').textContent(),'1,000.12');assert.equal(JSON.parse(fs.readFileSync(draft,'utf8')).values.B11,'1000.123');console.log('PASS: June report, compact external warnings and precise manual save with two-decimal display');
  assert((await page.locator('#sync-meta').textContent()).includes(' | Draft saved · '));assert.equal(await page.locator('.notification-strip > #status').count(),0);assert.equal(await page.locator('#draft-saved-at').getAttribute('datetime'),JSON.parse(fs.readFileSync(draft,'utf8')).savedAt);
  const syncBox=await page.locator('#sync-timestamp').boundingBox(),saveBox=await page.locator('#draft-saved').boundingBox();assert(Math.abs(syncBox.y-saveBox.y)<2);assert(saveBox.x>=syncBox.x+syncBox.width-1);
  const savedStamp=await page.locator('#draft-saved-at').textContent();await page.click('#sync-now');await page.waitForFunction(()=>!document.querySelector('#sync-now').disabled);assert.equal(await page.locator('#draft-saved-at').textContent(),savedStamp);console.log('PASS: dynamic draft timestamp follows sync timestamp on one desktop line and survives synchronization');
  await page.locator('.cell[data-ref="Q11"]').click();assert(await page.locator('#formula-text').evaluate(el=>el.readOnly));assert.equal(await page.locator('#cell-protection').textContent(),'Protected formula');
  await page.click('#formula-unlock');await page.waitForSelector('#formula-setup-dialog[open]');assert(await page.locator('#formula-setup-dialog').isVisible());assert((await page.locator('#formula-setup-command').textContent()).includes('tools\\formula-admin.php setup'));
  const origin=await page.evaluate(()=>performance.timeOrigin),password=crypto.randomBytes(20).toString('hex');
  execFileSync(php,['tools/formula-admin.php','setup'],{input:'ui-test-editor\n'+password+'\n'+password+'\n',encoding:'utf8'});
  assert(!fs.readFileSync(auth,'utf8').includes(password));assert(execFileSync(php,['tools/formula-admin.php','status'],{encoding:'utf8'}).includes('ready'));
  page.once('dialog',d=>d.accept());await page.click('#formula-check-setup');await page.waitForSelector('#formula-unlock-dialog[open]');assert.equal(await page.evaluate(()=>performance.timeOrigin),origin);
  console.log('PASS: actual local CLI setup is recognized by the already-open page without reload');
  await page.fill('#formula-password','incorrect-password');await page.click('#formula-unlock-form button[type="submit"]');await page.waitForSelector('#formula-auth-error:not([hidden])');assert(await page.locator('#formula-auth-error').isVisible());assert(!await page.locator('body').evaluate(el=>el.classList.contains('formula-editing-active')));
  await page.fill('#formula-password',password);await page.click('#formula-unlock-form button[type="submit"]');await page.waitForFunction(()=>document.body.classList.contains('formula-editing-active'));
  assert.equal(await page.locator('#cell-protection').textContent(),'Formula unlocked');
  await page.setViewportSize({width:390,height:844});assert(await page.locator('#formula-mode').isVisible());assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await page.setViewportSize({width:1440,height:1000});
  await page.locator('#formula-text').fill('=ROUND(B11/3,2)');await page.locator('#formula-text').press('Enter');await page.click('#save');await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft saved'));assert.equal(await page.locator('.cell[data-ref="Q11"] .cell-content').textContent(),'333.37');
  await page.click('#formula-unlock');await page.waitForFunction(()=>!document.body.classList.contains('formula-editing-active'));assert(!await page.locator('body').evaluate(el=>el.classList.contains('formula-editing-active')));assert(await page.locator('#formula-text').evaluate(el=>el.readOnly));
  console.log('PASS: visible password errors, authorized formula editing, mobile active state and explicit relocking');
  const headers={};for(const [header,meta] of [['X-CSRF-Token','csrf-token'],['X-Template-Hash','template-hash'],['X-Layout-Hash','layout-hash']])headers[header]=await page.locator('meta[name="'+meta+'"]').getAttribute('content');
  const saved=JSON.parse(fs.readFileSync(draft,'utf8'));const response=await page.request.post(base+'api.php?action=save',{headers,data:{values:saved.values,formulas:{Q11:'=2'},revision:saved.revision,admin:true}});assert.equal(response.status(),403);assert.equal(JSON.parse(fs.readFileSync(draft,'utf8')).revision,saved.revision);
  const wait=page.waitForEvent('download');await page.click('#export');const download=await wait;await download.saveAs('tests/ui-export.xlsx');process.stdout.write(execFileSync(php,['tests/check-ui-export.php','tests/ui-export.xlsx'],{encoding:'utf8'}));
  for(const viewport of [{width:1440,height:1000},{width:768,height:1024},{width:390,height:844},{width:360,height:640}]){
   await page.setViewportSize(viewport);await page.evaluate(()=>{const v=document.querySelector('#viewport');v.scrollTop=0;v.scrollLeft=0;});
   const metrics=await page.evaluate(()=>{const v=document.querySelector('#viewport'),f=document.querySelector('.formula-bar').getBoundingClientRect();return {rootWidth:document.documentElement.scrollWidth,width:innerWidth,height:v.clientHeight,horizontal:v.scrollWidth>v.clientWidth,vertical:v.scrollHeight>v.clientHeight,adjacent:Math.abs(f.bottom-v.getBoundingClientRect().top)<2};});
   assert(metrics.rootWidth<=metrics.width+1);assert(metrics.height>=180);assert(metrics.horizontal&&metrics.vertical&&metrics.adjacent);
   for(const id of ['open','save','export','zoom-menu','formula-unlock','formula-mode']){const box=await page.locator('#'+id).boundingBox();assert(box&&box.x>=0&&box.x+box.width<=viewport.width+1);}
   const headerTop=await page.locator('.app-header').evaluate(el=>el.getBoundingClientRect().top);await page.locator('#viewport').evaluate(el=>el.scrollTop=400);assert.equal(await page.locator('.app-header').evaluate(el=>el.getBoundingClientRect().top),headerTop);await page.locator('#viewport').evaluate(el=>el.scrollTop=0);
   if(viewport.width===1440||viewport.width===390)await page.screenshot({path:'tests/ui-'+(viewport.width===1440?'desktop':'mobile')+'.png'});
   console.log('PASS: responsive '+viewport.width+'x'+viewport.height+' controls, worksheet scroll and stationary formula bar');
  }
  await page.locator('input[data-ref="B11"]').fill('invalid');await page.click('#save');assert(await page.locator('#status').evaluate(el=>el.classList.contains('error')&&el.getAttribute('role')==='alert'));assert(!await page.locator('#workbook-notes').evaluate(el=>el.open));
  assert.deepEqual(errors,[]);console.log('PASS: critical errors remain visible outside collapsed notes; no browser JavaScript errors');
 }finally{
  if(browser)await browser.close();for(const file of [auth,draft,draft+'.lock'])if(fs.existsSync(file))fs.unlinkSync(file);
  for(const [file,bytes] of originals){if(bytes!==null)fs.writeFileSync(file,bytes);else if(fs.existsSync(file))fs.unlinkSync(file);}
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
