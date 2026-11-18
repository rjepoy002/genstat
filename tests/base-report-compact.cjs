'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const assert=require('assert');
const expected=[16,19,21,34,37,46,47];
(async()=>{
 const browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  await page.setViewportSize({width:1280,height:720});
  await page.goto(process.env.GENSTAT_TEST_COMPACT_URL||'http://localhost/genstat/');
  const toggle=page.locator('#total-group-toggle');
  const tableWidth=()=>page.locator('table').evaluate(table=>table.getBoundingClientRect().width / Number(table.dataset.scale));
  await page.waitForFunction(()=>document.querySelector('table').dataset.scale);
  assert.equal(await toggle.getAttribute('aria-expanded'),'false');
  assert(await toggle.isVisible());
  assert.equal(await toggle.innerText(),'');
  const actions=page.locator('.report-toolbar button');
  assert.deepEqual(await actions.evaluateAll(buttons=>buttons.map(button=>[button.id,button.dataset.tooltip,button.textContent.trim()])),[
   ['total-group-toggle','Show Totals',''],['report-export','Export Excel',''],['save','Save Draft',''],['sql-settings-open','Settings',''],
  ]);
  await page.locator('#report-month').focus();
  for(const [i,label] of ['Show Totals','Export Excel','Save Draft','Settings'].entries()){
   await page.keyboard.press('Tab');
   const tooltip=await actions.nth(i).evaluate(button=>({focused:button===document.activeElement,style:getComputedStyle(button,'::after').visibility,text:getComputedStyle(button,'::after').content}));
   assert.deepEqual(tooltip,{focused:true,style:'visible',text:JSON.stringify(label)});
  }
  await actions.nth(1).hover();
  assert.equal(await actions.nth(1).evaluate(button=>getComputedStyle(button,'::after').visibility),'visible');
  assert.equal(await page.locator('#status').count(),1);
  assert.equal(await page.locator('main + .report-notice #status').count(),1);
  const notes=page.locator('#report-notes');
  assert.equal(await notes.locator('#report-warning-count').textContent(),String(await page.evaluate(()=>JSON.parse(document.querySelector('#report-data').textContent).warnings.length)));
  await notes.locator('summary').click();
  assert(await notes.locator('#warnings').isVisible());
  assert.match(await notes.locator('.report-notes-content').textContent(),/Blue designated inputs/);
  await notes.locator('summary').click();
  assert.equal(await page.locator('table #total-group-toggle').count(),0);
  const [control,viewport]=await Promise.all([page.locator('.report-toolbar').boundingBox(),page.locator('.report-scroll').boundingBox()]);
  assert(control.y+control.height<=viewport.y&&Math.abs(control.x+control.width-viewport.x-viewport.width)<9);
  for(const ref of ['Q9','R9','S9','Q18','R18','S18'])assert(!(await page.locator(`[data-classification][data-ref="${ref}"]`).isVisible()));
  assert.deepEqual(await page.locator('col').evaluateAll(cols=>cols.filter(col=>getComputedStyle(col).display!=='none').map(col=>col.dataset.column)),[...'ABCDEFGHIJKLMNOP']);
  const trailingSpace=await page.locator('table').evaluate(table=>table.getBoundingClientRect().right-table.querySelector('td[data-ref="P18"]').getBoundingClientRect().right);
  assert(Math.abs(trailingSpace)<1,'Rizal is flush with the collapsed table edge');
  const collapsedWidth=await tableWidth();
  const total=await page.locator('[data-ref="Q18"] .value').textContent();
  const requests=[];
  const trackRequest=request=>requests.push(request.url());
  page.on('request',trackRequest);
  await toggle.focus();await page.keyboard.press('Enter');
  assert.equal(await toggle.getAttribute('aria-expanded'),'true');
  assert.equal(await toggle.getAttribute('aria-label'),'Hide Totals');
  assert.equal(await toggle.getAttribute('data-tooltip'),'Hide Totals');
  assert.equal(await page.locator('th[data-ref="Q8"]').getAttribute('colspan'),'2');
  assert(await tableWidth()>collapsedWidth+100);
  assert.equal(await page.locator('[data-ref="Q18"] .value').textContent(),total);
  for(const ref of ['Q9','R9','S9'])assert(await page.locator(`[data-ref="${ref}"]`).isVisible());
  assert.deepEqual(requests,[],'Toggle does not request report data');
  page.off('request',trackRequest);
  await page.reload();assert.equal(await toggle.getAttribute('aria-expanded'),'true');
  await toggle.focus();await page.keyboard.press('Space');
  await page.reload();assert.equal(await toggle.getAttribute('aria-expanded'),'false');
  await page.setViewportSize({width:390,height:844});
  const mobileControl=await toggle.boundingBox();
  assert(mobileControl.x>=0&&mobileControl.x+mobileControl.width<=390);
  await page.locator('.report-scroll').evaluate(viewport=>{viewport.scrollLeft=viewport.scrollWidth;});
  assert.deepEqual(await toggle.boundingBox(),mobileControl);
  await page.setViewportSize({width:1280,height:720});
  await toggle.click();
  for(const [width,height] of [[1920,1080],[1600,900],[1366,768],[1280,720],[390,844]]){
   await page.setViewportSize({width,height});await page.goto(process.env.GENSTAT_TEST_COMPACT_URL||'http://localhost/genstat/');await page.waitForFunction(()=>document.querySelector('table').dataset.scale);
   assert.deepEqual(await page.locator('tr[hidden]').evaluateAll(rows=>rows.map(r=>Number(r.dataset.excelRow))),expected);
   for(const row of expected){assert(!(await page.locator(`tr[data-excel-row="${row}"]`).isVisible()));assert(await page.locator(`tr[data-excel-row="${row}"] [data-classification="formula"]`).count()>0);}
   assert(await page.locator('tr[data-excel-row="25"]').isVisible());
   assert.equal(await page.locator('[data-classification="formula"]').count(),312);assert.equal(await page.locator('[data-classification="database_input"]').count(),96);
   const metrics=await page.evaluate(()=>{
    const viewport=document.querySelector('.report-scroll'),table=document.querySelector('table'),scale=Number(table.dataset.scale);
    const clipped=[];for(const cell of table.querySelectorAll('tbody tr:not([hidden]):not(.spacer-row) td:not(.particulars)')){const span=cell.querySelector('.value');if(span.scrollWidth>span.clientWidth+1)clipped.push(cell.dataset.ref);}
    return {scale,font:parseFloat(getComputedStyle(table).fontSize)*scale,tableHeight:table.getBoundingClientRect().height,available:viewport.clientHeight,scrollHeight:viewport.scrollHeight,scrollWidth:viewport.scrollWidth,width:viewport.clientWidth,documentWidth:document.documentElement.scrollWidth,clipped};
   });
   console.log(`${width}x${height}: ${JSON.stringify(metrics)}`);
   assert(metrics.font>=9.99);assert.equal(metrics.documentWidth,width);assert.deepEqual(metrics.clipped,[]);
   if(width>=1280)assert(metrics.scrollHeight<=metrics.available+1,'Visible rows must fit vertically');
   const before=await page.locator('[data-ref="B8"]').evaluate(e=>e.getBoundingClientRect().top);
   await page.locator('.report-scroll').evaluate(e=>{e.scrollLeft=e.scrollWidth;e.scrollTop=e.scrollHeight;});
   const pinned=await page.evaluate(()=>{const viewport=document.querySelector('.report-scroll').getBoundingClientRect(),a=document.querySelector('[data-ref="A30"]').getBoundingClientRect(),b=document.querySelector('[data-ref="B8"]').getBoundingClientRect(),header2=document.querySelector('[data-ref="S9"]').getBoundingClientRect(),header1=document.querySelector('[data-ref="S8"]').getBoundingClientRect();return {left:a.left,viewportLeft:viewport.left,top:b.top,header2Top:header2.top,header1Bottom:header1.bottom,right:document.querySelector('[data-ref="S44"]').getBoundingClientRect().right,viewportRight:viewport.right};});
   assert(Math.abs(pinned.left-pinned.viewportLeft)<3,'Particulars sticky');assert(Math.abs(before-pinned.top)<2,'Headers sticky');assert(Math.abs(pinned.header2Top-pinned.header1Bottom)<2,'Two header rows aligned');assert(pinned.right<=pinned.viewportRight,'Final comparison accessible');
   await page.locator('.report-scroll').evaluate(e=>{e.scrollLeft=0;e.scrollTop=0;});
   await page.screenshot({path:`tests/base-report-compact-${width}.png`});
  }
  await page.setViewportSize({width:1600,height:900});
  await page.waitForFunction(()=>Number(document.querySelector('table').dataset.scale)<1);
  const resized=await page.evaluate(()=>({scale:Number(document.querySelector('table').dataset.scale),height:document.querySelector('.report-scroll').scrollHeight,available:document.querySelector('.report-scroll').clientHeight}));
  assert(resized.height<=resized.available+1);console.log('PASS: resizing the loaded mobile view recalculates desktop fit');
  const blockedStorage=await browser.newPage();
  await blockedStorage.addInitScript(()=>Object.defineProperty(window,'localStorage',{get(){throw new Error('Storage unavailable');}}));
  await blockedStorage.goto(process.env.GENSTAT_TEST_COMPACT_URL||'http://localhost/genstat/');
  const blockedToggle=blockedStorage.locator('#total-group-toggle');
  assert.equal(await blockedToggle.getAttribute('aria-expanded'),'false');
  await blockedToggle.click();
  assert.equal(await blockedToggle.getAttribute('aria-expanded'),'true');
  await blockedStorage.reload();
  assert.equal(await blockedToggle.getAttribute('aria-expanded'),'false');
  await blockedStorage.close();
  assert.deepEqual(errors,[]);console.log('PASS: standalone ADJ rows hidden, Adjustment visible, model metadata, desktop fit, mobile readability, unclipped numbers and aligned sticky cells');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
