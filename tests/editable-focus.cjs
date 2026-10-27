'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const fs=require('fs'),path=require('path'),assert=require('assert'),{execFileSync}=require('child_process');
(async()=>{
 const draft=path.resolve('storage/draft-2026-06.json');
 if(fs.existsSync(draft))throw new Error('Existing draft: refusing to overwrite during tests.');
 const browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
 const page=await browser.newPage({viewport:{width:1440,height:1000}});let saved=false;const errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.goto('http://localhost/genstat/');await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('no saved draft'));
  const input=page.locator('input[data-ref="B11"]'),preview=page.locator('.cell[data-ref="B11"] .input-preview');
  assert.equal(await preview.textContent(),'26,992,328.74');assert(await preview.isVisible());assert.equal(await input.inputValue(),'26992328.74');
  console.log('PASS: Jun26!B11 normal viewing shows 26,992,328.74');
  await input.click();assert.equal(await input.inputValue(),'26992328.74');assert(!await preview.isVisible());assert.equal(await input.getAttribute('data-original'),'26992328.739999998');
  await page.locator('#open').click();await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('no saved draft'));assert(await preview.isVisible());
  console.log('PASS: click/edit/blur uses clean 26992328.74, retains raw source separately, and does not create unsaved changes');
  await page.click('#save');await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft saved'));saved=true;
  assert(!Object.prototype.hasOwnProperty.call(JSON.parse(fs.readFileSync(draft,'utf8')).values,'B11'));
  let wait=page.waitForEvent('download');await page.click('#export');let download=await wait;await download.saveAs('tests/focus-untouched.xlsx');
  process.stdout.write(execFileSync('C:\\xampp\\php\\php.exe',['tests/check-focus-export.php','tests/focus-untouched.xlsx','26992328.739999998'],{encoding:'utf8'}));
  console.log('PASS: focusing then saving/exporting never rounds or rewrites the untouched source value');
  await input.click();await input.fill('26992328.75');await page.locator('h1').click();assert.equal(await preview.textContent(),'26,992,328.75');assert.equal(await input.inputValue(),'26992328.75');
  await page.click('#save');await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft saved'));assert.equal(JSON.parse(fs.readFileSync(draft,'utf8')).values.B11,'26992328.75');
  await page.reload();await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft reopened'));
  assert.equal(await preview.textContent(),'26,992,328.75');await input.click();assert.equal(await input.inputValue(),'26992328.75');await page.locator('h1').click();
  console.log('PASS: changed numeric entry survives blur, save, reload and editing without binary artifacts');
  wait=page.waitForEvent('download');await page.click('#export');download=await wait;await download.saveAs('tests/focus-edited.xlsx');
  process.stdout.write(execFileSync('C:\\xampp\\php\\php.exe',['tests/check-focus-export.php','tests/focus-edited.xlsx','26992328.75'],{encoding:'utf8'}));
  assert.equal(await page.locator('.cell[data-ref="B12"] input').count(),0);assert.equal(await page.locator('.cell[data-ref="B12"]').getAttribute('aria-readonly'),'true');assert.deepEqual(errors,[]);
  console.log('PASS: formula cells remain protected and no browser errors occur');
 }finally{
  await browser.close();if(saved&&fs.existsSync(draft))fs.unlinkSync(draft);if(saved&&fs.existsSync(draft+'.lock'))fs.unlinkSync(draft+'.lock');
 }
})().catch(e=>{console.error(e);process.exitCode=1;});
