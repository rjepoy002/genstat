'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const assert=require('assert');
const expected=[19,34,37,46,47];
(async()=>{
 const browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
 const page=await browser.newPage();const errors=[];page.on('pageerror',e=>errors.push(e.message));
 try{
  for(const [width,height] of [[1920,1080],[1600,900],[1366,768],[1280,720],[390,844]]){
   await page.setViewportSize({width,height});await page.goto(process.env.GENSTAT_TEST_COMPACT_URL||'http://localhost/genstat/');await page.waitForFunction(()=>document.querySelector('table').dataset.scale);
   assert.deepEqual(await page.locator('tr[hidden]').evaluateAll(rows=>rows.map(r=>Number(r.dataset.excelRow))),expected);
   for(const row of expected){assert(!(await page.locator(`tr[data-excel-row="${row}"]`).isVisible()));assert(await page.locator(`tr[data-excel-row="${row}"] [data-classification="formula"]`).count()>0);}
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
  assert.deepEqual(errors,[]);console.log('PASS: five exact hidden rows, model metadata, desktop fit, mobile readability, unclipped numbers and aligned sticky cells');
 }finally{await browser.close();}
})().catch(e=>{console.error(e);process.exit(1);});
