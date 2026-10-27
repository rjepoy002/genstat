'use strict';
const fs=require('fs'),os=require('os'),path=require('path'),assert=require('assert'),crypto=require('crypto');
const {spawn,execFileSync}=require('child_process');
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const php='C:\\xampp\\php\\php.exe',root=path.resolve('.'),fixture=fs.mkdtempSync(path.join(os.tmpdir(),'genstat-editing-'));
const liveDraft=fs.readFileSync('storage/draft-2026-06.json'),liveWorkbook=fs.readFileSync('excel-files/active/genstat_2026.xlsx');
for(const dir of ['lib','tools','config','assets'])fs.cpSync(path.join(root,dir),path.join(fixture,dir),{recursive:true});
for(const f of ['index.php','api.php','genstat.xlsx'])fs.copyFileSync(f,path.join(fixture,f));
for(const dir of ['storage','excel-files/active','excel-files/incoming','excel-files/backups','excel-files/archive'])fs.mkdirSync(path.join(fixture,dir),{recursive:true});
fs.writeFileSync(path.join(fixture,'storage/draft-2026-06.json'),liveDraft);fs.copyFileSync('storage/template-auto.json',path.join(fixture,'storage/template-auto.json'));fs.writeFileSync(path.join(fixture,'excel-files/active/genstat_2026.xlsx'),liveWorkbook);
const active=path.join(fixture,'excel-files/active/genstat_2026.xlsx'),draft=path.join(fixture,'storage/draft-2026-06.json');
const port=19000+Math.floor(Math.random()*1000),base='http://127.0.0.1:'+port+'/';
const server=spawn(php,['-S','127.0.0.1:'+port,'-t',fixture],{windowsHide:true,stdio:'ignore'});
function phpCode(code){return execFileSync(php,['-r',code],{cwd:fixture,encoding:'utf8',windowsHide:true});}
(async()=>{let browser;
try{
 for(let i=0;i<50;i++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,100));}}
 browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
 const page=await browser.newPage({viewport:{width:1400,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 async function ready(url=base){await page.goto(url);await page.waitForFunction(()=>document.querySelector('input[data-ref="AG99"]')&&!document.querySelector('input[data-ref="AG99"]').disabled,{},{timeout:60000});}
 await ready();assert(fs.readFileSync(draft).equals(liveDraft));
 assert(await page.locator('input[data-ref="AG99"]').isEnabled());assert(await page.locator('input[data-ref="A1"]').isEnabled());assert(await page.locator('input[data-ref="B11"]').isEnabled());
 console.log('PASS: empty, label and manual-value cells edit independently of locked Excel styles; opening preserves exact existing draft bytes');
 // Replace the active workbook with changed geometry and a formula whose cached value looks numeric.
 phpCode(`require 'lib/app.php';$l=layout();$file=workbook_path(ROOT);$z=zip_read($file);$path=report_sheet()['path'];$d=xml_doc(zip_text($z,$path));$x=xml_path($d);$x->query('//s:cols/s:col')->item(0)->setAttribute('width','20');$c=$x->query('//s:c[@r="B11"]')->item(0);foreach(iterator_to_array($c->childNodes) as $n)if($n instanceof DOMElement&&$n->localName==='v')$c->removeChild($n);$f=$d->createElementNS($d->documentElement->namespaceURI,'f','1+2');$c->appendChild($f);$c->appendChild($d->createElementNS($d->documentElement->namespaceURI,'v','3'));atomic_file_write($file,zip_write($z,[$path=>$d->saveXML()]));`);
 // B11 is not overridden in the preserved user draft; its new formula must remain protected.
 await ready();assert(await page.locator('.cell[data-ref="B11"]').evaluate(e=>e.classList.contains('formula')));assert(await page.locator('input[data-ref="AG99"]').isEnabled());assert(fs.readFileSync(draft).equals(liveDraft));
 console.log('PASS: workbook replacement refreshes geometry and detects an underlying formula despite its numeric cached value; draft bytes unchanged');
 const formula=page.locator('.cell[data-ref="Q11"]'),editor=page.locator('#formula-text'),dialog=page.locator('#formula-unlock-dialog');
 await formula.click();const original=await editor.inputValue();await formula.dblclick();assert(await dialog.evaluate(e=>e.open));assert.equal(await page.locator('#formula-unlock-title').textContent(),'Formula Protected');assert((await dialog.textContent()).includes('This cell contains a formula. Editing it may affect other calculations. Do you wish to unlock and edit this cell?'));
 await page.locator('#formula-unlock-cancel').click();assert.equal(await editor.inputValue(),original);assert(await editor.evaluate(e=>e.readOnly));
 console.log('PASS: formula edit attempt shows exact confirmation text; Cancel preserves original formula and protection');
 async function unlock(){await formula.dblclick();await page.locator('#formula-unlock-form button[type="submit"]').click();await page.waitForFunction(()=>!document.querySelector('#formula-text').readOnly);}
 await unlock();assert.equal(await editor.inputValue(),original);await editor.fill('=B11+2');await editor.press('Escape');assert(await editor.evaluate(e=>e.readOnly));assert.equal(await formula.evaluate(e=>e.classList.contains('pending-change')),false);
 await unlock();await editor.fill('=B11+2');await editor.press('Enter');assert(await editor.evaluate(e=>e.readOnly));assert(await formula.evaluate(e=>e.classList.contains('pending-change')));assert(fs.readFileSync(draft).equals(liveDraft));
 const forged=await page.evaluate(async revision=>{const r=await fetch('api.php?action=save&period=2026-06&sheet=Jun26',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content,'X-Template-Hash':document.querySelector('meta[name="template-hash"]').content,'X-Layout-Hash':document.querySelector('meta[name="layout-hash"]').content},body:JSON.stringify({values:{},formulas:{Q12:'=1'},revision})});return r.status;},JSON.parse(fs.readFileSync(draft)).revision);assert.equal(forged,403);assert(fs.readFileSync(draft).equals(liveDraft));
 console.log('PASS: server rejects a formula change to an unconfirmed second cell');
 await page.locator('.cell[data-ref="Q12"]').click();assert(await editor.evaluate(e=>e.readOnly));await page.locator('#formula-undo').click();assert.equal(await formula.evaluate(e=>e.classList.contains('pending-change')),false);
 console.log('PASS: unlock is cell-specific, Escape cancels buffer, Enter marks a pending change and relocks, Undo cancels before save');
 await unlock();await editor.fill('=ROUND(37/3,2)');await editor.press('Enter');
 const beforeSave=fs.readFileSync(active);await page.locator('#save').click();await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('Draft saved'),{},{timeout:60000});assert.equal(JSON.parse(fs.readFileSync(draft)).formulas.Q11,'=ROUND(37/3,2)');assert(fs.readFileSync(active).equals(beforeSave));
 await ready();await formula.click();assert.equal(await editor.inputValue(),'=ROUND(37/3,2)');assert(await editor.evaluate(e=>e.readOnly));assert(await page.locator('input[data-ref="AG99"]').isEnabled());
 console.log('PASS: confirmed formula change persists in draft without replacing source workbook before sync; reload preserves editing and relocks formulas');
 // Save a label and blank; autosave must preserve formula overlays.
 await page.locator('input[data-ref="AG99"]').fill('notes & <text>');await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('Draft saved'),{},{timeout:60000});assert.equal(JSON.parse(fs.readFileSync(draft)).values.AG99,'notes & <text>');
 await page.locator('input[data-ref="A1"]').fill('GENSTAT label changed');await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('Draft saved'),{},{timeout:60000});assert.equal(JSON.parse(fs.readFileSync(draft)).values.A1,'GENSTAT label changed');
 console.log('PASS: text in empty cells and existing manual labels autosaves without discarding the saved formula');
 fs.writeFileSync(path.join(fixture,'before-sync.xlsx'),fs.readFileSync(active));await Promise.all([page.waitForNavigation({waitUntil:'load',timeout:60000}),page.locator('#sync-now').click()]);
 await page.waitForFunction(()=>!document.querySelector('input[data-ref="AG99"]').disabled,{},{timeout:60000});
 // Wait for sync to publish and reload, rather than racing the old DOM.
 for(let i=0;i<100&&fs.readFileSync(active).equals(beforeSave);i++)await page.waitForTimeout(100);
 assert(!fs.readFileSync(active).equals(beforeSave));await ready();
 const check=phpCode(`require 'lib/app.php';$l=layout();$a=zip_read('before-sync.xlsx');$b=zip_read(workbook_path(ROOT));$path=report_sheet()['path'];$x=xml_path(xml_doc(zip_text($a,$path)));$y=xml_path(xml_doc(zip_text($b,$path)));foreach($x->query('//s:c/s:f') as $f){$ref=$f->parentNode->getAttribute('r');if($ref==='Q11')continue;$next=$y->query('//s:c[@r="'.$ref.'"]/s:f')->item(0);if(!$next||$next->C14N()!==$f->C14N())throw new Exception('Unrelated formula changed: '.$ref);}foreach($a as $name=>$e)if(!in_array($name,[$path,'xl/workbook.xml','xl/calcChain.xml','xl/_rels/workbook.xml.rels','[Content_Types].xml'])&&zip_text($a,$name)!==zip_text($b,$name))throw new Exception('Unrelated workbook part changed: '.$name);if($y->evaluate('string(//s:c[@r="Q11"]/s:f)')!=='ROUND(37/3,2)')throw new Exception('Formula sync failed');echo 'PASS: synchronization writes the edited formula and text while preserving every unrelated formula expression, sheet, style and external-link part';`);console.log(check);
 // Replacement by a constant must be explicit; the displayed result must never be submitted automatically.
 await unlock();assert.equal(await editor.inputValue(),'=ROUND(37/3,2)');await editor.fill('42.125');await editor.press('Enter');await page.locator('#save').click();await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('Draft saved'),{},{timeout:60000});await Promise.all([page.waitForNavigation({waitUntil:'load',timeout:60000}),page.locator('#sync-now').click()]);await page.waitForFunction(()=>!document.querySelector('input[data-ref="AG99"]').disabled,{},{timeout:60000});assert(await page.locator('input[data-ref="Q11"]').isEnabled());assert.equal(await page.locator('input[data-ref="Q11"]').getAttribute('data-original'),'42.125');
 console.log('PASS: explicit formula-to-value replacement synchronizes and reloads as an editable non-formula cell');
 await page.locator('input[data-ref="AG97"]').fill('=SUM(1,2)');await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('Draft saved'),{},{timeout:60000});await ready();const draftFormulaInput=page.locator('input[data-ref="AG97"]');assert(await draftFormulaInput.evaluate(e=>e.readOnly));await draftFormulaInput.dblclick();assert(await dialog.evaluate(e=>e.open));await page.locator('#formula-unlock-cancel').click();
 console.log('PASS: newly created draft formulas are protected after saving and reloading, before Excel synchronization');
 await ready(base+'?sheet=Jan26');assert(await page.locator('input[data-ref="AG99"]').isEnabled());await page.locator('input[data-ref="AG99"]').fill('January draft');await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('Draft saved'),{},{timeout:60000});await ready(base+'?sheet=Jan26');assert.equal(await page.locator('input[data-ref="AG99"]').inputValue(),'January draft');assert.equal(JSON.parse(fs.readFileSync(draft)).values.AG99,'notes & <text>');
 console.log('PASS: monthly sheets edit and reload independent drafts without changing June entries');
 assert.deepEqual(errors,[]);assert(fs.readFileSync(path.join(root,'storage/draft-2026-06.json')).equals(liveDraft));assert(fs.readFileSync(path.join(root,'excel-files/active/genstat_2026.xlsx')).equals(liveWorkbook));console.log('PASS: no browser errors; production workbook and saved draft remain byte-for-byte intact');
}finally{if(browser)await browser.close();server.kill();await new Promise(r=>server.once('exit',r));const resolved=path.resolve(fixture);assert(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('genstat-editing-'));fs.rmSync(resolved,{recursive:true,force:true});}
})().catch(e=>{console.error(e);process.exitCode=1;});
