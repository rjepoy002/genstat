'use strict';
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright'),fs=require('fs'),assert=require('assert');
(async()=>{const browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
try{const page=await browser.newPage({viewport:{width:1440,height:1000}});await page.goto(process.env.GENSTAT_TEST_COMPACT_URL||'http://localhost/genstat/');await page.locator('#sql-settings-open').click();await page.waitForFunction(()=>document.querySelector('#sql-row option')&&!document.querySelector('#sql-settings-dialog').hasAttribute('aria-busy'));await page.locator('#sql-row').selectOption('genstat-base/Jul26/A18',{force:true});

 const picker=page.getByRole('combobox',{name:'GENSTAT Report Row',exact:true});
 const options=page.getByRole('listbox',{name:'GENSTAT Report Row'});
 const selected=()=>page.locator('#sql-row').inputValue();
 const active=()=>picker.evaluate(el=>document.getElementById(el.getAttribute('aria-activedescendant'))?.textContent);
 const previous=await selected();
 await picker.press('End');
 assert.equal(await picker.getAttribute('aria-expanded'),'true');
 assert.equal(await active(),await options.getByRole('option').last().textContent());
 await picker.press('ArrowUp');
 assert.notEqual(await active(),await options.getByRole('option').last().textContent());
 await picker.press('ArrowDown');
 assert.equal(await active(),await options.getByRole('option').last().textContent());
 await picker.press('Escape');
 assert(await options.isHidden());
 assert.equal(await selected(),previous);
 await picker.press('Home');
 const first=await options.getByRole('option').first().textContent();
 assert.equal(await active(),first);
 await picker.press('Space');
 assert.equal(await page.locator('#sql-row-value').textContent(),first);
 await picker.press('Enter');
 assert(await options.isVisible());
 await picker.press('Tab');
 assert(await options.isHidden());
 assert.equal(await page.evaluate(()=>document.activeElement.id),'sql-history');
 await picker.click();
 await page.locator('#sql-settings-title').click();
 assert(await options.isHidden());
 await page.setViewportSize({width:390,height:500});
 await picker.click();
 const bounds=await options.boundingBox();
 assert(bounds.x>=0&&bounds.y>=0&&bounds.x+bounds.width<=390&&bounds.y+bounds.height<=500);
 await options.getByRole('option').last().click();
 assert(await options.isHidden());
 await page.setViewportSize({width:1440,height:1000});
 await page.locator('#sql-row').selectOption('genstat-base/Jul26/A18',{force:true});
 await page.evaluate(()=>{document.querySelector('#sql-preview').innerHTML='<table><thead><tr><th>Area</th><th>Value</th></tr></thead><tbody><tr><td>Style fixture only</td><td data-numeric="true">123.45</td></tr></tbody></table>';});
 const captures={};for(const width of [1440,820,390]){await page.setViewportSize({width,height:1000});await page.locator('#sql-editor').focus();await page.evaluate(()=>Promise.all(document.getAnimations().map(a=>a.finished)));
  captures[width]=await page.evaluate(()=>{const out={};for(const selector of ['#sql-settings-dialog','#sql-editor','#sql-calculation','.settings-label','.settings-input','#sql-test','#sql-apply','#sql-preview','#sql-preview table','#sql-preview tr','#sql-preview td','#sql-preview td[data-numeric]','#sql-preview th','#sql-bindings','.close-settings']){const el=document.querySelector(selector),style=getComputedStyle(el);out[selector]=Object.fromEntries(Array.from(style).map(k=>[k,style.getPropertyValue(k)]));}out.backdrop=getComputedStyle(document.querySelector('dialog'),'::backdrop').backgroundColor;document.querySelector('#sql-fields').disabled=true;out.loading=getComputedStyle(document.querySelector('#sql-fields')).opacity;document.querySelector('#sql-fields').disabled=false;return out;});
 }
 if(process.argv[2]==='--capture'){fs.writeFileSync(process.argv[3],JSON.stringify(captures));console.log('Captured desktop/tablet/mobile computed-style baseline.');}
 else if(process.argv[2]==='--compare'){const old=JSON.parse(fs.readFileSync(process.argv[3],'utf8')),diff=[];for(const width of Object.keys(old))for(const [selector,value] of Object.entries(old[width])){if(typeof value==='string'){if(value!==captures[width][selector])diff.push(`${width} ${selector}: ${value} -> ${captures[width][selector]}`);}else for(const [key,before] of Object.entries(value))if(before!==captures[width][selector][key])diff.push(`${width} ${selector} ${key}: ${before} -> ${captures[width][selector][key]}`);}assert.deepEqual(diff,[]);console.log('PASS: complete computed styles match the pre-refactor baseline on desktop/tablet/mobile, including backdrop, focus, loading, hidden/empty states and table alignment.');}
 else{assert.equal(captures[1440].backdrop,'rgba(15, 23, 42, 0.65)');assert.equal(captures[1440].loading,'0.65');assert.equal(captures[1440]['#sql-preview th'].position,'sticky');assert.equal(captures[1440]['#sql-preview td[data-numeric]']['text-align'],'right');console.log('PASS: SQL settings style contract across desktop/tablet/mobile.');}
}finally{await browser.close();}})().catch(e=>{console.error(e);process.exit(1);});
