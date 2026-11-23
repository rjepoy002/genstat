'use strict';
(()=>{
 const data=JSON.parse(document.querySelector('#report-data').textContent),status=document.querySelector('#status'),bar=document.querySelector('#formula'),unlock=document.querySelector('#unlock');
 const confirmationDialog=document.querySelector('#report-confirmation');
 const confirmationTitle=document.querySelector('#report-confirmation-title');
 const confirmationWarning=document.querySelector('#report-confirmation-warning');
 const confirmationDetail=document.querySelector('#report-confirmation-detail');
 const confirmationButton=confirmationDialog.querySelector('[value="confirm"]');
 const recoveryTotals=confirmationDialog.querySelector('#report-confirmation-recovery');
 const confirmationCancel=confirmationDialog.querySelector('[value="cancel"]');
 function confirmAction(message,{restore=false,detail='',formulaUnlock=false,recoverySave=null}={}){
  if(confirmationDialog.open)return Promise.resolve(false);
  confirmationDialog.returnValue='';
  confirmationTitle.textContent=recoverySave?'Confirm Recovered Sales Update':formulaUnlock?'Unlock All Formula Cells?':restore?'Restore Report Formulas?':'Confirm report action';
  confirmationWarning.hidden=!restore&&!formulaUnlock;
  confirmationWarning.querySelector('span').textContent=formulaUnlock
   ?'Changes to formulas may affect calculated report values. All committed edits will be recorded in Formula Edit History.'
   :'Any unsaved manual changes to affected cells will be replaced.';
  confirmationDetail.hidden=!detail;
  confirmationDetail.textContent=detail;
  confirmationButton.textContent=recoverySave?'Confirm Save':formulaUnlock?'Unlock Formulas':restore?'Restore & Recalculate':'Continue';
  confirmationButton.toggleAttribute('data-restore',restore);
  document.querySelector('#report-confirmation-message').textContent=message;
  recoveryTotals.hidden=!recoverySave;
  if(recoverySave){
   recoveryTotals.querySelectorAll('dd').forEach((item,index)=>{item.textContent=recoverySave.totals[index];});
   let processing=false;
   confirmationDialog.oncancel=event=>{if(processing)event.preventDefault();};
   confirmationButton.onclick=async event=>{
    event.preventDefault();
    if(processing)return;
    processing=true;
    confirmationButton.disabled=confirmationCancel.disabled=true;
    confirmationDialog.setAttribute('aria-busy','true');
    confirmationButton.textContent='Saving…';
    try{
     await recoverySave.persist();
    }finally{
     confirmationDialog.close('confirm');
    }
   };
  }
  return new Promise(resolve=>{
   confirmationDialog.addEventListener('close',()=>{
    confirmationDialog.oncancel=confirmationButton.onclick=null;
    confirmationButton.disabled=confirmationCancel.disabled=false;
    confirmationDialog.removeAttribute('aria-busy');
    resolve(confirmationDialog.returnValue==='confirm');
   },{once:true});
   confirmationDialog.showModal();
  });
 }
 let selected=null,dirty=false,sequence=0,pending=Promise.resolve(),loading=false,saving=false,exporting=false;let worksheetUnlocked=false,editingRef=null,changingLock=false;
 const manualHistory=new GenstatEditHistory();
 const undoFormula=document.querySelector('#undo-formula');
 data.preview={};
 const controlled=ref=>Object.hasOwn(data.total,ref);
 const recovered=ref=>Object.hasOwn(data.recoveredSales,ref);
 const inputValue=ref=>recovered(ref)?(data.recoveredSales[ref]??''):controlled(ref)?(data.total[ref]??''):data.values[ref]??data.preview[ref]??(data.month!=='202607'&&Number.isFinite(Number(data.metadata[ref].originalValue))?'':data.metadata[ref].originalValue);
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
 const referenceHighlights=createBaseReportReferenceHighlights(table,data.metadata,bar);
 let inspectingFormula=false,selectedRowLabel=null,activeColumnHeading=null,activeColumnCells=[];
 function clearFormulaReferences(){
  inspectingFormula=false;
  if(selected?.hasAttribute('data-active-formula'))selected.classList.remove('selected');
  selected?.removeAttribute('data-active-formula');
  referenceHighlights.show(null);
 }
 function activeFormula(ref){
  if(recovered(ref)||controlled(ref)||Object.hasOwn(data.sqlReplacements,ref)||Object.hasOwn(data.values,ref))return null;
  const formula=data.formulas[ref]??data.metadata[ref].effectiveFormula;
  return formula?.startsWith('=')?formula:null;
 }
 function refreshFormulaReferences(){
  const ref=selected?.dataset.ref;
  const formula=inspectingFormula&&ref?activeFormula(ref):null;
  selected?.toggleAttribute('data-active-formula',Boolean(formula));
  referenceHighlights.show(formula,ref?data.metadata[ref].worksheet:null);
 }
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
  const rowLabel=label&&table.contains(label)?label:null;
  selectRowLabel(rowLabel);
  if(rowLabel){clearFormulaReferences();return;}
  const cell=e.target.closest('[data-classification]');
  if(cell&&table.contains(cell)){select(cell,true);return;}
  clearFormulaReferences();
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
  const cols=[...table.querySelectorAll('col[data-column]')];
  const numericWidths=new Map();
  let requiredWidth=(cols[0]?.hidden?0:210)+parseFloat(getComputedStyle(table).getPropertyValue('--excel-gutter'));
  for(const column of cols.slice(1)){
   const total=column.hasAttribute('data-total-column');
   if(column.hidden||total&&totalCollapsed)continue;
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
  if(recovered(ref))return false;
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
   cell.toggleAttribute('data-manual-formula-override',Object.hasOwn(data.formulas,ref)&&!controlled(ref)&&!record);
   if(fromSql)cell.dataset.sqlApplied='true';
   else delete cell.dataset.sqlApplied;
   cell.title=controlled(ref)?ref+' ? SQL-controlled Total KWH Sold':
    fromSql?(record?`${ref} ? SQL-applied value ? Original template ${record.originalFormula}`:`${ref} ? SQL-applied value`):cell.dataset.templateTitle+(cell.hasAttribute('data-manual-formula-override')?' / Manual formula override':'');
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
  if(result.recoveredSales)data.recoveredSales=result.recoveredSales;
  for(const [ref,c] of Object.entries(result.cells)){
   const cell=table.querySelector(`[data-classification][data-ref="${ref}"]`);if(!cell||cell.tagName==='TH')continue;
   cell.querySelector('.value').textContent=c.display;
   cell.toggleAttribute('data-calculation-error',Boolean(c.error));
   const input=inputs&&cell.querySelector('input');if(input){input.value=inputValue(ref);input.readOnly=recovered(ref)||controlled(ref);}
  }
  warn(result.warnings,result.errors);sizeNumericColumns();
 }
 function showUnlockState(unlocked){
  const action=unlocked?'Lock Formulas':'Unlock Formulas';
  table.toggleAttribute('data-formulas-unlocked',unlocked);
  unlock.setAttribute('aria-pressed',String(unlocked));
  unlock.setAttribute('aria-label',action);
  unlock.title=unlock.dataset.tooltip=action;
  unlock.querySelector('[data-lock-icon="closed"]').toggleAttribute('hidden',unlocked);
  unlock.querySelector('[data-lock-icon="open"]').toggleAttribute('hidden',!unlocked);
 }
 function canEditCell(cell){
  const ref=cell.dataset.ref;
  if(recovered(ref)||controlled(ref)||Object.hasOwn(data.sqlReplacements,ref))return false;
  if(cell.hasAttribute('data-original-formula-editable'))return worksheetUnlocked&&!Object.hasOwn(data.preview,ref);
  return data.metadata[ref].editable;
 }
 function highlightColumn(ref){
  activeColumnHeading?.removeAttribute('data-active-coordinate');
  for(const cell of activeColumnCells)cell.removeAttribute('data-active-column');
  const name=ref?.match(/^[A-Z]+/)?.[0],column=data.metadata[ref]?.column;
  activeColumnHeading=name?table.querySelector(`[data-excel-heading-column="${name}"]`):null;
  activeColumnHeading?.setAttribute('data-active-coordinate','');
  activeColumnCells=column?[...table.querySelectorAll('td[data-ref]')].filter(cell=>data.metadata[cell.dataset.ref]?.column===column):[];
  for(const cell of activeColumnCells)cell.setAttribute('data-active-column','');
 }
 function select(cell,inspect=inspectingFormula){
  selected?.classList.remove('selected');
  selected?.removeAttribute('data-active-formula');
  inspectingFormula=inspect;
  selected=cell;
  highlightColumn(cell.dataset.ref);
  cell.classList.add('selected');
  const ref=cell.dataset.ref,metadata=data.metadata[ref],sql=data.sqlReplacements[ref];
  const isControlled=controlled(ref),formula=activeFormula(ref);
  document.querySelector('#selection').textContent=ref;
  document.querySelector('#cell-kind').textContent=recovered(ref)?'Recovered Sales workbook':isControlled?'SQL-controlled value':
   sql?'SQL-applied value ? template formula preserved':sqlSource(ref)?'SQL-applied value':metadata.classification;
  bar.value=recovered(ref)?inputValue(ref):isControlled?(data.total[ref]??'#N/A'):sql?sql.value:(data.formulas[ref]??formula??inputValue(ref));
  bar.readOnly=!canEditCell(cell);
  if(inspect&&formula&&selectedRowLabel)selectRowLabel(null);
  refreshFormulaReferences();
 }
 table.addEventListener('focusin',e=>{
  const cell=e.target.closest('[data-classification]');
  if(cell)select(cell,!e.target.closest('.report-row-toggle'));
 });
 function recalculate(){
  const version=++sequence;
  dirty=true;status.textContent='Recalculating…';
  pending=pending.catch(()=>{}).then(async()=>{
   if(version!==sequence)return;
   const result=await request('calculate');
   if(version!==sequence)return;
   renderCalculation(result);
   status.textContent='Unsaved changes';status.classList.remove('error');
  }).catch(e=>{
   if(version!==sequence)return;
   status.textContent=e.message;status.classList.add('error');
  });
  return pending;
 }
 function editableGridInput(input){
  return input.matches('td input[data-ref]:enabled')&&!input.readOnly&&canEditCell(input.closest('td'));
 }
 function commitGridInput(input){
  if(!editableGridInput(input)||input.value===String(inputValue(input.dataset.ref)))return;
  setManualValue(input.dataset.ref,input.value);
  if(selected===input.closest('td'))select(selected);
  recalculate();
 }
 function handleGridKey(event,cell,commit,cancel){
  handleWorksheetEditKey(event,{
   cell,commit,cancel,
   candidates:()=>table.querySelectorAll('td[data-ref]'),
   coordinates:cell=>{
    const {row,column}=data.metadata[cell.dataset.ref],end=data.metadata[cell.dataset.excelRangeEnd];
    return {row,column,rowEnd:end?.row,columnEnd:end?.column};
   },
   leave:direction=>document.querySelector(direction<0?'#total-group-toggle':'#report-notes summary').focus(),
   focus:cell=>{
    const input=cell.querySelector('input');
    if(input&&editableGridInput(input)){input.focus();return;}
    if(!cell.hasAttribute('tabindex'))cell.tabIndex=-1;
    cell.focus();
    openFormulaEditor(cell);
   }
  });
 }
 table.addEventListener('change',e=>commitGridInput(e.target));
 table.addEventListener('keydown',e=>{
  const target=e.target,cell=target.closest('td[data-ref]');
  if(!cell||(!target.matches('input[data-ref]')&&target!==cell))return;
  const editing=editableGridInput(target);
  handleGridKey(e,cell,()=>{if(editing)commitGridInput(target);},()=>{if(editing)target.value=inputValue(target.dataset.ref);});
 });
 function setWorksheetUnlocked(unlocked){
  worksheetUnlocked=unlocked;
  showUnlockState(unlocked);
  if(selected)select(selected);
 }
 function clearManualHistory(){
  manualHistory.clear();
  undoFormula.hidden=true;
 }
 function openFormulaEditor(cell,replacement){
  if(!cell?.hasAttribute('data-original-formula-editable')||!canEditCell(cell)||loading||saving||exporting)return false;
  if(selected!==cell)select(cell,true);
  bar.focus();
  if(replacement!==undefined){bar.value=replacement;editingRef=cell.dataset.ref;}
  return true;
 }
 table.addEventListener('dblclick',e=>openFormulaEditor(e.target.closest('[data-original-formula-editable]')));
 table.addEventListener('keydown',e=>{
  const cell=e.target.closest('[data-original-formula-editable]');
  if(e.defaultPrevented||e.target!==cell)return;
  if(e.key==='F2'||e.key==='Enter'||(e.key.length===1&&!e.ctrlKey&&!e.metaKey&&!e.altKey)){
   if(openFormulaEditor(cell,e.key.length===1?e.key:undefined))e.preventDefault();
  }
 });
 function commitFormula(ref,content,editToken){
  const version=++sequence;
  pending=pending.catch(()=>{}).then(async()=>{
   const result=await request(editToken?'undo-formula':'edit-formula',{ref,content,editToken});
   data.formulas=result.formulas;
   data.formulaAudit=result.formulaAudit;
   if(editToken)manualHistory.commit('undo');
   else if(result.editToken)manualHistory.record({ref,before:null,after:result.editToken});
   undoFormula.hidden=!manualHistory.peek('undo');
   if(result.changed){dirty=true;setRestoreRecovery(null);}
   sqlIndicators();
   if(version===sequence)renderCalculation(result.calculation);
   if(selected&&!editingRef)select(selected,true);
   status.classList.remove('error');
   if(dirty)status.textContent='Unsaved changes';
  }).catch(e=>{
   if(e.message.startsWith('Unlock Formulas'))setWorksheetUnlocked(false);
   status.textContent=e.message;
   status.classList.add('error');
  });
  return pending;
 }
 function commitBarEdit(){
  if(!editingRef)return;
  const ref=editingRef,content=bar.value;
  editingRef=null;
  const cell=table.querySelector('td[data-ref="'+ref+'"]');
  if(!cell||!canEditCell(cell))return;
  if(cell.hasAttribute('data-original-formula-editable'))return commitFormula(ref,content);
  const input=cell.querySelector('input');
  if(input){input.value=content;commitGridInput(input);}
 }
 bar.addEventListener('input',()=>{
  if(selected&&canEditCell(selected))editingRef=selected.dataset.ref;
 });
 bar.addEventListener('blur',commitBarEdit);
 bar.addEventListener('keydown',e=>{
  if(bar.readOnly||!selected)return;
  const cell=selected;
  handleGridKey(e,cell,commitBarEdit,()=>{editingRef=null;select(cell);});
 });
 unlock.addEventListener('click',async()=>{
  if(changingLock||loading||saving||exporting)return;
  changingLock=true;
  try{
   if(!worksheetUnlocked&&!await confirmAction('This will allow manual editing of all Excel-based formula cells in the current reporting worksheet.',{formulaUnlock:true}))return;
   await pending;
   const result=await request(worksheetUnlocked?'lock-formulas':'unlock');
   setWorksheetUnlocked(result.unlocked);
   status.classList.remove('error');
  }catch(e){status.textContent=e.message;status.classList.add('error');}
  finally{changingLock=false;}
 });
 undoFormula.addEventListener('click',()=>{
  const command=manualHistory.peek('undo');
  if(command&&!loading&&!saving&&!exporting)void commitFormula(command.ref,undefined,command.after);
 });
 document.querySelector('#report-formula-history').addEventListener('click',()=>{
  const list=document.querySelector('#report-formula-history-list');
  list.replaceChildren();
  for(const event of [...data.formulaAudit].reverse()){
   const item=document.createElement('li'),title=document.createElement('strong'),content=document.createElement('div');
   title.textContent=[event.period,event.sheet+'!'+event.cell,event.action,event.timestamp,event.editor].filter(Boolean).join(' / ');
   content.textContent=event.previousFormula+' '+String.fromCharCode(8594)+' '+event.newFormula;
   item.append(title,content);list.append(item);
  }
  document.querySelector('#report-formula-history-empty').hidden=list.childElementCount>0;
  document.querySelector('#report-formula-history-dialog').showModal();
 });
 const saveButton=document.querySelector('#save'),exportButton=document.querySelector('#report-export');
 saveButton.addEventListener('click',async()=>{
  if(saving||loading||exporting)return;
  saving=true;saveButton.disabled=true;saveButton.setAttribute('aria-busy','true');
  status.textContent='Saving draft…';status.classList.remove('error');
  // Use the same queue as recalculation so edits made during a draft write
  // calculate against the new revision once the write has finished.
  const write=pending.catch(()=>{}).then(async()=>{
   const version=sequence;
   status.textContent='Saving draft…';
   const result=await request('save');
   data.revision=result.revision;
   if(version===sequence){dirty=false;status.textContent='Draft saved';}
  }).catch(e=>{status.textContent='Save failed. '+e.message;status.classList.add('error');});
  pending=write;
  try{await write;}
  finally{saving=false;saveButton.disabled=false;saveButton.removeAttribute('aria-busy');}
 });
 // Reviewed SQL values enter the same live draft and Save Draft workflow.
 async function refreshRecoveredSales(month){
  await pending;
  if(month!==data.month)throw Error('The reporting month changed.');
  const result=await request('calculate');
  if(month!==data.month||!result.recoveredSales||!result.cells)throw Error('The report returned an incomplete summary.');
  renderCalculation(result,true);
  if(selected)select(selected);
 }
 window.genstatReport={confirmAction,refreshRecoveredSales,notifyRecoveredSalesSaved:()=>notifyReport('Recovered Sales saved and GENSTAT summary updated successfully.'),snapshot:()=>({month:data.month,csrf:data.csrf,hash:data.hash,revision:data.revision,values:structuredClone(data.values),formulas:structuredClone(data.formulas),sqlReplacements:structuredClone(data.sqlReplacements),sqlSources:structuredClone(data.sqlSources)}),
  previewContext:()=>({preview:data.preview,total:data.total,previewToken:data.previewToken}),
  disableSqlLabels(rowKey){setRestoreRecovery(null);
   for(const [ref,key] of Object.entries(data.sqlTargets))if(key===rowKey)delete data.sqlTargets[ref];
   sqlIndicators();sizeNumericColumns();if(selected)select(selected);
  },
  applySqlResult(result){setRestoreRecovery(null);
   data.values=result.values??data.values;data.formulas=result.formulas??data.formulas;
   data.sqlReplacements=result.sqlReplacements??data.sqlReplacements;
   data.sqlSources=result.sqlSources??data.sqlSources;
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
 function notifyReport(message,error=false){
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
   clearManualHistory();
   sqlIndicators();
   renderCalculation(calculation,true);
   if(selected)select(selected);
   status.classList.remove('error');
   if(dirty)status.textContent='Unsaved changes';
   else if(undo)status.textContent=data.revision?'Draft loaded':'No saved report data.';
   notifyReport(undo?'Previous report recovered.':'Report formulas restored and saved configurations reapplied.');
  }catch(e){
   notifyReport((undo?'Recovery failed. Your current report has been preserved.':'Restoration failed. Your previous report has been preserved.')+' '+e.message,true);
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
  notifyReport('');
  await rebuildReport();
 });
 undoRestore.addEventListener('click',()=>{
  if(restoreRecovery&&!loading&&!saving&&!exporting)void rebuildReport(true);
 });
 document.addEventListener('keydown',e=>{
  if(!(e.ctrlKey||e.metaKey)||e.altKey||e.shiftKey||e.key.toLowerCase()!=='z'||e.target.closest('dialog, input:not([readonly]), textarea, [contenteditable="true"]')||loading||saving||exporting)return;
  const manual=manualHistory.peek('undo');
  if(!manual&&!restoreRecovery)return;
  e.preventDefault();
  if(manual)void commitFormula(manual.ref,undefined,manual.after);
  else void rebuildReport(true);
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
  for(const control of [yearSelector,monthSelector,exportButton,saveButton,unlock,document.querySelector('#sql-settings-open'),document.querySelector('#recovered-sales-open')])control.disabled=active;
 }
 async function loadPeriod(target){
  if(loading)return;
  if(!target||target===data.month||saving||exporting||settings.open){syncSelectors(data.month);return;}
  if(dirty&&!await confirmAction('Discard unsaved report changes and switch reporting periods?')){syncSelectors(data.month);return;}
  const version=++loadSequence;
  clearFormulaReferences();
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
   setWorksheetUnlocked(false);clearManualHistory();selected?.classList.remove('selected');selected=null;
   document.querySelector('#selection').textContent='Select a cell';document.querySelector('#cell-kind').textContent='Formula cells protected';
   highlightColumn(null);
   bar.value='';bar.readOnly=true;setRestoreRecovery(null);notifyReport('');
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
