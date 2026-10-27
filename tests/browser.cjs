'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const fs=require('fs'),path=require('path'),assert=require('assert');
(async()=>{
 const draft=path.resolve('storage/draft-2026-06.json');if(fs.existsSync(draft))throw new Error('Existing draft: refusing to overwrite during tests.');
 const browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
 const page=await browser.newPage({viewport:{width:1440,height:1000}});const errors=[];page.on('pageerror',e=>errors.push(e.message));
 let saved=false;
 try{
 await page.goto('http://localhost/genstat/');await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('no saved draft'));
 assert.equal(await page.locator('.editable input').count(),17);assert.equal(await page.locator('.formula input').count(),0);assert.equal(await page.locator('.cell[data-ref="A1"] input').count(),0);console.log('PASS: browser loads all inputs; formulas and labels have no editors');
 assert(await page.locator('input[data-ref="C39"]').isEnabled());assert.equal(await page.locator('input[data-ref="C39"]').inputValue(),'');assert(await page.locator('input[data-ref="B11"]').isEnabled());console.log('PASS: approved blank and populated cells are editable');
 const originalInput=page.locator('input[data-ref="B11"]'),originalPreview=page.locator('.cell[data-ref="B11"] .input-preview');
 assert.equal(await originalPreview.textContent(),'26,992,328.74');await originalInput.click();assert.equal(await originalInput.inputValue(),'26992328.74');assert(!await originalPreview.isVisible());
 await page.locator('#open').focus();assert(await originalPreview.isVisible());assert.equal(await originalPreview.textContent(),'26,992,328.74');assert.equal(await originalInput.inputValue(),'26992328.74');assert.equal(await originalInput.getAttribute('data-original'),'26992328.739999998');
 assert(!(await page.locator('#status').textContent()).includes('Unsaved'));console.log('PASS: focus shows clean editing text; blur preserves the separate raw source value without dirtying the input');
 for(const ref of ['B12','H12','B22']){
  const cell=page.locator(`.cell[data-ref="${ref}"]`);assert(await cell.getAttribute('class').then(c=>c.includes('formula')));assert.equal(await cell.getAttribute('aria-readonly'),'true');assert.equal(await cell.getAttribute('contenteditable'),'false');
  const before=await cell.textContent();await cell.focus();await page.keyboard.type('999');await page.keyboard.press('Delete');
  assert.equal(await cell.textContent(),before);
  const pasteBlocked=await cell.evaluate(el=>!el.dispatchEvent(new ClipboardEvent('paste',{bubbles:true,cancelable:true})));assert(pasteBlocked);
  assert((await cell.getAttribute('title')).includes('Read-only'));assert(await page.locator('#cell-info').getAttribute('title').then(t=>t.includes('Read-only formula')));
 }
 console.log('PASS: ordinary, empty shared follower and external-reference formulas reject typing, Delete and paste');
 assert.equal(await page.locator('#show-formula-protection').count(),0);
 const locked=page.locator('.cell[data-ref="H12"]');await locked.focus();assert((await locked.getAttribute('title')).includes('Read-only'));assert((await page.locator('#cell-info').getAttribute('title')).includes('Read-only'));assert.equal(await locked.getAttribute('aria-readonly'),'true');
 assert.equal(await locked.evaluate(el=>getComputedStyle(el,'::before').content),'"fx"');
 const beforeLocked=await locked.textContent();await page.keyboard.type('777');assert.equal(await locked.textContent(),beforeLocked);
 assert(await locked.evaluate(el=>!el.dispatchEvent(new ClipboardEvent('paste',{bubbles:true,cancelable:true}))));
 await page.evaluate(()=>localStorage.setItem('genstat.showFormulaProtection','false'));await page.reload();await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('no saved draft'));
 assert.equal(await locked.evaluate(el=>getComputedStyle(el,'::before').content),'"fx"');assert((await locked.getAttribute('title')).includes('Read-only'));assert(await page.locator('#formula-mode').isVisible());
 console.log('PASS: formula indicators are permanent; legacy hidden preference has no effect; locked formula rejects typing and paste');
 assert.equal(await page.locator('.col-heading').count(),33);assert.equal(await page.locator('.row-heading').count(),87);assert.equal(await page.locator('[aria-colspan]').count(),16);console.log('PASS: 33 columns, 87 visible rows (12 source-hidden), 16 merged regions rendered');
 assert(await page.locator('.cell[data-ref="AG99"]').count());assert(await page.locator('.cell[data-ref="Z10"]').count());console.log('PASS: full worksheet including off-print working area');
 const metrics=await page.evaluate(()=>{const el=document.querySelector('#viewport');return {horizontal:el.scrollWidth>el.clientWidth,vertical:el.scrollHeight>el.clientHeight,bg:getComputedStyle(document.querySelector('.editable')).backgroundColor};});assert(metrics.horizontal&&metrics.vertical);assert.equal(metrics.bg,'rgb(255, 243, 176)');console.log('PASS: horizontal/vertical scrolling and pale yellow inputs');
 await page.screenshot({path:'tests/worksheet.png',fullPage:true});
 await page.locator('input[data-ref="B11"]').fill('not a number');await page.click('#save');await page.waitForFunction(()=>document.querySelector('#status').classList.contains('error'));assert(!fs.existsSync(draft));console.log('PASS: browser numeric validation prevents save');
 await page.locator('input[data-ref="B11"]').fill('1,000.00');await page.click('#save');assert(!fs.existsSync(draft));console.log('PASS: formatted thousands separators are rejected as raw numeric input');
 await page.locator('input[data-ref="B11"]').fill('12345.67');await page.locator('input[data-ref="C39"]').fill('98.5');
 let unsavedPrompt=false;page.once('dialog',async dialog=>{unsavedPrompt=true;await dialog.dismiss();});await page.reload({timeout:2000}).catch(e=>{if(!unsavedPrompt)throw e;});assert(unsavedPrompt);console.log('PASS: browser warns before navigating away with unsaved changes');
 await page.click('#save');await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft saved'));saved=true;assert(fs.existsSync(draft));
 await page.reload();await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft reopened'));assert.equal(await page.locator('input[data-ref="B11"]').inputValue(),'12345.67');assert.equal(await page.locator('input[data-ref="C39"]').inputValue(),'98.5');console.log('PASS: JSON draft saved and restored after reload');
 const csrf=await page.locator('meta[name="csrf-token"]').getAttribute('content');
 const templateHash=await page.locator('meta[name="template-hash"]').getAttribute('content');
 const layoutHash=await page.locator('meta[name="layout-hash"]').getAttribute('content');
 await page.context().setExtraHTTPHeaders({'X-Template-Hash':templateHash,'X-Layout-Hash':layoutHash});
 for(const ref of ['B12','H12','B22']){const blocked=await page.request.post('http://localhost/genstat/api.php?action=save&period=2026-06',{headers:{'X-CSRF-Token':csrf},data:{values:{[ref]:'999'},revision:1}});assert.equal(blocked.status(),400);}
 assert.equal(JSON.parse(fs.readFileSync(draft,'utf8')).values.B11,'12345.67');console.log('PASS: save endpoint rejects ordinary, shared and external-reference formula writes');
 const stale=await page.request.post('http://localhost/genstat/api.php?action=save&period=2026-06',{headers:{'X-CSRF-Token':csrf,'X-Template-Hash':'obsolete'},data:{values:{B11:'999'},revision:1}});assert.equal(stale.status(),409);console.log('PASS: stale browser template version rejected');
 const staleLayout=await page.request.post('http://localhost/genstat/api.php?action=save&period=2026-06',{headers:{'X-CSRF-Token':csrf,'X-Layout-Hash':'obsolete'},data:{values:{B11:'999'},revision:1}});assert.equal(staleLayout.status(),409);console.log('PASS: stale browser input-rule/layout version rejected');
 let r=await page.request.post('http://localhost/genstat/api.php?action=save&period=2026-06',{headers:{'X-CSRF-Token':csrf},data:{values:{Q11:'2'},revision:1}});assert.equal(r.status(),400);
 r=await page.request.post('http://localhost/genstat/api.php?action=save&period=2026-06',{headers:{'X-CSRF-Token':csrf},data:{values:{B11:'Infinity'},revision:1}});assert.equal(r.status(),400);console.log('PASS: server rejects protected cells and invalid numbers');
 r=await page.request.post('http://localhost/genstat/api.php?action=save&period=2026-06',{data:{values:{B11:'2'},revision:1}});assert.equal(r.status(),400);console.log('PASS: writes require session CSRF token');
 r=await page.request.post('http://localhost/genstat/api.php?action=save&period=2026-06',{headers:{'X-CSRF-Token':csrf},data:{values:{B11:'2'},revision:0}});assert.equal(r.status(),409);console.log('PASS: stale draft revision rejected');
 const downloadPromise=page.waitForEvent('download');await page.click('#export');const download=await downloadPromise;await download.saveAs('tests/exported.xlsx');assert.equal(download.suggestedFilename(),'PALECO-GenStat-2026-06.xlsx');console.log('PASS: browser downloads XLSX from saved draft');
 for(const route of ['storage/draft-2026-06.json','config/layout.json','lib/app.php','tools/refresh-template.php']){r=await page.request.get('http://localhost/genstat/'+route);assert.equal(r.status(),403);}console.log('PASS: Apache denies direct draft/config/helper/refresh-tool access');
 await page.locator('#zoom-menu summary').click();await page.selectOption('#zoom','1');assert.equal(await page.locator('#sheet').evaluate(el=>getComputedStyle(el).transform),'matrix(1, 0, 0, 1, 0, 0)');console.log('PASS: zoom control updates worksheet geometry');
 for(const [ref,raw,formatted] of [['B11','1000','1,000.00'],['B11','0','0.00'],['B11','-1000.125','-1,000.13'],['B39','-1000.125','(1,000.13)'],['B39','0','-']]){
  const input=page.locator(`input[data-ref="${ref}"]`);await input.fill(raw);await page.locator('#save').focus();assert.equal(await page.locator(`.cell[data-ref="${ref}"] .input-preview`).textContent(),formatted);assert.equal(await input.inputValue(),raw);
 }
 const precise='12345.678901234567';await page.locator('input[data-ref="B11"]').fill(precise);await page.locator('#save').focus();assert.equal(await originalPreview.textContent(),'12,345.68');assert.equal(await originalInput.inputValue(),precise);
 await page.click('#save');await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft saved'));assert.equal(JSON.parse(fs.readFileSync(draft,'utf8')).values.B11,precise);
 await page.reload();await page.waitForFunction(()=>document.querySelector('#status').textContent.startsWith('Draft reopened'));assert.equal(await originalInput.inputValue(),precise);assert.equal(await originalPreview.textContent(),'12,345.68');console.log('PASS: formatted blur/reopen and JSON saving retain every digit of the raw numeric string');
 assert.deepEqual(errors,[]);console.log('PASS: no browser JavaScript errors');
 }finally{await browser.close();if(saved&&fs.existsSync(draft))fs.unlinkSync(draft);const lock=draft+'.lock';if(saved&&fs.existsSync(lock))fs.unlinkSync(lock);}
})().catch(e=>{console.error(e);process.exitCode=1;});
