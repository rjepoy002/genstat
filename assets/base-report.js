'use strict';
(()=>{
 const data=JSON.parse(document.querySelector('#report-data').textContent),status=document.querySelector('#status'),bar=document.querySelector('#formula'),unlock=document.querySelector('#unlock');
 let selected=null,dirty=false,sequence=0,pending=Promise.resolve();const grants=new Set();
 const warn=(warnings,errors)=>{const list=document.querySelector('#warnings');list.replaceChildren();for(const w of warnings){const li=document.createElement('li');li.textContent=`${w.cell}: ${w.formula} — ${w.message}`;list.append(li);}for(const [ref,error] of Object.entries(errors)){const li=document.createElement('li');li.textContent=`${ref}: ${error}`;list.append(li);}if(!list.children.length){const li=document.createElement('li');li.textContent='No calculation warnings.';list.append(li);}};
 warn(data.warnings,data.errors);
 // Element-only zoom keeps native table layout and sticky cells in the same
 // coordinate system. Never zoom the browser or shrink desktop text below 10px.
 const table=document.querySelector('table'),viewport=document.querySelector('.report-scroll');
 let fitFrame=0;
 function fitReport(){
  fitFrame=0;
  const mobile=matchMedia('(max-width:700px)').matches;
  const previous=Number(table.dataset.scale)||1;
  const naturalHeight=table.getBoundingClientRect().height/previous;
  const naturalWidth=table.getBoundingClientRect().width/previous;
  const scale=mobile?1:Math.max(10/11,Math.min(1,(viewport.clientHeight-16)/naturalHeight,(viewport.clientWidth-2)/naturalWidth));
  table.style.zoom=String(scale);table.dataset.scale=String(scale);
  document.querySelector('#fit-status').textContent=mobile?'Readable mobile view':`Auto fit ${Math.round(scale*100)}%`;
 }
 function scheduleFit(){if(!fitFrame)fitFrame=requestAnimationFrame(fitReport);}
 new ResizeObserver(scheduleFit).observe(viewport);
 addEventListener('resize',scheduleFit);
 document.fonts.ready.then(scheduleFit);
 // Widen numeric columns when formatted content needs it; never clip numbers
 // to meet the initial-fit target. Re-evaluate after edited results arrive.
 const measure=document.createElement('canvas').getContext('2d');
 function sizeNumericColumns(){
  const fontSize=matchMedia('(max-width:700px)').matches?12:11;
  const cols=table.querySelectorAll('col');
  for(let c=1;c<cols.length;c++){
   let width=c>=16?126:94;
   for(const row of table.rows){if(row.hidden)continue;
    const cell=row.querySelector(`[data-ref="${String.fromCharCode(65+c)}${row.dataset.excelRow}"]`);
    if(!cell||cell.tagName==='TH')continue;
    measure.font=`${getComputedStyle(cell).fontWeight} ${fontSize}px Arial`;
    width=Math.max(width,Math.ceil(measure.measureText(cell.querySelector('.value').textContent).width)+14+(cell.dataset.sqlApplied?18:0));
   }
   cols[c].style.width=width+'px';
  }
  scheduleFit();
 }
 addEventListener('resize',sizeNumericColumns);sizeNumericColumns();
 data.sqlReplacements??={};
 function sqlIndicators(){for(const cell of table.querySelectorAll('[data-classification]')){cell.dataset.templateTitle??=cell.title;const record=data.sqlReplacements[cell.dataset.ref];if(record){cell.dataset.sqlApplied='true';cell.title=`${cell.dataset.ref} · SQL-applied value · Original template ${record.originalFormula}`;}else{delete cell.dataset.sqlApplied;cell.title=cell.dataset.templateTitle;}}}
 sqlIndicators();sizeNumericColumns();
 async function request(action,extra={}){const response=await fetch('base-report.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,csrf:data.csrf,hash:data.hash,revision:data.revision,values:data.values,formulas:data.formulas,sqlReplacements:data.sqlReplacements,...extra})});const result=await response.json();if(!response.ok||result.error)throw Error(result.error||'Request failed');return result;}
 function select(cell){selected?.classList.remove('selected');selected=cell;cell.classList.add('selected');const ref=cell.dataset.ref,m=data.metadata[ref],sql=data.sqlReplacements[ref];document.querySelector('#selection').textContent=ref;document.querySelector('#cell-kind').textContent=sql?'SQL-applied value · template formula preserved':m.classification;bar.value=sql?sql.value:(data.formulas[ref]??(m.effectiveFormula!==null?'='+m.effectiveFormula.replace(/^=/,''):data.values[ref]??m.originalValue));bar.readOnly=Boolean(sql)||!m.editable&&!grants.has(ref);unlock.disabled=Boolean(sql)||m.classification!=='formula'||grants.has(ref);document.querySelector('#restore-sql').disabled=!sql;}
 document.querySelector('table').addEventListener('focusin',e=>{const cell=e.target.closest('[data-classification]');if(cell)select(cell);});
 function recalculate(){const version=++sequence;dirty=true;status.textContent='Recalculating…';pending=pending.catch(()=>{}).then(async()=>{const result=await request('calculate');if(version!==sequence)return;for(const [ref,c] of Object.entries(result.cells)){const cell=document.querySelector(`[data-classification][data-ref="${ref}"]`);if(cell)cell.querySelector('.value').textContent=c.display;}warn(result.warnings,result.errors);sizeNumericColumns();status.textContent='Unsaved changes';}).catch(e=>{status.textContent=e.message;status.classList.add('error');});return pending;}
 document.querySelector('table').addEventListener('change',e=>{if(!e.target.matches('td input'))return;data.values[e.target.dataset.ref]=e.target.value;select(e.target.closest('td'));recalculate();});
 unlock.addEventListener('click',async()=>{if(!selected)return;const ref=selected.dataset.ref;if(!confirm(`This cell contains a formula. Editing ${ref} may affect other calculations. Unlock and edit this cell?`))return;try{await request('unlock',{ref});grants.add(ref);select(selected);bar.focus();}catch(e){status.textContent=e.message;}});
 bar.addEventListener('keydown',async e=>{if(e.key==='Escape'&&selected)select(selected);if(e.key!=='Enter'||bar.readOnly||!selected)return;const ref=selected.dataset.ref,m=data.metadata[ref];if(m.classification==='formula'){if(!bar.value.startsWith('=')){status.textContent='Enter an Excel formula beginning with =.';return;}data.formulas[ref]=bar.value;}else{data.values[ref]=bar.value;selected.querySelector('input').value=bar.value;}await recalculate();});
 document.querySelector('#save').addEventListener('click',async()=>{try{await pending;const result=await request('save');data.revision=result.revision;dirty=false;status.textContent='Draft saved';status.classList.remove('error');}catch(e){status.textContent=e.message;status.classList.add('error');}});
 // Reviewed SQL values enter the same live draft and Save Draft workflow.
 window.genstatReport={snapshot:()=>({csrf:data.csrf,hash:data.hash,revision:data.revision,values:structuredClone(data.values),formulas:structuredClone(data.formulas),sqlReplacements:structuredClone(data.sqlReplacements)}),
  applyReviewed(result){data.values=result.values??data.values;data.formulas=result.formulas??data.formulas;for(const ref of Object.keys(data.sqlReplacements))grants.delete(ref);data.sqlReplacements=result.sqlReplacements??data.sqlReplacements;for(const ref of Object.keys(data.sqlReplacements))grants.delete(ref);for(const [ref,c] of Object.entries(result.calculation.cells)){const cell=document.querySelector(`[data-classification][data-ref="${ref}"]`);if(!cell)continue;cell.querySelector('.value').textContent=c.display;const input=cell.querySelector('input');if(input)input.value=data.values[ref]??data.metadata[ref].originalValue;}warn(result.calculation.warnings,result.calculation.errors);dirty=true;sqlIndicators();sizeNumericColumns();if(selected)select(selected);status.textContent='Reviewed query values applied · Unsaved changes';},
  ready:()=>pending};
 document.querySelector('#restore-sql').addEventListener('click',async()=>{if(!selected||!data.sqlReplacements[selected.dataset.ref])return;if(!confirm(`Restore the original template formula in ${selected.dataset.ref}? The SQL-applied value will be removed from the working report.`))return;try{const result=await request('restore-sql',{ref:selected.dataset.ref});window.genstatReport.applyReviewed(result);status.textContent='Template formula restored · Unsaved changes';}catch(e){status.textContent=e.message;}});
 addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
})();
