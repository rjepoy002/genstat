'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const assert=require('assert');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
 const page=await browser.newPage({viewport:{width:1440,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.goto(process.env.GENSTAT_TEST_COMPACT_URL||'http://localhost/genstat/');await page.locator('#sql-settings-open').click();await page.waitForFunction(()=>document.querySelector('#sql-row option'));
  await page.locator('#sql-row').selectOption('genstat-base/Jul26/A18');
  
  await page.locator('#sql-editor').fill('WITH params AS (SELECT :ReportMonth AS ReportMonth) SELECT AreaID, AreaName, 1200838.00 AS Sales_KWH, 455.00 AS KWH_Diff, 36.00 AS Total_Cancelled_KWH, 1201293.00 AS TotalKWhSold FROM area_master ORDER BY AreaID DESC');
  await page.locator('#sql-area-column').fill('AreaID');await page.locator('#sql-value-column').fill('TotalKWhSold');await page.locator('#sql-sacode-column').fill('');
  const response=page.waitForResponse(r=>r.url().endsWith('query-settings-api.php')&&JSON.parse(r.request().postData()).action==='inspect-result');await page.locator('#sql-test').click();assert.equal((await response).status(),200);await page.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));
  assert.equal(await page.locator('#sql-auto-column').inputValue(),'AreaName');assert.equal(await page.locator('#sql-bindings select[data-ref="F18"]').inputValue(),'12');assert.equal(await page.locator('#sql-preview tbody tr').count(),20);assert((await page.locator('#sql-mapping-message').textContent()).includes('automatically matched'));
  await page.locator('#sql-bindings select[data-ref="C18"]').selectOption('9');await page.locator('#sql-auto-column').selectOption('AreaID');await page.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));assert.equal(await page.locator('#sql-bindings select[data-ref="C18"]').inputValue(),'9');
  await page.locator('#sql-auto-column').selectOption('AreaName');await page.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));
  const c=page.locator('#sql-bindings select[data-ref="C18"]');await c.locator('..').getByRole('button',{name:'Reset to Auto'}).click();await page.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));assert.equal(await c.inputValue(),'8');
  await page.locator('#sql-formula-enabled').check();await page.locator('#sql-test').click();await page.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));
  await page.locator('#sql-formula-preset').selectOption('Sales_KWH + KWH_Diff + Total_Cancelled_KWH');await page.locator('#sql-preview-formula').click();await page.waitForFunction(()=>document.querySelector('#sql-formula-message').textContent.includes('validated successfully'));
  assert.equal(await page.locator('#sql-preview tbody tr').first().locator('td').last().textContent(),'1201329');
  await page.locator('#sql-calculation-section').scrollIntoViewIfNeeded();await page.screenshot({path:'tests/query-calculation-formula.png'});
  await page.locator('#sql-calculation').fill('TotalKWhSold');await page.locator('#sql-preview-formula').click();await page.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));assert.equal(await page.locator('#sql-preview tbody tr').first().locator('td').last().textContent(),'1201293');
  await page.locator('#sql-calculation').fill('Sales_KWH / 0');await page.locator('#sql-preview-formula').click();await page.waitForFunction(()=>document.querySelector('#sql-formula-message').textContent.includes('division by zero'));assert(await page.locator('#sql-apply').isDisabled());
  await page.locator('#sql-calculation').fill('Sales_KWH + KWH_Diff');await page.locator('#sql-calculation').evaluate(e=>{e.focus();e.setSelectionRange(0,0);});await page.locator('#sql-column-tokens button').filter({hasText:/^TotalKWhSold$/}).click();assert.equal(await page.locator('#sql-calculation').inputValue(),'TotalKWhSoldSales_KWH + KWH_Diff');
  assert.equal(await page.locator('#sql-bindings select[data-ref="F18"]').count(),1);assert.deepEqual(errors,[]);
  await page.setViewportSize({width:390,height:844});assert(await page.locator('#sql-calculation').isVisible());assert(await page.locator('#sql-calculation').evaluate(e=>e.getBoundingClientRect().right<=innerWidth));
  console.log('PASS: live SQL preview, automatic mapping, manual override retention, reset to auto, row-18 formula presets/preview, division errors, cursor insertion, formula protection and mobile width. No configuration, draft or workbook saved.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
