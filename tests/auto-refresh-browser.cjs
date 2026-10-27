'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const assert=require('assert');
(async()=>{
 const browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
 try{
  const page=await browser.newPage();let updated=false,blocked=false;
  await page.route('**/api.php?action=*',async route=>{
   if(!['template-status','sync'].includes(new URL(route.request().url()).searchParams.get('action')))return route.continue();
   if(!updated)return route.continue();
   await route.fulfill({json:{hash:'new-template-version',status:{state:blocked?'conflict':'ready',message:'Conflict: Jun26!B11: input identity is ambiguous. Correct the workbook and select Sync Now.',workbook:'genstat.xlsx',syncedAt:new Date().toISOString()}}});
  });
  await page.goto('http://localhost/genstat/');await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('no saved draft'));
  const input=page.locator('input[data-ref="B11"]');await input.fill('12345.6789');updated=true;
  await page.waitForFunction(()=>document.querySelector('#sync-status').textContent.includes('Update available'),{},{timeout:15000});assert((await page.locator('.template-update').textContent()).includes('unsaved browser edits have been kept'));assert.equal(await input.inputValue(),'12345.6789');
  page.once('dialog',d=>d.dismiss());await page.locator('#sync-reload').click();assert.equal(await input.inputValue(),'12345.6789');
  await page.locator('.cell[data-ref="Q11"]').click();assert(await page.locator('#formula-text').evaluate(el=>el.readOnly));console.log('PASS: periodic update notification preserves unsaved input; Reload cancellation keeps edits and formulas protected');
  blocked=true;await page.click('#sync-now');await page.waitForFunction(()=>document.querySelector('#sync-status').textContent.startsWith('Conflict:'));assert(await page.locator('#sync-reload').evaluate(el=>el.hidden));assert.equal(await input.inputValue(),'12345.6789');assert.equal(await page.locator('.template-update').count(),1);assert(!(await page.locator('.template-update').textContent()).includes('administrator'));assert((await page.locator('#sync-meta').textContent()).includes('genstat.xlsx'));console.log('PASS: Sync Now immediately checks changes; one conflict message identifies sheet/cell without approval or lost edits');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exitCode=1;});
