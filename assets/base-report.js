'use strict';
(()=>{
 const data=JSON.parse(document.querySelector('#report-data').textContent),status=document.querySelector('#status'),bar=document.querySelector('#formula'),unlock=document.querySelector('#unlock');
 const confirmationDialog=document.querySelector('#report-confirmation');
 const confirmationTitle=document.querySelector('#report-confirmation-title');
 const confirmationWarning=document.querySelector('#report-confirmation-warning');
 const confirmationDetail=document.querySelector('#report-confirmation-detail');
 const confirmationButton=confirmationDialog.querySelector('[value="confirm"]');
 function confirmAction(message,{restore=false,detail=''}={}){
  if(confirmationDialog.open)return Promise.resolve(false);
  confirmationDialog.returnValue='';
  confirmationTitle.textContent=restore?'Restore Report Formulas?':'Confirm report action';
  confirmationWarning.hidden=!restore;
  confirmationDetail.hidden=!detail;
  confirmationDetail.textContent=detail;
  confirmationButton.textContent=restore?'Restore & Recalculate':'Continue';
  confirmationButton.toggleAttribute('data-restore',restore);
  document.querySelector('#report-confirmation-message').textContent=message;
  return new Promise(resolve=>{
   confirmationDialog.addEventListener('close',()=>resolve(confirmationDialog.returnValue==='confirm'),{once:true});
   confirmationDialog.showModal();
  });
 }
 let selected=null,dirty=false,sequence=0,pending=Promise.resolve(),loading=false,saving=false,exporting=false;const grants=new Set();
 data.preview={};
 const controlled=ref=>Object.hasOwn(data.total,ref);
 const inputValue=ref=>controlled(ref)?(data.total[ref]??''):data.values[ref]??data.preview[ref]??(data.month!=='202607'&&Number.isFinite(Number(data.metadata[ref].originalValue))?'':data.metadata[ref].originalValue);
 const warn=(warnings,errors)=>{
  const list=document.querySelector('#warnings'),errorCount=document.querySelector('#report-error-count');
  const errorEntries=Object.entries(errors);
  document.querySelector('#report-warning-count').textContent=warnings.length;
  errorCount.hidden=!errorEntries.length;
  errorCount.textContent=errorEntries.length?` · ${errorEntries.length} calculation errors`:'';
  list.replaceChildren();
  function append(message){const item=document.createElement('li');item.textContent=message;list.append(item);}
  for(const w of warnings)append(`${w.cell}: ${w.formula} — ${w.message}`);
  for(const [ref,error] of errorEntries)append(`${ref}: ${error}`);
  if(!list.children.length)append('No calculation warnings.');
 };
 warn(data.warnings,data.errors);
 // Element-only zoom keeps native table layout and sticky cells in the same
 // coordinate system. Never zoom the browser or shrink desktop text below 10px.
 const table=document.querySelector('#genstat-report-table'),viewport=document.querySelector('.report-scroll');
 let selectedRowLabel=null;
 function selectRowLabel(label){
  if(selectedRowLabel){
   selectedRowLabel.setAttribute('aria-pressed','false');
   selectedRowLabel.closest('tr').removeAttribute('data-row-selected');
  }
  selectedRowLabel=label===selectedRowLabel?null:label;
  if(selectedRowLabel){
   selectedRowLabel.setAttribute('aria-pressed','true');
   selectedRowLabel.closest('tr').setAttribute('data-row-selected','');
  }
 }
 document.addEventListener('click',e=>{
  const label=e.target.closest('.report-row-toggle');
  selectRowLabel(label&&table.contains(label)?label:null);
 });
 const totalToggle=document.querySelector('#total-group-toggle');
 const totalPreference='genstat_total_group_collapsed';
 let totalCollapsed=true;
 try{totalCollapsed=localStorage.getItem(totalPreference)!=='false';}catch{}
 function renderTotalGroup(){
  table.dataset.totalCollapsed=String(totalCollapsed);
  const action=totalCollapsed?'Show Totals':'Hide Totals';
  totalToggle.setAttribute('aria-expanded',String(!totalCollapsed));
  totalToggle.setAttribute('aria-label',action);totalToggle.dataset.tooltip=action;
 }
 renderTotalGroup();
 totalToggle.addEventListener('click',()=>{
  totalCollapsed=!totalCollapsed;
  try{localStorage.setItem(totalPreference,String(totalCollapsed));}catch{}
  renderTotalGroup();sizeNumericColumns();
 });
 // Widen numeric columns when formatted content needs it; never clip numbers
 // when headings, appearance, or edited results change.
 const measure=document.createElement('canvas').getContext('2d');
 function sizeNumericColumns(){
  const fontSize=parseFloat(getComputedStyle(table).fontSize);
  const padding=parseFloat(getComputedStyle(table).getPropertyValue('--report-cell-padding'));
  const cols=[...table.querySelectorAll('col')];
  const numericWidths=new Map();
  let requiredWidth=210;
  for(const column of cols.slice(1)){
   const total=column.hasAttribute('data-total-column');
   if(total&&totalCollapsed)continue;
   let width=(total?126:94)+2*(padding-5);
   for(const row of table.rows){if(row.hidden)continue;
    const cell=row.querySelector(`[data-ref="${column.dataset.column}${row.dataset.excelRow}"]`);
    if(!cell)continue;
    const heading=cell.tagName==='TH';
    if(heading&&cell.colSpan>1)continue;
    measure.font=`${getComputedStyle(cell).fontWeight} ${fontSize}px Arial`;
    const lines=cell.querySelector('.value').textContent.split('\n');
    const contentWidth=Math.max(...lines.map(line=>measure.measureText(line).width));
    width=Math.max(width,Math.ceil(contentWidth)+(heading?20:2*padding+4)+(cell.dataset.sqlApplied?18:0));
   }
   numericWidths.set(column,width);requiredWidth+=width;
  }
  const extra=totalCollapsed?Math.floor(Math.max(0,viewport.clientWidth-requiredWidth)/Math.max(1,numericWidths.size)):0;
  for(const [column,width] of numericWidths)column.style.width=(width+extra)+'px';
 }
 addEventListener('resize',sizeNumericColumns);
 addEventListener('genstat-appearance',sizeNumericColumns);sizeNumericColumns();
 data.sqlReplacements??={};
 data.sqlSources??={};
 data.sqlTargets??={};
 function sqlSource(ref){
  if(!Object.hasOwn(data.sqlTargets,ref))return false;
  if(controlled(ref))return data.total[ref]!==null;
  if(Object.hasOwn(data.sqlReplacements,ref))return true;
  if(Object.hasOwn(data.values,ref))return Object.hasOwn(data.sqlSources,ref);
  return Object.hasOwn(data.preview,ref);
 }
 function sqlIndicators(){
  for(const cell of table.querySelectorAll('[data-classification]')){
   const ref=cell.dataset.ref,record=data.sqlReplacements[ref],fromSql=sqlSource(ref);
   cell.dataset.templateTitle??=cell.title;
   cell.setAttribute('aria-readonly',String(controlled(ref)||!data.metadata[ref].editable));
   if(fromSql)cell.dataset.sqlApplied='true';
   else delete cell.dataset.sqlApplied;
   cell.title=controlled(ref)?ref+' ? SQL-controlled Total KWH Sold':
    fromSql?(record?`${ref} ? SQL-applied value ? Original template ${record.originalFormula}`:`${ref} ? SQL-applied value`):cell.dataset.templateTitle;
  }
 }
 function setManualValue(ref,value){
  data.values[ref]=value;
  delete data.sqlSources[ref];
  sqlIndicators();
 }
 sqlIndicators();sizeNumericColumns();
 async function request(action,extra={}){
  const response=await fetch('base-report.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,month:data.month,csrf:data.csrf,hash:data.hash,revision:data.revision,values:data.values,formulas:data.formulas,sqlReplacements:data.sqlReplacements,sqlSources:data.sqlSources,preview:data.preview,total:data.total,previewToken:data.previewToken,...extra})});
  if(action==='export'&&response.ok)return response.blob();
  const result=await response.json();if(!response.ok||result.error)throw Error(result.error||'Request failed');return result;
 }
 function renderCalculation(result,inputs=false){
  for(const [ref,c] of Object.entries(result.cells)){
   const cell=table.querySelector(`[data-classification][data-ref="${ref}"]`);if(!cell||cell.tagName==='TH')continue;
   cell.querySelector('.value').textContent=c.display;
   cell.toggleAttribute('data-calculation-error',Boolean(c.error));
   const input=inputs&&cell.querySelector('input');if(input){input.value=inputValue(ref);input.readOnly=controlled(ref);}
  }
  warn(result.warnings,result.errors);sizeNumericColumns();
 }
 function select(cell){selected?.classList.remove('selected');selected=cell;cell.classList.add('selected');const ref=cell.dataset.ref,m=data.metadata[ref],sql=data.sqlReplacements[ref];document.querySelector('#selection').textContent=ref;document.querySelector('#cell-kind').textContent=controlled(ref)?'SQL-controlled value':sql?'SQL-applied value · template formula preserved':sqlSource(ref)?'SQL-applied value':m.classification;bar.value=controlled(ref)?(data.total[ref]??'#N/A'):sql?sql.value:(data.formulas[ref]??(m.effectiveFormula!==null?'='+m.effectiveFormula.replace(/^=/,''):inputValue(ref)));bar.readOnly=controlled(ref)||Boolean(sql)||!m.editable&&!grants.has(ref);unlock.disabled=controlled(ref)||Boolean(sql)||m.classification!=='formula'||grants.has(ref);}
 table.addEventListener('focusin',e=>{const cell=e.target.closest('[data-classification]');if(cell)select(cell);});
 function recalculate(){const version=++sequence;dirty=true;status.textContent='Recalculating…';pending=pending.catch(()=>{}).then(async()=>{const result=await request('calculate');if(version!==sequence)return;renderCalculation(result);status.textContent='Unsaved changes';}).catch(e=>{status.textContent=e.message;status.classList.add('error');});return pending;}
 table.addEventListener('change',e=>{if(!e.target.matches('td input'))return;setManualValue(e.target.dataset.ref,e.target.value);select(e.target.closest('td'));recalculate();});
 unlock.addEventListener('click',async()=>{if(!selected)return;const ref=selected.dataset.ref,month=data.month;if(!await confirmAction(`This cell contains a formula. Editing ${ref} may affect other calculations. Unlock and edit this cell?`))return;try{await request('unlock',{ref});if(loading||data.month!==month||!selected)return;grants.add(ref);select(selected);bar.focus();}catch(e){status.textContent=e.message;}});
 bar.addEventListener('keydown',async e=>{if(e.key==='Escape'&&selected)select(selected);if(e.key!=='Enter'||bar.readOnly||!selected)return;const ref=selected.dataset.ref,m=data.metadata[ref];if(m.classification==='formula'){if(!bar.value.startsWith('=')){status.textContent='Enter an Excel formula beginning with =.';return;}data.formulas[ref]=bar.value;}else{setManualValue(ref,bar.value);select(selected);selected.querySelector('input').value=bar.value;}await recalculate();});
 const saveButton=document.querySelector('#save'),exportButton=document.querySelector('#report-export');
 saveButton.addEventListener('click',async()=>{
  if(saving||loading||exporting)return;
  saving=true;saveButton.disabled=true;saveButton.setAttribute('aria-busy','true');
  status.textContent='Saving draft…';status.classList.remove('error');
  try{await pending;status.textContent='Saving draft…';const result=await request('save');data.revision=result.revision;dirty=false;status.textContent='Draft saved';}
  catch(e){status.textContent='Save failed. '+e.message;status.classList.add('error');}
  finally{saving=false;saveButton.disabled=false;saveButton.removeAttribute('aria-busy');}
 });
 // Reviewed SQL values enter the same live draft and Save Draft workflow.
 window.genstatReport={confirmAction,snapshot:()=>({month:data.month,csrf:data.csrf,hash:data.hash,revision:data.revision,values:structuredClone(data.values),formulas:structuredClone(data.formulas),sqlReplacements:structuredClone(data.sqlReplacements),sqlSources:structuredClone(data.sqlSources)}),
  previewContext:()=>({preview:data.preview,total:data.total,previewToken:data.previewToken}),
  disableSqlLabels(rowKey){setRestoreRecovery(null);
   for(const [ref,key] of Object.entries(data.sqlTargets))if(key===rowKey)delete data.sqlTargets[ref];
   sqlIndicators();sizeNumericColumns();if(selected)select(selected);
  },
  applySqlResult(result){setRestoreRecovery(null);
   data.values=result.values??data.values;data.formulas=result.formulas??data.formulas;
   for(const ref of Object.keys(data.sqlReplacements))grants.delete(ref);
   data.sqlReplacements=result.sqlReplacements??data.sqlReplacements;
   data.sqlSources=result.sqlSources??data.sqlSources;
   for(const ref of Object.keys(data.sqlReplacements))grants.delete(ref);
   dirty=true;
   status.textContent='SQL values applied · Unsaved changes';
   if(result.previewContext){
    Object.assign(data,result.previewContext);
    sqlIndicators();renderCalculation(result.calculation,true);if(selected)select(selected);
    return;
   }
   sqlIndicators();
   const refreshTotal=Boolean(result.record?.template_row_key.endsWith('/A18'));
   if(refreshTotal||Object.keys(data.preview).length||Object.keys(data.total).length){
    const version=++sequence;
    pending=pending.catch(()=>{}).then(()=>request('calculate',{refreshTotal})).then(calculation=>{
     if(version===sequence){
      data.total=calculation.total;data.previewToken=calculation.previewToken;
      data.sqlTargets=calculation.sqlTargets??data.sqlTargets;sqlIndicators();
      renderCalculation(calculation,true);if(selected)select(selected);
      if(Object.values(data.total).includes(null))status.textContent='Total KWH Sold is unavailable.';
     }
    }).catch(e=>{status.textContent=e.message;status.classList.add('error');});
   }else{renderCalculation(result.calculation,true);if(selected)select(selected);}
  },
  ready:()=>pending};
 const restoreButton=document.querySelector('#restore-sql');
 const undoRestore=document.querySelector('#undo-restore');
 const restoreNotice=document.querySelector('#restore-notice');
 let restoreRecovery=null;
 function setRestoreRecovery(recovery){
  restoreRecovery=recovery;
  undoRestore.hidden=!recovery;
 }
 function notifyRestore(message,error=false){
  restoreNotice.toggleAttribute('data-error',error);
  restoreNotice.textContent=message;
 }
 async function rebuildReport(undo=false){
  const recovery=restoreRecovery;
  const before={dirty,revision:data.revision,sqlTargets:data.sqlTargets};
  setLoading(true,data.month,undo?'Recovering the previous report?':'Restoring Excel formulas and recalculating report?');
  restoreButton.disabled=true;
  restoreButton.setAttribute('aria-busy','true');
  try{
   await pending;
   const result=await request(undo?'undo-restore':'restore-report',undo?{undoToken:recovery.token}:{});
   if(result.month!==data.month||!result.calculation?.cells||!result.values||!result.formulas||!result.sqlReplacements||!result.sqlSources||!result.preview||!result.total||(!undo&&!result.sqlTargets))throw Error('The restoration response is incomplete.');
   const {calculation,undoToken,draftChanged,...state}=result;
   Object.assign(data,state);
   if(undo){
    data.sqlTargets=recovery.sqlTargets;
    dirty=recovery.revision===data.revision?recovery.dirty:true;
    setRestoreRecovery(null);
   }else if(undoToken){
    setRestoreRecovery({...before,token:undoToken});
    dirty=dirty||draftChanged;
   }
   grants.clear();
   sqlIndicators();
   renderCalculation(calculation,true);
   if(selected)select(selected);
   status.classList.remove('error');
   if(dirty)status.textContent='Unsaved changes';
   else if(undo)status.textContent=data.revision?'Draft loaded':'No saved report data.';
   notifyRestore(undo?'Previous report recovered.':'Report formulas restored and saved configurations reapplied.');
  }catch(e){
   notifyRestore((undo?'Recovery failed. Your current report has been preserved.':'Restoration failed. Your previous report has been preserved.')+' '+e.message,true);
  }finally{
   setLoading(false);
   restoreButton.disabled=false;
   restoreButton.removeAttribute('aria-busy');
   (undo&&restoreRecovery?undoRestore:restoreButton).focus({preventScroll:true});
  }
 }
 restoreButton.addEventListener('click',async()=>{
  if(loading||saving||exporting)return;
  const month=data.month;
  const message='This will restore the original Excel template formulas and reapply all saved SQL configurations and custom report formulas for the selected reporting period.';
  if(!await confirmAction(message,{restore:true,detail:formatPeriod(month)}))return;
  if(loading||saving||exporting||data.month!==month)return;
  notifyRestore('');
  await rebuildReport();
 });
 undoRestore.addEventListener('click',()=>{
  if(restoreRecovery&&!loading&&!saving&&!exporting)void rebuildReport(true);
 });
 document.addEventListener('keydown',e=>{
  if(!(e.ctrlKey||e.metaKey)||e.altKey||e.shiftKey||e.key.toLowerCase()!=='z'||e.target.closest('dialog, input:not([readonly]), textarea, [contenteditable="true"]')||!restoreRecovery||loading||saving||exporting)return;
  e.preventDefault();
  void rebuildReport(true);
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
 function setLoading(active,target,title='Loading GENSTAT Report'){
  loading=active;overlay.hidden=!active;
  if(active){document.querySelector('#loading-title').textContent=title;document.querySelector('#loading-period').textContent=formatPeriod(target);}
  viewport.setAttribute('aria-busy',String(active));
  for(const section of content)section.inert=active;
  for(const control of [yearSelector,monthSelector,exportButton,saveButton,document.querySelector('#sql-settings-open')])control.disabled=active;
 }
 async function loadPeriod(target){
  if(loading)return;
  if(!target||target===data.month||saving||exporting||settings.open){syncSelectors(data.month);return;}
  if(dirty&&!await confirmAction('Discard unsaved report changes and switch reporting periods?')){syncSelectors(data.month);return;}
  const version=++loadSequence;
  retry.hidden=true;syncSelectors(target);setLoading(true,target);
  try{
   await pending;
   const result=await request('load-period',{targetMonth:target});
   if(version!==loadSequence)return;
   if(result.month!==target)throw Error('The response belongs to a different reporting period.');
   if(!result.calculation?.cells||!result.values||!result.formulas||!result.sqlReplacements||!result.sqlSources||!result.sqlTargets||!result.preview||!result.total)throw Error('The reporting response is incomplete.');
   Object.assign(data,result);
   syncSelectors(target);
   selectRowLabel(null);
   grants.clear();selected?.classList.remove('selected');selected=null;
   document.querySelector('#selection').textContent='Select a cell';document.querySelector('#cell-kind').textContent='Formula cells protected';
   bar.value='';bar.readOnly=true;unlock.disabled=true;setRestoreRecovery(null);notifyRestore('');
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
