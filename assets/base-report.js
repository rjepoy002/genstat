'use strict';
(()=>{
 const data=JSON.parse(document.querySelector('#report-data').textContent),status=document.querySelector('#status'),bar=document.querySelector('#formula'),unlock=document.querySelector('#unlock');
 let selected=null,dirty=false,sequence=0,pending=Promise.resolve(),loading=false,saving=false,exporting=false;const grants=new Set();
 data.preview={};
 const controlled=ref=>Object.hasOwn(data.total,ref);
 const inputValue=ref=>controlled(ref)?(data.total[ref]??''):data.values[ref]??data.preview[ref]??(data.month!=='202607'&&Number.isFinite(Number(data.metadata[ref].originalValue))?'':data.metadata[ref].originalValue);
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
 function sqlIndicators(){for(const cell of table.querySelectorAll('[data-classification]')){cell.dataset.templateTitle??=cell.title;const record=data.sqlReplacements[cell.dataset.ref];if(controlled(cell.dataset.ref)){cell.dataset.sqlApplied='true';cell.setAttribute('aria-readonly','true');cell.title=cell.dataset.ref+' · SQL-controlled Total KWH Sold';continue;}cell.setAttribute('aria-readonly',String(!data.metadata[cell.dataset.ref].editable));if(record){cell.dataset.sqlApplied='true';cell.title=`${cell.dataset.ref} · SQL-applied value · Original template ${record.originalFormula}`;}else{delete cell.dataset.sqlApplied;cell.title=cell.dataset.templateTitle;}}}
 sqlIndicators();sizeNumericColumns();
 async function request(action,extra={}){
  const response=await fetch('base-report.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,month:data.month,csrf:data.csrf,hash:data.hash,revision:data.revision,values:data.values,formulas:data.formulas,sqlReplacements:data.sqlReplacements,preview:data.preview,total:data.total,previewToken:data.previewToken,...extra})});
  if(action==='export'&&response.ok)return response.blob();
  const result=await response.json();if(!response.ok||result.error)throw Error(result.error||'Request failed');return result;
 }
 function renderCalculation(result,inputs=false){
  for(const [ref,c] of Object.entries(result.cells)){
   const cell=table.querySelector(`[data-classification][data-ref="${ref}"]`);if(!cell)continue;
   cell.querySelector('.value').textContent=c.display;
   const input=inputs&&cell.querySelector('input');if(input){input.value=inputValue(ref);input.readOnly=controlled(ref);}
  }
  warn(result.warnings,result.errors);sizeNumericColumns();
 }
 function select(cell){selected?.classList.remove('selected');selected=cell;cell.classList.add('selected');const ref=cell.dataset.ref,m=data.metadata[ref],sql=data.sqlReplacements[ref];document.querySelector('#selection').textContent=ref;document.querySelector('#cell-kind').textContent=controlled(ref)?'SQL-controlled value':sql?'SQL-applied value · template formula preserved':m.classification;bar.value=controlled(ref)?(data.total[ref]??'#N/A'):sql?sql.value:(data.formulas[ref]??(m.effectiveFormula!==null?'='+m.effectiveFormula.replace(/^=/,''):inputValue(ref)));bar.readOnly=controlled(ref)||Boolean(sql)||!m.editable&&!grants.has(ref);unlock.disabled=controlled(ref)||Boolean(sql)||m.classification!=='formula'||grants.has(ref);document.querySelector('#restore-sql').disabled=!sql;}
 document.querySelector('table').addEventListener('focusin',e=>{const cell=e.target.closest('[data-classification]');if(cell)select(cell);});
 function recalculate(){const version=++sequence;dirty=true;status.textContent='Recalculating…';pending=pending.catch(()=>{}).then(async()=>{const result=await request('calculate');if(version!==sequence)return;for(const [ref,c] of Object.entries(result.cells)){const cell=document.querySelector(`[data-classification][data-ref="${ref}"]`);if(cell)cell.querySelector('.value').textContent=c.display;}warn(result.warnings,result.errors);sizeNumericColumns();status.textContent='Unsaved changes';}).catch(e=>{status.textContent=e.message;status.classList.add('error');});return pending;}
 document.querySelector('table').addEventListener('change',e=>{if(!e.target.matches('td input'))return;data.values[e.target.dataset.ref]=e.target.value;select(e.target.closest('td'));recalculate();});
 unlock.addEventListener('click',async()=>{if(!selected)return;const ref=selected.dataset.ref,month=data.month;if(!confirm(`This cell contains a formula. Editing ${ref} may affect other calculations. Unlock and edit this cell?`))return;try{await request('unlock',{ref});if(loading||data.month!==month||!selected)return;grants.add(ref);select(selected);bar.focus();}catch(e){status.textContent=e.message;}});
 bar.addEventListener('keydown',async e=>{if(e.key==='Escape'&&selected)select(selected);if(e.key!=='Enter'||bar.readOnly||!selected)return;const ref=selected.dataset.ref,m=data.metadata[ref];if(m.classification==='formula'){if(!bar.value.startsWith('=')){status.textContent='Enter an Excel formula beginning with =.';return;}data.formulas[ref]=bar.value;}else{data.values[ref]=bar.value;selected.querySelector('input').value=bar.value;}await recalculate();});
 const saveButton=document.querySelector('#save'),exportButton=document.querySelector('#report-export');
 saveButton.addEventListener('click',async()=>{
  if(saving||loading||exporting)return;
  saving=true;saveButton.disabled=true;
  try{await pending;const result=await request('save');data.revision=result.revision;dirty=false;status.textContent='Draft saved';status.classList.remove('error');}
  catch(e){status.textContent=e.message;status.classList.add('error');}
  finally{saving=false;saveButton.disabled=false;}
 });
 // Reviewed SQL values enter the same live draft and Save Draft workflow.
 window.genstatReport={snapshot:()=>({month:data.month,csrf:data.csrf,hash:data.hash,revision:data.revision,values:structuredClone(data.values),formulas:structuredClone(data.formulas),sqlReplacements:structuredClone(data.sqlReplacements)}),
  applyReviewed(result){
   data.values=result.values??data.values;data.formulas=result.formulas??data.formulas;
   for(const ref of Object.keys(data.sqlReplacements))grants.delete(ref);
   data.sqlReplacements=result.sqlReplacements??data.sqlReplacements;
   for(const ref of Object.keys(data.sqlReplacements))grants.delete(ref);
   dirty=true;sqlIndicators();
   status.textContent='Reviewed query values applied · Unsaved changes';
   const refreshTotal=Boolean(result.record?.template_row_key.endsWith('/A18'));
   if(refreshTotal||Object.keys(data.preview).length||Object.keys(data.total).length){
    const version=++sequence;
    pending=pending.catch(()=>{}).then(()=>request('calculate',{refreshTotal})).then(calculation=>{
     if(version===sequence){
      data.total=calculation.total;data.previewToken=calculation.previewToken;
      renderCalculation(calculation,true);if(selected)select(selected);
      if(Object.values(data.total).includes(null))status.textContent='Total KWH Sold is unavailable.';
     }
    }).catch(e=>{status.textContent=e.message;status.classList.add('error');});
   }else{renderCalculation(result.calculation,true);if(selected)select(selected);}
  },
  ready:()=>pending};
 document.querySelector('#restore-sql').addEventListener('click',async()=>{
  if(!selected||!data.sqlReplacements[selected.dataset.ref])return;
  const ref=selected.dataset.ref,month=data.month;
  const liveTotal=controlled(ref);
  const confirmation=liveTotal?`Clear the saved formula override for ${ref}? The current SQL value will stay in the report.`:`Restore the original template formula in ${ref}? The SQL-applied value will be removed from the working report.`;
  if(!confirm(confirmation))return;
  try{const result=await request('restore-sql',{ref});if(loading||data.month!==month)return;window.genstatReport.applyReviewed(result);status.textContent=liveTotal?'Saved formula override cleared · Unsaved changes':'Template formula restored · Unsaved changes';}
  catch(e){status.textContent=e.message;}
 });
 const yearSelector=document.querySelector('#report-year'),monthSelector=document.querySelector('#report-month');
 const overlay=document.querySelector('#report-loading-overlay'),retry=document.querySelector('#report-retry');
 const periodLabel=document.querySelector('#report-period'),settings=document.querySelector('#sql-settings-dialog');
 const content=[...document.querySelectorAll('body > header, body > main, body > footer, .formula-bar, .report-notice')];
 let loadSequence=0,failedMonth=null;
 const monthsFor=year=>data.periods.filter(period=>period.startsWith(year)).sort();
 const formatPeriod=(period,withYear=true)=>new Intl.DateTimeFormat('en',{month:'long',...(withYear?{year:'numeric'}:{}),timeZone:'UTC'}).format(new Date(period.slice(0,4)+'-'+period.slice(4)+'-01T00:00:00Z'));
 function syncSelectors(period){
  const year=period.slice(0,4);yearSelector.value=year;
  monthSelector.replaceChildren(...monthsFor(year).map(value=>new Option(formatPeriod(value,false),value)));
  monthSelector.value=period;
 }
 function setLoading(active,target){
  loading=active;overlay.hidden=!active;
  if(active)document.querySelector('#loading-period').textContent=formatPeriod(target);
  viewport.setAttribute('aria-busy',String(active));
  for(const section of content)section.inert=active;
  for(const control of [yearSelector,monthSelector,exportButton,saveButton,document.querySelector('#sql-settings-open')])control.disabled=active;
 }
 async function loadPeriod(target){
  if(loading)return;
  if(!target||target===data.month||saving||exporting||settings.open){syncSelectors(data.month);return;}
  if(dirty&&!confirm('Discard unsaved report changes and switch reporting periods?')){syncSelectors(data.month);return;}
  const version=++loadSequence;
  retry.hidden=true;syncSelectors(target);setLoading(true,target);
  try{
   await pending;
   const result=await request('load-period',{targetMonth:target});
   if(version!==loadSequence)return;
   if(result.month!==target)throw Error('The response belongs to a different reporting period.');
   if(!result.calculation?.cells||!result.values||!result.formulas||!result.sqlReplacements||!result.preview||!result.total)throw Error('The reporting response is incomplete.');
   Object.assign(data,result);
   syncSelectors(target);
   grants.clear();selected?.classList.remove('selected');selected=null;
   document.querySelector('#selection').textContent='Select a cell';document.querySelector('#cell-kind').textContent='Formula cells protected';
   bar.value='';bar.readOnly=true;unlock.disabled=true;document.querySelector('#restore-sql').disabled=true;
   dirty=false;sqlIndicators();renderCalculation(result.calculation,true);
   const label=formatPeriod(target);periodLabel.textContent=label;table.setAttribute('aria-label',label+' KWH purchased, sold and system losses');
   const url=new URL(location.href);url.searchParams.set('month',target);history.replaceState(null,'',url);
   status.textContent=(data.revision?'Draft loaded. ':'')+(Object.values(data.total).includes(null)?'Total KWH Sold is unavailable.':Object.keys(data.preview).length||Object.keys(data.total).length?'Read-only SQL results.':'No saved SQL preview values.');
   status.classList.remove('error');failedMonth=null;
  }catch(e){
   if(version!==loadSequence)return;
   syncSelectors(data.month);failedMonth=target;retry.hidden=false;
   status.textContent='Could not load '+formatPeriod(target)+'. '+e.message;status.classList.add('error');
  }finally{if(version===loadSequence)setLoading(false);}
 }
 yearSelector.addEventListener('change',()=>{
  const months=monthsFor(yearSelector.value),sameMonth=yearSelector.value+data.month.slice(4);
  void loadPeriod(months.includes(sameMonth)?sameMonth:months.at(-1));
 });
 monthSelector.addEventListener('change',()=>void loadPeriod(monthSelector.value));
 retry.addEventListener('click',()=>void loadPeriod(failedMonth));
 exportButton.addEventListener('click',async()=>{
  if(loading||saving||exporting)return;
  exporting=true;exportButton.disabled=true;
  try{
   await pending;
   const blob=await request('export'),url=URL.createObjectURL(blob);
   const link=document.createElement('a');link.href=url;link.download='PALECO-GenStat-'+data.month+'.xlsx';link.click();
   setTimeout(()=>URL.revokeObjectURL(url),1000);
   status.textContent='Excel exported';status.classList.remove('error');
  }catch(e){status.textContent=e.message;status.classList.add('error');}
  finally{exporting=false;exportButton.disabled=false;}
 });
 addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
})();
