'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const assert=require('assert');
const fs=require('fs'),path=require('path');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];let testDraft=null;
 page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.goto(process.env.GENSTAT_TEST_COMPACT_URL||'http://localhost/genstat/');
  for(const [endpoint,action,error] of [
   ['base-report.php','unlock','Invalid session token.'],
   ['query-settings-api.php','metadata','Session expired. Reload the report.'],
  ]){
   const denied=await page.request.post(new URL(endpoint,page.url()).href,{data:{action,ref:'Q11',csrf:'invalid'}});
   assert.equal(denied.status(),400);
   assert.equal((await denied.json()).error,error);
  }
  assert.equal(await page.locator('tbody tr').count(),38);
  assert.equal(await page.locator('td input').count(),122);
  assert.equal(await page.locator('[data-classification="database_input"]').count(),96);
  assert.equal(await page.locator('[data-classification="formula"]').count(),312);
  assert.equal(await page.locator('[data-ref="T8"]').count(),0);
  assert.equal(await page.locator('[data-ref="A50"]').count(),0);
  assert.equal(await page.locator('[data-ref="H25"] .value').textContent(),'(477.00)');
  console.log('PASS: exact reporting range, counts, repeated rows, and negative format');
  const initial=await page.locator('[data-ref="Q11"] .value').textContent();
  const input=page.locator('input[data-ref="B11"]');await input.fill('24850752.84');await input.press('Tab');
  await page.waitForFunction(()=>document.querySelector('#status').textContent==='Unsaved changes');
  assert.equal(await page.locator('[data-ref="Q11"] .value').textContent(),'42,854,345.90');
  assert.notEqual(initial,await page.locator('[data-ref="Q11"] .value').textContent());
  await page.locator('td[data-ref="Q11"]').focus();
  assert.equal(await page.locator('#formula').inputValue(),'=SUM(B11:P11)');
  assert(await page.locator('#formula').getAttribute('readonly')!==null);
  assert.equal(await page.locator('td[data-ref="Q11"] input').count(),0);
  const denial=await page.evaluate(async()=>{const d=JSON.parse(document.querySelector('#report-data').textContent);const r=await fetch('base-report.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...d,action:'calculate',values:{Q11:'1'},formulas:{}})});return r.status;});assert.equal(denial,400);
  console.log('PASS: browser edit recalculates totals, formula bar preserves expression, server rejects formula overwrite');
  page.once('dialog',d=>d.accept());await page.locator('#unlock').click();
  await page.waitForFunction(()=>!document.querySelector('#formula').readOnly);
  await page.locator('#formula').fill('=SUM(B11:P11)+1');await page.locator('#formula').press('Enter');
  await page.waitForFunction(()=>document.querySelector('[data-ref="Q11"] .value').textContent==='42,854,346.90');
  console.log('PASS: per-cell confirmed formula unlocking and recalculation');
  const hash=await page.evaluate(()=>JSON.parse(document.querySelector('#report-data').textContent).hash);
  const candidate=path.resolve(`storage/base-report-${hash}.json`);
  if(fs.existsSync(candidate))throw Error('Existing base draft: refusing persistence test writes.');
  testDraft=candidate;await page.locator('#save').click();await page.waitForFunction(()=>document.querySelector('#status').textContent==='Draft saved');
  await page.reload();assert.equal(await page.locator('[data-ref="Q11"] .value').textContent(),'42,854,346.90');
  const stale=await page.evaluate(async()=>{const d=JSON.parse(document.querySelector('#report-data').textContent);const r=await fetch('base-report.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...d,revision:0,action:'save'})});return r.status;});assert.equal(stale,400);
  const saved=JSON.parse(fs.readFileSync(testDraft,'utf8'));assert.equal(saved.revision,1);assert.equal(saved.values.B11,'24850752.84');fs.unlinkSync(testDraft);fs.unlinkSync(testDraft+'.lock');testDraft=null;
  console.log('PASS: isolated draft save/reload and stale revision protection; temporary test draft removed');
  // No save: leave existing drafts untouched. Dismiss unsaved-change prompt on reload.
  page.once('dialog',d=>d.accept());await page.reload();
  const headerTop=await page.locator('[data-ref="B8"]').evaluate(e=>e.getBoundingClientRect().top);
  await page.locator('.report-scroll').evaluate(e=>{e.scrollTop=500;e.scrollLeft=600;});
  const positions=await page.evaluate(()=>{const box=document.querySelector('.report-scroll').getBoundingClientRect();return {boxLeft:box.left,partLeft:document.querySelector('[data-ref="A30"]').getBoundingClientRect().left,headerTop:document.querySelector('[data-ref="B8"]').getBoundingClientRect().top};});
  assert(Math.abs(positions.headerTop-headerTop)<2);assert(Math.abs(positions.partLeft-positions.boxLeft)<3);
  console.log('PASS: desktop headers and Particulars remain pinned while scrolling');
  await page.locator('.report-scroll').evaluate(e=>{e.scrollTop=0;e.scrollLeft=0;});await page.screenshot({path:'tests/base-report-desktop.png'});
  await page.setViewportSize({width:390,height:844});
  assert(await page.locator('.report-scroll').evaluate(e=>e.scrollWidth>e.clientWidth));
  assert(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth));
  await page.locator('.report-scroll').evaluate(e=>{e.scrollLeft=1800;e.scrollTop=400;});
  const mobile=await page.locator('[data-ref="A30"]').evaluate(e=>e.getBoundingClientRect().left);assert(mobile<10);
  await page.screenshot({path:'tests/base-report-mobile.png'});
  assert.equal(errors.length,0);console.log('PASS: mobile overflow, sticky column, no document overflow or JavaScript errors');
 }finally{await browser.close();if(testDraft&&fs.existsSync(testDraft)){const saved=JSON.parse(fs.readFileSync(testDraft,'utf8'));if(saved.revision===1&&saved.values.B11==='24850752.84'){fs.unlinkSync(testDraft);if(fs.existsSync(testDraft+'.lock'))fs.unlinkSync(testDraft+'.lock');}}}
})().catch(e=>{console.error(e);process.exit(1);});
