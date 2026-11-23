'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright'),assert=require('assert');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.goto(process.env.GENSTAT_TEST_COMPACT_URL||'http://localhost/genstat/');const original=await page.locator('[data-ref="F18"] .value').textContent();
  await page.locator('#sql-settings-open').click();await page.waitForFunction(()=>document.querySelector('#sql-bindings select'));
  assert.equal(await page.locator('#sql-bindings select[data-ref="F11"]').inputValue(),'12');
  await page.locator('#sql-row').selectOption('genstat-base/Jul26/A18',{force:true});
  assert.equal(await page.locator('#sql-bindings select').count(),15);assert.equal(await page.locator('#sql-bindings select[data-ref="F18"]').inputValue(),'12');await page.locator('#sql-sacode-column').fill('');await page.locator('#sql-formula-enabled').uncheck();
  const ids=[1,8,9,10,12,2,5,15,4,3,6,19,16,18,11];for(const select of await page.locator('#sql-bindings select').all()){const ref=await select.getAttribute('data-ref');await select.selectOption(String(ids[ref.charCodeAt(0)-66]));}
  await page.locator('#sql-value-column').fill('TotalKWhSold');await page.locator('#sql-excluded').fill('');
  await page.locator('#sql-editor').fill(`SELECT AreaID, AreaName, CASE WHEN AreaID=12 THEN 3776446.00 ELSE AreaID*100 END AS TotalKWhSold FROM area_master WHERE CAST(:ReportMonth AS UNSIGNED)=202607 AND AreaID IN (${ids.join(',')}) ORDER BY AreaID DESC`);
  await page.locator('#sql-test').click();await page.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));assert((await page.locator('#sql-editor-state').textContent()).includes('validated'));
  assert.equal(await page.locator('#sql-preview tbody tr').count(),15);assert.equal((await page.locator('#sql-diagnostics').textContent()).trim(),'');
  assert(await page.locator('#sql-apply').isEnabled());
  assert.equal(await page.locator('#sql-save-config').count(),0);assert.equal(await page.locator('[data-ref="F18"] .value').textContent(),original);
  await page.locator('#sql-preview').scrollIntoViewIfNeeded();await page.screenshot({path:'tests/brookes-point-approved-preview.png'});
  assert.deepEqual(errors,[]);console.log('PASS: verified alias defaults, 15 recognized areas / 15 approved inputs, live 15-row SQL preview, no save/apply or value overwrite');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
