'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const fs=require('fs'),path=require('path'),assert=require('assert'),crypto=require('crypto'),{execFileSync}=require('child_process');
const php='C:\\xampp\\php\\php.exe',base='http://localhost/genstat/';
(async()=>{
 const draft=path.resolve('storage/draft-2026-06.json'),auth=path.resolve('storage/formula-auth.json');
 if(fs.existsSync(draft)||fs.existsSync(auth))throw new Error('Refusing to replace an existing draft or administrator configuration.');
 const originals=new Map();for(const file of ['storage/formula-auth-attempts.json','storage/formula-auth-attempts.json.lock'])originals.set(file,fs.existsSync(file)?fs.readFileSync(file):null);
 const password=crypto.randomBytes(24).toString('hex');
 const hash=execFileSync(php,['-r',"echo password_hash(file_get_contents('php://stdin'), PASSWORD_DEFAULT);"],{input:password,encoding:'utf8'});
 fs.writeFileSync(auth,JSON.stringify({identity:'integration-test-editor',passwordHash:hash}));
 let browser;
 try{
  browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
  const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
  await page.goto(base);await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('no saved draft'));
  const bar=page.locator('#formula-text');await page.locator('.cell[data-ref="B12"]').click();assert.equal(await bar.inputValue(),'=+B11');assert(await bar.evaluate(el=>el.readOnly));
  await page.locator('.cell[data-ref="H12"]').click();assert.equal(await bar.inputValue(),'=+H11');assert(await bar.evaluate(el=>el.readOnly));console.log('PASS: formula bar shows original and expanded shared formulas while locked');
  await page.locator('input[data-ref="C39"]').click();await bar.fill('=ROUND(B11/3,2)');await bar.press('Enter');await page.click('#save');await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft saved'));
  assert.equal(JSON.parse(fs.readFileSync(draft,'utf8')).formulas.C39,'=ROUND(B11/3,2)');assert.equal(await page.locator('.cell[data-ref="C39"] .input-preview').textContent(),'8,997,442.91');console.log('PASS: approved blank accepts formula, calculates, formats and persists it');
  await page.reload();await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft reopened'));await page.locator('input[data-ref="C39"]').click();assert.equal(await bar.inputValue(),'=ROUND(B11/3,2)');
  const headers={'X-CSRF-Token':await page.locator('meta[name="csrf-token"]').getAttribute('content'),'X-Template-Hash':await page.locator('meta[name="template-hash"]').getAttribute('content'),'X-Layout-Hash':await page.locator('meta[name="layout-hash"]').getAttribute('content')};
  let saved=JSON.parse(fs.readFileSync(draft,'utf8'));
  let response=await page.request.post(base+'api.php?action=save',{headers,data:{values:saved.values,formulas:{...saved.formulas,Q11:'=2'},revision:saved.revision,unlocked:true,admin:true}});assert.equal(response.status(),403);console.log('PASS: direct HTTP request cannot unlock protected edits with client flags');
  response=await page.request.post(base+'api.php?action=unlock',{headers,data:{password:'wrong-password',admin:true}});assert.equal(response.status(),403);assert(!await page.locator('body').evaluate(el=>el.classList.contains('formula-editing-active')));console.log('PASS: invalid credentials do not grant an editing session');
  page.once('dialog',d=>d.accept());await page.click('#formula-unlock');await page.fill('#formula-password',password);await page.click('#formula-unlock-form button[type="submit"]');await page.waitForFunction(()=>document.body.classList.contains('formula-editing-active'));
  const formulaIndicator=page.locator('.cell[data-ref="Q11"]');assert.equal(await formulaIndicator.evaluate(el=>getComputedStyle(el,'::before').content),'"fx"');assert((await formulaIndicator.getAttribute('title')).includes('Authorized formula-bar editing'));
  await page.locator('.cell[data-ref="Q11"]').click();assert(!await bar.evaluate(el=>el.readOnly));await bar.fill('=SUM(B11:P11)+1');await bar.press('Escape');assert.equal(await bar.inputValue(),'=SUM(B11:P11)');
  await bar.fill('=SUM(B11:P11)+1');await bar.press('Enter');
  await page.locator('.cell[data-ref="H12"]').click();await bar.fill('=H11+2');await bar.press('Enter');await page.click('#save');await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft saved'));
  saved=JSON.parse(fs.readFileSync(draft,'utf8'));assert.equal(saved.formulas.Q11,'=SUM(B11:P11)+1');assert(saved.formulaAudit.some(a=>a.cell==='Q11'&&a.editor==='integration-test-editor'&&a.originalFormula==='=SUM(B11:P11)'&&a.newFormula==='=SUM(B11:P11)+1'));
  assert.equal(await page.locator('.cell[data-ref="Q11"] .cell-content').textContent(),'46,371,289.70');console.log('PASS: password authorization, Escape cancellation, shared/ordinary edits, dependent calculation and audit identity');
  await page.locator('input[data-ref="E39"]').click();await bar.fill('=SUM(');await bar.press('Enter');await page.click('#save');await page.waitForFunction(()=>document.querySelector('#status').classList.contains('error'));assert.equal(JSON.parse(fs.readFileSync(draft,'utf8')).revision,saved.revision);
  await bar.fill('=E39+1');await bar.press('Enter');await page.click('#save');await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('Circular reference'));assert.equal(JSON.parse(fs.readFileSync(draft,'utf8')).revision,saved.revision);console.log('PASS: invalid and circular formulas return understandable errors without altering the draft');
  // Restore pending invalid edit before testing the lock/session lifecycle.
  await page.locator('input[data-ref="E39"]').click();await bar.fill('');await bar.press('Enter');
  const cookies=await page.context().cookies(),session=cookies.find(c=>c.name==='PHPSESSID');assert(session);
  execFileSync(php,['-r',"session_id($argv[1]);session_start();$_SESSION['formulaGrant']['expiresAt']=time()-1;session_write_close();",session.value]);
  response=await page.request.post(base+'api.php?action=save',{headers,data:{values:saved.values,formulas:{...saved.formulas,Q11:'=3'},revision:saved.revision}});assert.equal(response.status(),403);console.log('PASS: server rejects expired authorization on every protected save');
  // Advance only the browser clock to exercise the visible automatic lock.
  await page.evaluate(()=>{const real=Date.now;Date.now=()=>real()+601000;});await page.waitForFunction(()=>!document.body.classList.contains('formula-editing-active'));assert.equal(await page.locator('#formula-unlock').textContent(),'Unlock Formula Editing');
  response=await page.request.post(base+'api.php?action=lock',{headers,data:{}});assert.equal(response.status(),200);
  const wait=page.waitForEvent('download');await page.click('#export');const download=await wait;await download.saveAs('tests/formula-browser.xlsx');process.stdout.write(execFileSync(php,['tests/check-formula-export.php','tests/formula-browser.xlsx'],{encoding:'utf8'}));
  await page.reload();await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft reopened'));assert(!await page.locator('body').evaluate(el=>el.classList.contains('formula-editing-active')));await page.locator('.cell[data-ref="Q11"]').click();assert.equal(await bar.inputValue(),'=SUM(B11:P11)+1');assert(await bar.evaluate(el=>el.readOnly));
  assert.deepEqual(errors,[]);console.log('PASS: expiry returns to locked mode; formula edits survive reload; no JavaScript errors');
 }finally{
  if(browser)await browser.close();for(const file of [auth,draft,draft+'.lock'])if(fs.existsSync(file))fs.unlinkSync(file);
  for(const [file,bytes] of originals){if(bytes!==null)fs.writeFileSync(file,bytes);else if(fs.existsSync(file))fs.unlinkSync(file);}
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
