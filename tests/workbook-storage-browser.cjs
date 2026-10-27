'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const assert=require('assert');
(async()=>{const browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
try{const page=await browser.newPage({viewport:{width:1280,height:900}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
await page.goto('http://localhost/genstat/');await page.waitForFunction(()=>!document.querySelector('input[data-ref="B11"]').disabled);
assert.equal(await page.locator('.worksheet-tab').count(),10);assert.equal(await page.locator('.paleco-logo').count(),1);assert.equal(await page.locator('.formula-bar').count(),1);
await page.locator('.cell[data-ref="Q11"]').click();assert(await page.locator('#formula-text').evaluate(e=>e.readOnly));assert((await page.locator('#formula-text').inputValue()).length>0);
assert(await page.locator('.cell[data-ref="B11"] .input-preview').textContent().then(t=>/\.\d{2}$/.test(t)));
console.log('PASS: ten tabs, PALECO branding, visible formula bar, protected formula selection and Excel two-decimal display');
for(const name of ['RecovBill26','Jan26','Feb26','Mar26','Apr26','May26','Jun26','Jul26','Aug26','Sep26']){await page.goto('http://localhost/genstat/?sheet='+name);assert.equal(await page.locator('body').getAttribute('data-sheet'),name);assert(await page.locator('.cell.formula').count()>0);assert(await page.locator('.col-heading').count()>0);assert(await page.locator('.row-heading').count()>0);}
console.log('PASS: RecovBill and every monthly worksheet render formulas and row/column headings');
for(const path of ['excel-files/active/genstat_2026.xlsx','excel-files/archive/','storage/draft-2026-06.json','config/layout.json','genstat.xlsx']){const r=await page.request.get('http://localhost/genstat/'+path);assert.equal(r.status(),403,path);}
assert.deepEqual(errors,[]);console.log('PASS: Apache denies workbook/storage/config access; no browser JavaScript errors');
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exitCode=1;});
