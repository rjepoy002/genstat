'use strict';
const fs=require('fs'),os=require('os'),path=require('path'),assert=require('assert');
const {spawn}=require('child_process');
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const root=path.resolve('.'),fixture=fs.mkdtempSync(path.join(os.tmpdir(),'genstat-headers-')),php='C:\\xampp\\php\\php.exe';
const workbook=fs.readFileSync('excel-files/active/genstat_2026.xlsx');
const drafts=new Map(fs.readdirSync('storage').filter(n=>/^draft-.*\.json$/.test(n)).map(n=>[n,fs.readFileSync(path.join('storage',n))]));
for(const dir of ['lib','tools','config','assets'])fs.cpSync(dir,path.join(fixture,dir),{recursive:true});
for(const file of ['index.php','api.php','genstat.xlsx'])fs.copyFileSync(file,path.join(fixture,file));
for(const dir of ['storage','excel-files/active','excel-files/incoming','excel-files/backups','excel-files/archive'])fs.mkdirSync(path.join(fixture,dir),{recursive:true});
fs.writeFileSync(path.join(fixture,'excel-files/active/genstat_2026.xlsx'),workbook);for(const [name,bytes] of drafts)fs.writeFileSync(path.join(fixture,'storage',name),bytes);
fs.copyFileSync('storage/template-auto.json',path.join(fixture,'storage/template-auto.json'));
const port=22000+Math.floor(Math.random()*1000),base='http://127.0.0.1:'+port+'/';
const server=spawn(php,['-S','127.0.0.1:'+port,'-t',fixture],{windowsHide:true,stdio:'ignore'});
(async()=>{let browser;try{
 for(let i=0;i<50;i++){try{await fetch(base);break;}catch{await new Promise(r=>setTimeout(r,100));}}
 browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
 let total=0;
 for(const profile of [{name:'desktop',viewport:{width:1100,height:760}},{name:'mobile',viewport:{width:390,height:844},isMobile:true,hasTouch:true,deviceScaleFactor:2}]){
  const context=await browser.newContext(profile),page=await context.newPage(),errors=[],writes=[];page.on('pageerror',e=>errors.push(e.message));page.on('request',r=>{if(r.method()==='POST')writes.push(r.url());});
  const paint=()=>page.evaluate(()=>new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve))));
  async function check(){await paint();const failures=await page.evaluate(()=>{
   const v=document.getElementById('viewport'),s=document.getElementById('sheet'),z=Number(document.getElementById('zoom').value),box=v.getBoundingClientRect(),x0=box.left+v.clientLeft,y0=box.top+v.clientTop,corner=document.getElementById('sheet-corner').getBoundingClientRect(),bad=[];
   const near=(a,b,message)=>{if(Math.abs(a-b)>1)bad.push(message+': '+a+' vs '+b);};
   near(corner.left,x0,'corner x');near(corner.top,y0,'corner y');near(corner.width,36*z,'corner width');near(corner.height,24*z,'corner height');
   const seenCols=new Set(),seenRows=new Set();
   for(const h of s.querySelectorAll('.col-heading')){const r=h.getBoundingClientRect(),n=h.dataset.column;if(seenCols.has(n))bad.push('duplicate column '+n);seenCols.add(n);near(r.top,y0,'column pinned '+n);near(r.left,x0+parseFloat(h.style.left)*z-v.scrollLeft,'column position '+n);near(r.width,parseFloat(h.style.width)*z,'column width '+n);if(r.right>x0+36*z+2&&r.left<x0+v.clientWidth-2){const x=Math.max(x0+36*z+2,r.left+2),hit=document.elementFromPoint(x,y0+12*z);if(!hit?.closest('.col-heading'))bad.push('column covered '+n);}}
   for(const h of s.querySelectorAll('.row-heading')){const r=h.getBoundingClientRect(),n=h.dataset.row;if(seenRows.has(n))bad.push('duplicate row '+n);seenRows.add(n);near(r.left,x0,'row pinned '+n);near(r.top,y0+parseFloat(h.style.top)*z-v.scrollTop,'row position '+n);near(r.height,parseFloat(h.style.height)*z,'row height '+n);if(r.bottom>y0+24*z+2&&r.top<y0+v.clientHeight-2){const y=Math.max(y0+24*z+2,r.top+2),hit=document.elementFromPoint(x0+18*z,y);if(!hit?.closest('.row-heading'))bad.push('row covered '+n);}}
   if(!document.elementFromPoint(x0+18*z,y0+12*z)?.closest('#sheet-corner'))bad.push('corner covered');
   for(const cell of s.querySelectorAll('.cell')){const col=s.querySelector('.col-heading[data-column="'+cell.getAttribute('aria-colindex')+'"]'),row=s.querySelector('.row-heading[data-row="'+cell.getAttribute('aria-rowindex')+'"]');if(!col||!row){bad.push('hidden row/column cell was rendered '+cell.dataset.ref);continue;}const r=cell.getBoundingClientRect();near(r.left,col.getBoundingClientRect().left,'cell column '+cell.dataset.ref);near(r.top,row.getBoundingClientRect().top,'cell row '+cell.dataset.ref);near(r.width,parseFloat(cell.style.width)*z,'cell/merged width '+cell.dataset.ref);near(r.height,parseFloat(cell.style.height)*z,'cell/merged height '+cell.dataset.ref);}
   if(s.querySelectorAll('.corner').length!==1)bad.push('duplicate corner');return bad.slice(0,12);
  });assert.deepEqual(failures,[]);total++;}
  for(const sheet of ['Jun26','Jan26','RecovBill26']){
   await page.goto(base+'?sheet='+sheet);await page.waitForFunction(()=>document.querySelector('.editable input')&&!document.querySelector('.editable input').disabled,{},{timeout:60000});
   await page.evaluate(()=>{document.querySelector('.cell').classList.add('formula-selected');const layer=document.querySelector('.reference-highlights'),highlight=document.createElement('div');highlight.className='reference-highlight';highlight.style.cssText='left:0;top:0;width:100%;height:100%;background:rgba(220,20,20,.2)';layer.append(highlight);});
   const geometry=await page.locator('.cell').evaluateAll(cells=>cells.map(c=>[c.dataset.ref,c.style.left,c.style.top,c.style.width,c.style.height]));
   for(const zoom of ['0.5','0.7','1','1.5']){
    await page.locator('#zoom-menu summary').click();await page.selectOption('#zoom',zoom);
    for(const [x,y] of [[0,0],[0,320],[450,0],[450,320],[100000,100000]]){await page.locator('#viewport').evaluate((v,p)=>{v.scrollLeft=p[0];v.scrollTop=p[1];},[x,y]);await check();}
   }
   await page.locator('#zoom-menu summary').click();await page.selectOption('#zoom','1');await page.locator('#viewport').evaluate(v=>{v.scrollLeft=100;v.scrollTop=150;});await paint();
   const box=await page.locator('#viewport').boundingBox();await page.mouse.move(box.x+130,box.y+110);await page.keyboard.down('Control');await page.mouse.wheel(0,-120);await page.keyboard.up('Control');await page.waitForFunction(()=>document.getElementById('zoom').value==='1.1');await check();
   assert.deepEqual(await page.locator('.cell').evaluateAll(cells=>cells.map(c=>[c.dataset.ref,c.style.left,c.style.top,c.style.width,c.style.height])),geometry);
   console.log('PASS: '+profile.name+' '+sheet+' - vertical/horizontal/diagonal/end scrolling at 50/70/100/150%, Ctrl+wheel, header/cell alignment, geometry and overlay stacking');
  }
  // Back/forward cache lifecycle must reconnect the header scroll listener too.
  await page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pagehide')));await page.evaluate(()=>window.dispatchEvent(new PageTransitionEvent('pageshow',{persisted:true})));await page.locator('#viewport').evaluate(v=>{v.scrollTop=80;v.scrollLeft=50;});await check();
  assert.deepEqual(errors,[]);assert.deepEqual(writes,[]);await context.close();
 }
 assert(fs.readFileSync(path.join(fixture,'excel-files/active/genstat_2026.xlsx')).equals(workbook));assert(fs.readFileSync('excel-files/active/genstat_2026.xlsx').equals(workbook));for(const [name,bytes] of drafts){assert(fs.readFileSync(path.join(fixture,'storage',name)).equals(bytes));assert(fs.readFileSync(path.join(root,'storage',name)).equals(bytes));}
 console.log('PASS: '+total+' geometry/stacking checks; no duplicate headers, JavaScript errors or POST requests; workbook and drafts byte-for-byte unchanged');
}finally{if(browser)await browser.close();server.kill();await new Promise(r=>server.once('exit',r));const resolved=path.resolve(fixture);assert(resolved.startsWith(path.resolve(os.tmpdir())+path.sep)&&path.basename(resolved).startsWith('genstat-headers-'));fs.rmSync(resolved,{recursive:true,force:true});}})().catch(e=>{console.error(e);process.exitCode=1;});
