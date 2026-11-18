'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const assert=require('assert'),fs=require('fs'),crypto=require('crypto');
const workbook='excel-files/reference/GENSTAT_BASE.xlsx',checksum=()=>crypto.createHash('sha256').update(fs.readFileSync(workbook)).digest('hex');
// Read-only UI fixture, deliberately not a replacement for approved business SQL.
const sql='WITH params AS (SELECT :ReportMonth AS ReportMonth) SELECT (SELECT ReportMonth FROM params) AS BillMonth, AreaID, AreaName, CAST(\'01\' AS CHAR) AS SACode, 1200838.00 AS Sales_KWH, 455.00 AS KWH_Diff, 36.00 AS Cancelled_KWH, 0.00 AS Cancelled_KWH_2, 36.00 AS Total_Cancelled_KWH, 1201293.00 AS TotalKWhSold FROM area_master WHERE (SELECT ReportMonth FROM params)=\'202607\'';
(async()=>{
 const browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
 const sourceBefore=checksum(),page=await browser.newPage(),errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.goto(process.env.GENSTAT_TEST_COMPACT_URL||'http://localhost/genstat/');const before=await page.evaluate(()=>window.genstatReport.snapshot());
  await page.locator('#sql-settings-open').click();await page.waitForFunction(()=>document.querySelector('#sql-row option'));await page.locator('#sql-row').selectOption('genstat-base/Jul26/A18');
  await page.locator('#sql-editor').fill(sql);await page.locator('#sql-area-column').fill('AreaID');await page.locator('#sql-value-column').fill('TotalKWhSold');
  const response=page.waitForResponse(r=>r.url().endsWith('query-settings-api.php')&&JSON.parse(r.request().postData()).action==='inspect-result');await page.locator('#sql-test').click();const result=await response;assert.equal(result.status(),200);const data=await result.json();await page.waitForFunction(()=>!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));
  assert.equal(data.month,'202607');assert.equal(data.truncated,false);
  const required=['BillMonth','AreaID','AreaName','SACode','Sales_KWH','KWH_Diff','Cancelled_KWH','Cancelled_KWH_2','Total_Cancelled_KWH','TotalKWhSold'];assert.deepEqual(data.columns,required);
  for(const col of required)assert.equal(await page.locator('#sql-auto-column option').filter({hasText:new RegExp('^'+col+'$')}).count(),1);
  for(const col of ['Sales_KWH','KWH_Diff','Cancelled_KWH','Cancelled_KWH_2','Total_Cancelled_KWH','TotalKWhSold'])assert.equal(await page.locator('#sql-column-tokens button').filter({hasText:new RegExp('^'+col+'$')}).count(),1);
  assert.equal(await page.locator('#sql-formula-preset option:disabled').count(),0);assert.deepEqual(await page.evaluate(()=>window.genstatReport.snapshot()),before);assert.deepEqual(errors,[]);assert.equal(checksum(),sourceBefore);
  console.log('PASS: July-bound read-only ten-column fixture populates mapping options, numeric formula tokens and presets. Test Query leaves report values/formulas/revision and workbook checksum unchanged. No configuration, draft or workbook saved.');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
