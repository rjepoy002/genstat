'use strict';
const fs=require('fs'),os=require('os'),path=require('path'),assert=require('assert'),crypto=require('crypto');
const {spawn,execFileSync}=require('child_process');
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const root=path.resolve('.'),php='C:\\xampp\\php\\php.exe',fixture=fs.mkdtempSync(path.join(os.tmpdir(),'genstat-history-'));
const liveDraft=fs.readFileSync('storage/draft-2026-06.json'),liveWorkbook=fs.readFileSync('excel-files/active/genstat_2026.xlsx');
for(const dir of ['lib','tools','config','assets'])fs.cpSync(dir,path.join(fixture,dir),{recursive:true});
for(const file of ['index.php','api.php','genstat.xlsx'])fs.copyFileSync(file,path.join(fixture,file));
for(const dir of ['storage','excel-files/active','excel-files/incoming','excel-files/backups','excel-files/archive'])fs.mkdirSync(path.join(fixture,dir),{recursive:true});
const active=path.join(fixture,'excel-files/active/genstat_2026.xlsx'),draft=path.join(fixture,'storage/draft-2026-06.json'),cacheFile=path.join(fixture,'storage/template-auto.json');
fs.writeFileSync(active,liveWorkbook);fs.copyFileSync('storage/template-auto.json',cacheFile);
const hash=crypto.createHash('sha256').update(liveWorkbook).digest('hex'),checkpoint=path.join(fixture,'storage/workbook-sync.json');
fs.writeFileSync(checkpoint,JSON.stringify({sha256:hash,synchronizedAt:'2026-10-27T00:00:00Z'}));
fs.writeFileSync(draft,JSON.stringify({period:'2026-06',sheet:'Jun26',templateHash:hash,values:{},formulas:{},revision:7,sourceCells:{},formulaAudit:[],savedAt:null}));
const monthly=path.join(fixture,'storage/draft-2026-01--'+crypto.createHash('sha256').update('Jan26').digest('hex').slice(0,16)+'.json');
fs.writeFileSync(monthly,JSON.stringify({period:'2026-01',sheet:'Jan26',templateHash:hash,values:{AG99:'month-preserved'},formulas:{},sourceCells:{AG99:{value:'',formula:null,attributes:null}},revision:2,savedAt:'2026-10-27T00:01:00Z'}));
const monthBytes=fs.readFileSync(monthly),port=20000+Math.floor(Math.random()*1000),base='http://127.0.0.1:'+port+'/';
const server=spawn(php,['-S','127.0.0.1:'+port,'-t',fixture],{windowsHide:true,stdio:'ignore'});
const data=()=>JSON.parse(fs.readFileSync(draft));
function phpCode(code){return execFileSync(php,['-r',code],{cwd:fixture,encoding:'utf8',windowsHide:true});}
(async()=>{let browser;
try{
 for(let i=0;i<50;i++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,100));}}
 browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
 const page=await browser.newPage({viewport:{width:1400,height:1000}}),errors=[];page.on('pageerror',e=>errors.push(e.message));
 async function ready(){await page.goto(base);await page.waitForFunction(()=>!document.querySelector('input[data-ref="AG99"]').disabled,{},{timeout:60000});}
 async function settled(){await page.waitForFunction(()=>!document.querySelector('#save').disabled,{},{timeout:60000});}
 async function persisted(check){for(let i=0;i<600;i++){if(check(data())){await settled();return;}await page.waitForTimeout(100);}throw new Error('Draft persistence timed out: '+JSON.stringify(data()));}
 const input=page.locator('input[data-ref="AG99"]');await ready();
 assert(await page.locator('#formula-undo').isDisabled());assert(await page.locator('#cell-redo').isDisabled());
 await input.fill('first');await persisted(d=>d.values.AG99==='first');assert(await page.locator('#formula-undo').isEnabled());
 await input.focus();await page.keyboard.press('Control+z');await persisted(d=>!Object.prototype.hasOwnProperty.call(d.values,'AG99'));assert.equal(await input.inputValue(),'');
 assert(await page.locator('#cell-redo').isEnabled());await page.keyboard.press('Control+y');await persisted(d=>d.values.AG99==='first');assert.equal(await input.inputValue(),'first');
 console.log('PASS: ordinary Ctrl+Z/Ctrl+Y works after autosave and persists both restored and redone values');
 await input.fill('second');await persisted(d=>d.values.AG99==='second');await page.locator('#formula-undo').click();await persisted(d=>d.values.AG99==='first');await page.locator('#cell-redo').click();await persisted(d=>d.values.AG99==='second');
 await input.fill('not-yet-saved');await page.locator('#formula-undo').click();await persisted(d=>d.values.AG99==='second');await page.waitForTimeout(1500);assert.equal(data().values.AG99,'second');
 await input.fill('new-branch');await persisted(d=>d.values.AG99==='new-branch');assert(await page.locator('#cell-redo').isDisabled());
 console.log('PASS: icon Undo/Redo handles text and unsaved edits, cancels stale autosave, and invalidates redo after a new edit');
 const formula=page.locator('.cell[data-ref="Q11"]'),editor=page.locator('#formula-text'),formulaDialog=page.locator('#formula-unlock-dialog');
 await formula.dblclick();await page.locator('#formula-unlock-form [type="submit"]').click();await page.waitForFunction(()=>!document.querySelector('#formula-text').readOnly);await editor.fill('=ROUND(37/3,2)');await editor.press('Enter');await page.locator('#save').click();await persisted(d=>d.formulas.Q11==='=ROUND(37/3,2)');
 const savedFormula=fs.readFileSync(draft);await page.locator('#formula-undo').click();assert(await formulaDialog.evaluate(e=>e.open));await page.locator('#formula-unlock-cancel').click();assert(fs.readFileSync(draft).equals(savedFormula));
 await page.locator('#formula-undo').click();await page.locator('#formula-unlock-form [type="submit"]').click();await persisted(d=>!Object.prototype.hasOwnProperty.call(d.formulas,'Q11'));await page.locator('#cell-redo').click();assert(await formulaDialog.evaluate(e=>e.open));await page.locator('#formula-unlock-form [type="submit"]').click();await persisted(d=>d.formulas.Q11==='=ROUND(37/3,2)');assert(await editor.evaluate(e=>e.readOnly));
 console.log('PASS: saved formula Undo and Redo each require cell-specific confirmation; Cancel preserves the draft and history');
 await input.fill('concurrent-base');await persisted(d=>d.values.AG99==='concurrent-base');let other=data();other.revision++;other.values.AG99='other-window';other.savedAt='2026-10-27T04:00:00Z';fs.writeFileSync(draft,JSON.stringify(other));const concurrent=fs.readFileSync(draft);
 await page.locator('#formula-undo').click();await page.waitForFunction(()=>document.querySelector('#status').textContent.includes('Another window saved'),{},{timeout:60000});assert(fs.readFileSync(draft).equals(concurrent));assert.equal(await input.inputValue(),'concurrent-base');assert(fs.readFileSync(active).equals(liveWorkbook));
 console.log('PASS: Undo rejects concurrent draft revisions without overwriting newer saved data or the workbook');
 const resync=page.locator('#resync'),resyncDialog=page.locator('#resync-dialog'),confirm=page.locator('#resync-confirm');
 async function preview(){const response=page.waitForResponse(r=>r.url().includes('action=resync-preview'));await resync.click();await page.waitForFunction(()=>!document.querySelector('#resync-confirm').disabled,{},{timeout:60000});assert(await resyncDialog.evaluate(e=>e.open));return (await response).json();}
 const unauthorized=await page.evaluate(async()=>{const r=await fetch('api.php?action=resync&period=2026-06&sheet=Jun26',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content},body:'{}'});return r.status;});assert.equal(unauthorized,409);assert(fs.readFileSync(draft).equals(concurrent));
 console.log('PASS: server requires an explicit, version-bound Resync confirmation token');
 const oldCache=fs.readFileSync(cacheFile),oldBook=fs.readFileSync(active),oldCheckpoint=fs.readFileSync(checkpoint),backups=fs.readdirSync(path.join(fixture,'excel-files/backups'));
 await preview();assert((await page.locator('#resync-time').textContent()).includes('Last successful workbook synchronization'));assert((await page.locator('#resync-warning').textContent()).includes('newer changes'));await page.locator('#resync-cancel').click();
 assert(fs.readFileSync(draft).equals(concurrent));assert(fs.readFileSync(cacheFile).equals(oldCache));assert(fs.readFileSync(active).equals(oldBook));assert(fs.readFileSync(checkpoint).equals(oldCheckpoint));assert.deepEqual(fs.readdirSync(path.join(fixture,'excel-files/backups')),backups);assert.equal(await input.inputValue(),'concurrent-base');
 console.log('PASS: Resync displays the synchronization timestamp and newer-draft warning; Cancel changes no draft, workbook, cache, checkpoint or backup');
 await preview();other=data();other.revision++;other.values.AG99='newer-after-preview';fs.writeFileSync(draft,JSON.stringify(other));const newer=fs.readFileSync(draft);await confirm.click();await page.waitForFunction(()=>!document.querySelector('#resync-error').hidden,{},{timeout:60000});assert((await page.locator('#resync-error').textContent()).includes('changed after confirmation'));assert(fs.readFileSync(draft).equals(newer));assert(fs.readFileSync(active).equals(oldBook));await page.locator('#resync-cancel').click();
 console.log('PASS: a newer draft created after preview invalidates Resync confirmation without data loss');
 await preview();const sheetPath=JSON.parse(fs.readFileSync(cacheFile)).layout.sheets.find(s=>s.name==='Jun26').path;
 phpCode(`require 'lib/xlsx.php';$file='excel-files/active/genstat_2026.xlsx';$z=zip_read($file);$path='${sheetPath}';$d=xml_doc(zip_text($z,$path));$x=xml_path($d);$x->query('//s:c[@r="B11"]/s:v')->item(0)->nodeValue='123.45';file_put_contents($file,zip_write($z,[$path=>$d->saveXML()]));`);
 const external=fs.readFileSync(active);await confirm.click();await page.waitForFunction(()=>!document.querySelector('#resync-error').hidden,{},{timeout:60000});assert(fs.readFileSync(draft).equals(newer));assert(fs.readFileSync(active).equals(external));await page.locator('#resync-cancel').click();
 console.log('PASS: external Excel changes after preview invalidate confirmation and are never overwritten');
 const accepted=await preview();await Promise.all([page.waitForNavigation({waitUntil:'load',timeout:60000}),confirm.click()]);await page.waitForFunction(()=>!document.querySelector('input[data-ref="AG99"]').disabled,{},{timeout:60000});assert.equal(await input.inputValue(),'');assert.equal(Object.keys(data().values).length+Object.keys(data().formulas).length,0);assert(fs.readFileSync(active).equals(external));assert(fs.readFileSync(monthly).equals(monthBytes));assert(fs.readFileSync(checkpoint).equals(oldCheckpoint));
 const dirs=fs.readdirSync(path.join(fixture,'storage/backups')).filter(n=>n.startsWith('resync-'));assert(dirs.some(n=>fs.readFileSync(path.join(fixture,'storage/backups',n,'draft.json')).equals(newer)));
 assert(fs.readdirSync(path.join(fixture,'excel-files/backups')).some(n=>n.startsWith('before-resync-')&&fs.readFileSync(path.join(fixture,'excel-files/backups',n)).equals(external)));assert(await page.locator('#formula-undo').isDisabled());assert(await page.locator('#cell-redo').isDisabled());
 console.log('PASS: confirmed Resync reloads the latest active Excel bytes, clears only the selected draft, preserves other worksheets and retains verified workbook/draft backups');
 const replay=await page.evaluate(async token=>{const r=await fetch('api.php?action=resync&period=2026-06&sheet=Jun26',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({token})});return r.status;},accepted.token);assert.equal(replay,409);
 await page.setViewportSize({width:390,height:844});for(const id of ['formula-undo','cell-redo']){const bounds=await page.locator('#'+id).boundingBox();assert(bounds.x>=0&&bounds.x+bounds.width<=390);}
 console.log('PASS: consumed confirmation cannot be replayed; icon Undo/Redo remains within the mobile editing toolbar');
 assert.deepEqual(errors,[]);assert(fs.readFileSync(path.join(root,'storage/draft-2026-06.json')).equals(liveDraft));assert(fs.readFileSync(path.join(root,'excel-files/active/genstat_2026.xlsx')).equals(liveWorkbook));console.log('PASS: no JavaScript errors; production workbook and draft remain untouched');
}finally{if(browser)await browser.close();server.kill();await new Promise(r=>server.once('exit',r));const resolved=path.resolve(fixture);assert(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('genstat-history-'));fs.rmSync(resolved,{recursive:true,force:true});}
})().catch(e=>{console.error(e);process.exitCode=1;});
