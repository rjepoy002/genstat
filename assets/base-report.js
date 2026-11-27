'use strict';
(()=>{
 const data=JSON.parse(document.querySelector('#report-data').textContent),statusDetail=document.querySelector('#report-status-detail'),bar=document.querySelector('#formula'),unlock=document.querySelector('#unlock');
 const confirmationDialog=document.querySelector('#report-confirmation');
 const confirmationTitle=document.querySelector('#report-confirmation-title');
 const confirmationWarning=document.querySelector('#report-confirmation-warning');
 const confirmationDetail=document.querySelector('#report-confirmation-detail');
 const confirmationButton=confirmationDialog.querySelector('[value="confirm"]');
 const recoveryTotals=confirmationDialog.querySelector('#report-confirmation-recovery');
 const confirmationCancel=confirmationDialog.querySelector('[value="cancel"]');
 function confirmAction(message,{restore=false,restoreSql=false,detail='',formulaUnlock=false,recoverySave=null}={}){
  if(confirmationDialog.open)return Promise.resolve(false);
  confirmationDialog.returnValue='';
  confirmationTitle.textContent=restoreSql?'RESTORE SQL VALUE':recoverySave?'Confirm Recovered Sales Update':formulaUnlock?'Unlock All Formula Cells?':restore?'Restore Report Formulas & SQL Values?':'Confirm report action';
  confirmationWarning.hidden=!restore&&!formulaUnlock;
  confirmationWarning.querySelector('span').textContent=formulaUnlock
   ?'Changes to formulas may affect calculated report values. All committed edits will be recorded in Formula Edit History.'
   :'All saved and unsaved manual overrides in affected SQL and formula cells will be replaced. Other manually entered values will remain unchanged.';
  confirmationDetail.hidden=!detail;
  confirmationDetail.textContent=detail;
  confirmationButton.textContent=restoreSql?'Restore SQL Value':recoverySave?'Confirm Save':formulaUnlock?'Unlock Formulas':restore?'Restore & Recalculate':'Continue';
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
 let calculationFailed=false;
 const undoFormula=document.querySelector('#undo-formula');
 const restoreSqlButton=document.querySelector('#restore-sql-value');
 const pendingManual=new Map();
 const failedEdits=new Map();
 data.preview??={};
 const periodStatus=document.querySelector('#report-period-status');
 const reportStatus=document.querySelector('#status');
 const statusPrimary=document.querySelector('#report-status-primary');
 const statusSecondary=document.querySelector('#report-status-secondary');
 const statusSeparator=document.querySelector('#report-status-separator');
 const savedAt=document.querySelector('#report-saved-at');
 const statusIcons=document.querySelectorAll('[data-report-icon]');
 const dateFormat=new Intl.DateTimeFormat('en-US',{month:'short',day:'numeric',year:'numeric'});
 const timeFormat=new Intl.DateTimeFormat('en-US',{hour:'numeric',minute:'2-digit',hour12:true});
 const statusCopy={
  saved:['Report saved','Auto-refresh off','Monthly report saved. Automatic loading is disabled.'],
  unsaved:['Unsaved report','Auto-refresh on','Unsaved monthly report. Automatic loading is enabled.'],
  pending:['Unsaved changes','Click Save to update report','Unsaved changes. Click Save to update the monthly report.'],
  loading:['Loading report','','Loading monthly report.']
 };
 let workingModified=false,saveFailed=false,pendingInput=false;
 function renderReportStatus(){
  const state=loading||saving?'loading':dirty||workingModified||saveFailed||pendingInput?'pending':data.savedAt?'saved':'unsaved';
  const [primary,secondary,description]=statusCopy[state];
  periodStatus.dataset.state=reportStatus.dataset.state=state;
  periodStatus.title=description;
  periodStatus.setAttribute('aria-label',description);
  for(const icon of statusIcons)icon.hidden=icon.dataset.reportIcon!==state;
  statusPrimary.textContent=saving?'Saving report':primary;
  statusSecondary.textContent=secondary;
  statusSeparator.hidden=!secondary;
  savedAt.hidden=state!=='saved';
  if(state==='saved'){
   const date=new Date(data.savedAt);
   const time=timeFormat.formatToParts(date).map(part=>part.type==='literal'?part.value.replace(/\s+/g,' '):part.value).join('');
   savedAt.dateTime=data.savedAt;
   savedAt.textContent=dateFormat.format(date)+' at '+time;
  }else{
   savedAt.removeAttribute('datetime');
   savedAt.textContent='';
  }
 }
 renderReportStatus();
 const controlled=ref=>Object.hasOwn(data.total,ref);
 const recovered=ref=>Object.hasOwn(data.recoveredSales,ref);
 const manual=ref=>data.sqlManualOverrides[ref];
 const inputValue=ref=>recovered(ref)?(data.recoveredSales[ref]??''):manual(ref)?.value
  ??(sqlSource(ref)?(data.sqlLatest[ref]??data.total[ref]??data.sqlReplacements[ref]?.value??data.values[ref]??data.preview[ref]):undefined)
  ??(controlled(ref)?'':data.values[ref]??data.preview[ref]??(data.month!=='202607'&&Number.isFinite(Number(data.metadata[ref].originalValue))?'':data.metadata[ref].originalValue));
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
 const gridCellSelector=':is(td,th)[data-ref]';
 const spacerRows=new Map([...table.querySelectorAll('[data-spacer-candidate]')].map(row=>[Number(row.dataset.excelRow),{row,refs:[]}]));
 const renderedColumns=new Set([...table.querySelectorAll('col[data-excel-column]')].map(col=>Number(col.dataset.excelColumn)));
 for(const [ref,cell] of Object.entries(data.metadata)){
  if(renderedColumns.has(cell.column))spacerRows.get(cell.row)?.refs.push(ref);
 }
 data.configuredRows=JSON.parse(table.dataset.configuredRows);
 function applyEmptyRowVisibility(cells){
  const show=document.documentElement.dataset.showEmptyRows==='on';
  const configured=new Set(data.configuredRows);
  for(const [number,{row,refs}] of spacerRows){
   if(cells){
    const occupied=refs.some(ref=>{
     const value=cells[ref]?.value;
     return Object.hasOwn(data.formulas,ref)||(value!==undefined&&value!==null&&String(value).trim()!=='');
    });
    row.toggleAttribute('data-empty-row',!configured.has(number)&&!occupied);
    row.classList.toggle('spacer-row',row.hasAttribute('data-empty-row'));
   }
   row.hidden=row.hasAttribute('data-explicitly-hidden')||(!show&&row.hasAttribute('data-empty-row'));
  }
  if(selected?.closest('tr[hidden]')){
   selected.classList.remove('selected');
   selected.removeAttribute('data-active-formula');
   selected=null;highlightColumn(null);
   restoreSqlButton.hidden=true;
   document.querySelector('#selection').textContent='Select a cell';
   document.querySelector('#cell-kind').textContent='Formula cells protected';
   bar.value='';bar.readOnly=true;refreshFormulaReferences();
  }
 }
 const referenceHighlights=createBaseReportReferenceHighlights(table,data.metadata,bar);
 let inspectingFormula=false,selectedRowLabel=null,activeColumnHeading=null,activeColumnCells=[];
 function clearFormulaReferences(){
  inspectingFormula=false;
  if(selected?.hasAttribute('data-active-formula'))selected.classList.remove('selected');
  selected?.removeAttribute('data-active-formula');
  referenceHighlights.show(null);
 }
 function activeFormula(ref){
  if(manual(ref)||sqlSource(ref))return null;
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
    width=Math.max(width,Math.ceil(contentWidth)+(heading?20:2*padding+4)+(cell.dataset.sqlApplied||cell.dataset.sqlManual?18:0));
   }
   numericWidths.set(column,width);requiredWidth+=width;
  }
  const extra=totalCollapsed?Math.floor(Math.max(0,viewport.clientWidth-requiredWidth)/Math.max(1,numericWidths.size)):0;
  for(const [column,width] of numericWidths)column.style.width=(width+extra)+'px';
 }
 addEventListener('resize',sizeNumericColumns);
 addEventListener('genstat-appearance',()=>{applyEmptyRowVisibility();sizeNumericColumns();refreshFormulaReferences();});
 applyEmptyRowVisibility();sizeNumericColumns();
 data.sqlReplacements??={};
 data.sqlSources??={};
 data.sqlTargets??={};
 function sqlSource(ref){
  if(recovered(ref))return false;
  if(!Object.hasOwn(data.sqlTargets,ref))return false;
  if(Object.hasOwn(data.sqlLatest,ref))return true;
  if(controlled(ref))return data.total[ref]!==null;
  if(Object.hasOwn(data.sqlReplacements,ref))return true;
  if(Object.hasOwn(data.values,ref))return Object.hasOwn(data.sqlSources,ref);
  return Object.hasOwn(data.preview,ref);
 }
 function applyManualState(result){
  if(result.sqlManualOverrides)data.sqlManualOverrides=result.sqlManualOverrides;
  if(result.sqlLatest)data.sqlLatest=result.sqlLatest;
 }
 const formatSqlNumber=(value,options={})=>value==null?'Unavailable':Number(value).toLocaleString(undefined,{minimumFractionDigits:2,maximumFractionDigits:10,...options});
 function manualTitle(ref,record){
  const latest=data.sqlLatest[ref];
  return ['Source: Manual Override',`Cell: ${ref} / ${record.area}`,`Original SQL: ${formatSqlNumber(record.originalSql)}`,
   `Latest SQL: ${formatSqlNumber(latest)}`,`Manual Value: ${formatSqlNumber(record.value)}`,
   `Difference: ${formatSqlNumber(latest==null?null:Number(record.value)-Number(latest),{signDisplay:'exceptZero'})}`,
   `Modified: ${new Date(record.modifiedAt).toLocaleString()}`,record.modifiedBy?`Modified by: ${record.modifiedBy}`:null].filter(Boolean).join('\n');
 }
 function sqlIndicators(){
  for(const cell of table.querySelectorAll('[data-classification]')){
   const ref=cell.dataset.ref,record=data.sqlReplacements[ref],fromSql=sqlSource(ref);
   const override=manual(ref),editableSql=!recovered(ref)&&Boolean(override||fromSql)&&cell.tagName==='TD';
   cell.dataset.templateTitle??=cell.title;
   cell.setAttribute('aria-readonly',String(!canEditCell(cell)));
   cell.toggleAttribute('data-manual-formula-override',Object.hasOwn(data.formulas,ref)&&!controlled(ref)&&!record);
   if(fromSql&&!override)cell.dataset.sqlApplied='true';
   else delete cell.dataset.sqlApplied;
   if(override)cell.dataset.sqlManual='true';else delete cell.dataset.sqlManual;
   let input=cell.querySelector('input[data-ref]');
   if(editableSql&&!input){
    input=document.createElement('input');
    input.dataset.ref=ref;input.dataset.sqlInput='true';
    input.setAttribute('aria-label',ref+' SQL value');
    cell.prepend(input);
   }else if(!editableSql&&input?.dataset.sqlInput){
    input.remove();input=null;
   }
   if(input){
    input.readOnly=!canEditCell(cell);
    if(document.activeElement!==input)input.value=inputValue(ref);
   }
   cell.title=override?manualTitle(ref,override):controlled(ref)?ref+(data.sqlTargets[ref]==='coop-registered-accounts'?' / SQL-controlled COOP Consumption':' / SQL-controlled Total KWH Sold'):
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
  const response=await fetch('base-report.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action,month:data.month,csrf:data.csrf,hash:data.hash,revision:data.revision,workingId:data.workingId,snapshotRevision:data.snapshotRevision,values:data.values,formulas:data.formulas,sqlReplacements:data.sqlReplacements,sqlSources:data.sqlSources,preview:data.preview,total:data.total,previewToken:data.previewToken,...extra})});
  if(action==='export'&&response.ok)return response.blob();
  const result=await response.json();if(!response.ok||result.error)throw Error(result.error||'Request failed');return result;
 }
 function renderCalculation(result,inputs=false,preserveActive=false){
  applyEmptyRowVisibility(result.cells);
  if(result.recoveredSales)data.recoveredSales=result.recoveredSales;
  for(const [ref,c] of Object.entries(result.cells)){
   const cell=table.querySelector(`[data-classification][data-ref="${ref}"]`);if(!cell||cell.tagName==='TH')continue;
   cell.querySelector('.value').textContent=c.display;
   cell.toggleAttribute('data-calculation-error',Boolean(c.error));
   const input=inputs&&cell.querySelector('input');if(input){
    input.readOnly=!canEditCell(cell);
    if(!preserveActive||input.readOnly||document.activeElement!==input)input.value=inputValue(ref);
   }
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
  if(recovered(ref))return false;
  if(manual(ref)||sqlSource(ref))return true;
  if(controlled(ref)||Object.hasOwn(data.sqlReplacements,ref))return false;
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
  restoreSqlButton.hidden=!manual(ref);
  document.querySelector('#cell-kind').textContent=manual(ref)?'Manually overridden SQL value':recovered(ref)?'Recovered Sales workbook':isControlled?'SQL-controlled value':
   sql?'SQL-applied value ? template formula preserved':sqlSource(ref)?'SQL-applied value':metadata.classification;
  bar.value=manual(ref)||sqlSource(ref)||recovered(ref)?inputValue(ref):isControlled?'#N/A':sql?sql.value:(data.formulas[ref]??formula??inputValue(ref));
  bar.readOnly=!canEditCell(cell);
  if(inspect&&formula&&selectedRowLabel)selectRowLabel(null);
  refreshFormulaReferences();
 }
 table.addEventListener('focusin',e=>{
  const cell=e.target.closest('[data-classification]');
  if(cell)select(cell,!e.target.closest('.report-row-toggle'));
 });
 function focusGridCell(cell){
  if(!cell.hasAttribute('tabindex'))cell.tabIndex=-1;
  cell.focus({preventScroll:true});
  const bounds=cell.getBoundingClientRect(),frame=viewport.getBoundingClientRect();
  const top=frame.top+table.tHead.offsetHeight;
  const left=cell.classList.contains('particulars')?frame.left:
   Math.max(frame.left,cell.parentElement.querySelector('.particulars')?.getBoundingClientRect().right??frame.left);
  const dx=bounds.left<left?bounds.left-left:Math.max(0,bounds.right-frame.left-viewport.clientWidth);
  const dy=cell.closest('thead')?0:bounds.top<top?bounds.top-top:Math.max(0,bounds.bottom-frame.top-viewport.clientHeight);
  viewport.scrollBy({left:dx,top:dy,behavior:'instant'});
 }
 table.addEventListener('mousedown',event=>{
  const cell=event.target.closest(gridCellSelector);
  if(event.button!==0||!cell||event.target.closest('button')||
     (event.target.matches('input')&&event.target===document.activeElement))return;
  event.preventDefault();
  focusGridCell(cell);
 });
 function recalculate(){
  const version=++sequence;
  dirty=true;statusDetail.textContent='Recalculating…';
  pending=pending.catch(()=>{}).then(async()=>{
   if(version!==sequence)return;
   const result=await request('calculate');
   if(version!==sequence)return;
   applyManualState(result);sqlIndicators();
   renderCalculation(result);
   calculationFailed=false;statusDetail.classList.remove('error');
  }).catch(e=>{
   if(version!==sequence)return;
   calculationFailed=true;statusDetail.textContent=e.message;statusDetail.classList.add('error');
  });
  return pending;
 }
 function editableGridInput(input){
  return input.matches('td input[data-ref]:enabled')&&!input.readOnly&&canEditCell(input.closest('td'));
 }
 function commitGridInput(input){
  if(!editableGridInput(input))return;
  const ref=input.dataset.ref;
  failedEdits.delete(ref);
  if(input.value===String(inputValue(ref)))return;
  if(manual(ref)||sqlSource(ref)){
   if(pendingManual.get(ref)===input.value)return;
   const content=input.value;
   pendingManual.set(ref,content);
   return commitSqlManual('edit-sql-manual',ref,{content}).finally(()=>{
    if(pendingManual.get(ref)===content)pendingManual.delete(ref);
   });
  }
  setManualValue(input.dataset.ref,input.value);
  if(selected===input.closest('td'))select(selected);
  recalculate();
 }
 function handleGridKey(event,cell,commit,cancel,arrows=false){
  handleWorksheetEditKey(event,{
   cell,commit,cancel,arrows,
   candidates:()=>table.querySelectorAll(gridCellSelector),
   coordinates:cell=>{
    const {row,column}=data.metadata[cell.dataset.ref],end=data.metadata[cell.dataset.excelRangeEnd];
    return {row,column,rowEnd:end?.row??row+cell.rowSpan-1,columnEnd:end?.column??column+cell.colSpan-1};
   },
   focus:focusGridCell
  });
 }
 function updatePendingInput(){
  const input=document.activeElement;
  pendingInput=input===bar
   ?editingRef!==null&&bar.value!==String(activeFormula(editingRef)??inputValue(editingRef))
   :input.matches('td input[data-ref]')&&editableGridInput(input)&&input.value!==String(inputValue(input.dataset.ref));
  renderReportStatus();
 }
 for(const field of [table,bar]){
  field.addEventListener('input',updatePendingInput);
  field.addEventListener('focusout',()=>queueMicrotask(updatePendingInput));
  field.addEventListener('keydown',event=>{
   if(event.key==='Enter'||event.key==='Escape')queueMicrotask(updatePendingInput);
  });
 }
 table.addEventListener('change',e=>commitGridInput(e.target));
 table.addEventListener('focusout',e=>{
  if(e.target.matches('input[data-ref]'))commitGridInput(e.target);
 });
 table.addEventListener('keydown',e=>{
  const target=e.target,cell=target.closest(gridCellSelector);
  if(!cell)return;
  if(target!==cell&&!target.matches('input[data-ref]')){
   if(target.matches('.report-row-toggle')&&(e.key==='Tab'||e.key.startsWith('Arrow')))handleGridKey(e,cell,()=>{},()=>{},true);
   return;
  }
  if(target===cell&&!e.isComposing&&!e.ctrlKey&&!e.metaKey&&!e.altKey&&
     (e.key==='F2'||e.key.length===1)){
   if(beginGridEdit(cell,e.key==='F2'?undefined:e.key))e.preventDefault();
   return;
  }
  const editing=editableGridInput(target);
  handleGridKey(e,cell,()=>{if(editing)commitGridInput(target);},()=>{
   if(!editing)return;
   failedEdits.delete(cell.dataset.ref);
   target.value=inputValue(cell.dataset.ref);
   focusGridCell(cell);
  },!editing);
  if(editing&&e.defaultPrevented&&document.activeElement===target&&['Enter','Tab'].includes(e.key))focusGridCell(cell);
 });
 function setWorksheetUnlocked(unlocked){
  worksheetUnlocked=unlocked;
  showUnlockState(unlocked);
  if(selected)select(selected);
 }
 function clearManualHistory(keepSql=false){
  manualHistory.clear(keepSql?command=>command.kind==='sql':undefined);
  updateUndoButton();
 }
 function updateUndoButton(){
  undoFormula.hidden=!manualHistory.peek('undo');
 }
 function undoCellEdit(command){
  return command.kind==='sql'?commitSqlManual('undo-sql-manual',command.ref,{editToken:command.after})
   :commitFormula(command.ref,undefined,command.after);
 }
 function commitSqlManual(action,ref,extra={}){
  const version=++sequence;
  pending=pending.catch(()=>{}).then(async()=>{
   const guard=action==='edit-sql-manual'?{overrideId:manual(ref)?.id??null}:{};
   const result=await request(action,{ref,...guard,...extra});
   applyManualState(result);
   if(action==='undo-sql-manual')manualHistory.commit('undo');
   else if(result.editToken)manualHistory.record({kind:'sql',ref,before:null,after:result.editToken});
   updateUndoButton();
   sqlIndicators();
   if(version===sequence)renderCalculation(result.calculation,true,true);
   const input=table.querySelector(`td[data-ref="${ref}"] input[data-ref]`);
   if(input&&document.activeElement!==input&&(!pendingManual.has(ref)||pendingManual.get(ref)===extra.content))input.value=inputValue(ref);
   if(selected&&!editingRef)select(selected);
   statusDetail.classList.remove('error');
   if(result.changed){if(data.savedAt)dirty=true;workingModified=true;statusDetail.textContent='';renderReportStatus();}
  }).catch(error=>{
   if(action==='edit-sql-manual'&&pendingManual.get(ref)===extra.content)failedEdits.set(ref,extra.content);
   const input=table.querySelector(`td[data-ref="${ref}"] input[data-ref]`);
   if(input&&failedEdits.has(ref)&&document.activeElement!==input)input.value=failedEdits.get(ref);
   if(selected?.dataset.ref===ref&&!editingRef)select(selected);
   statusDetail.textContent=error.message+(action==='edit-sql-manual'?' Select the cell and press F2 to revise your entry.':'');statusDetail.classList.add('error');
  });
  return pending;
 }
 function beginGridEdit(cell,replacement){
  if(!cell||!canEditCell(cell)||loading||saving||exporting)return false;
  if(selected!==cell)select(cell,true);
  const ref=cell.dataset.ref,input=cell.querySelector('input[data-ref]');
  const editor=input??bar;
  const value=replacement??pendingManual.get(ref)??failedEdits.get(ref)??(input?inputValue(ref):bar.value);
  editor.focus({preventScroll:true});
  editor.value=value;
  editor.setSelectionRange(editor.value.length,editor.value.length);
  if(editor===bar)editingRef=ref;
  return true;
 }
 table.addEventListener('dblclick',e=>beginGridEdit(e.target.closest('td[data-ref]')));
 function commitFormula(ref,content,editToken){
  const version=++sequence;
  pending=pending.catch(()=>{}).then(async()=>{
   const result=await request(editToken?'undo-formula':'edit-formula',{ref,content,editToken});
   data.formulas=result.formulas;
   data.formulaAudit=result.formulaAudit;
   if(editToken)manualHistory.commit('undo');
   else if(result.editToken)manualHistory.record({ref,before:null,after:result.editToken});
   updateUndoButton();
   if(result.changed){dirty=true;setRestoreRecovery(null);}
   sqlIndicators();
   if(version===sequence)renderCalculation(result.calculation);
   if(selected&&!editingRef)select(selected,true);
   statusDetail.classList.remove('error');
   if(dirty){statusDetail.textContent='';renderReportStatus();}
  }).catch(e=>{
   if(!editToken)failedEdits.set(ref,content);
   if(e.message.startsWith('Unlock Formulas'))setWorksheetUnlocked(false);
   statusDetail.textContent=e.message+(editToken?'':' Select the cell and press F2 to revise your entry.');
   statusDetail.classList.add('error');
  });
  return pending;
 }
 function commitBarEdit(){
  if(!editingRef)return;
  const ref=editingRef,content=bar.value;
  editingRef=null;
  failedEdits.delete(ref);
  const cell=table.querySelector('td[data-ref="'+ref+'"]');
  if(!cell||!canEditCell(cell))return;
  if(cell.hasAttribute('data-original-formula-editable')&&!manual(ref)&&!sqlSource(ref)){
   if(content!==(data.formulas[ref]??activeFormula(ref)??inputValue(ref)))return commitFormula(ref,content);
   return;
  }
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
  if(e.key==='Tab'&&!editingRef)return;
  handleGridKey(e,cell,commitBarEdit,()=>{editingRef=null;failedEdits.delete(cell.dataset.ref);focusGridCell(cell);});
  if(e.defaultPrevented&&document.activeElement===bar&&['Enter','Tab'].includes(e.key))focusGridCell(cell);
 });
 unlock.addEventListener('click',async()=>{
  if(changingLock||loading||saving||exporting)return;
  changingLock=true;
  try{
   if(!worksheetUnlocked&&!await confirmAction('This will allow manual editing of all Excel-based formula cells in the current reporting worksheet.',{formulaUnlock:true}))return;
   await pending;
   const result=await request(worksheetUnlocked?'lock-formulas':'unlock');
   setWorksheetUnlocked(result.unlocked);
   statusDetail.classList.remove('error');
  }catch(e){statusDetail.textContent=e.message;statusDetail.classList.add('error');}
  finally{changingLock=false;}
 });
 undoFormula.addEventListener('click',()=>{
  const command=manualHistory.peek('undo');
  if(command&&!loading&&!saving&&!exporting)void undoCellEdit(command);
 });
 restoreSqlButton.addEventListener('click',async()=>{
  if(loading||saving||exporting)return;
  const ref=selected?.dataset.ref,month=data.month;
  await pending;
  const record=manual(ref),latest=data.sqlLatest[ref];
  if(month!==data.month||!record)return;
  if(latest==null){
   statusDetail.textContent='No valid SQL value is available. The manual value was kept.';
   statusDetail.classList.add('error');return;
  }
  const detail=`Current manual value: ${formatSqlNumber(record.value)}\nLatest available SQL value: ${formatSqlNumber(latest)}`;
  if(await confirmAction('This will discard the manual override and restore the latest value retrieved from the database.',{restoreSql:true,detail})&&
     data.month===month&&!loading&&!saving&&!exporting)await commitSqlManual('restore-sql-value',ref,{overrideId:record.id});
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
  commitBarEdit();
  if(document.activeElement?.matches('td input[data-ref]'))commitGridInput(document.activeElement);
  saving=true;saveButton.disabled=true;saveButton.setAttribute('aria-busy','true');
  statusDetail.textContent='Saving draft…';statusDetail.classList.remove('error');
  // Use the same queue as recalculation so edits made during a draft write
  // calculate against the new revision once the write has finished.
  const write=pending.catch(()=>{}).then(async()=>{
   const version=sequence;
   statusDetail.textContent='Saving draft…';
   const result=await request('save');
   data.revision=result.revision;
   data.workingId=result.workingId;
   data.snapshotRevision=result.snapshotRevision;
   data.savedAt=result.savedAt;
   saveFailed=false;
   if(version===sequence){dirty=false;workingModified=false;pendingInput=false;}
  }).catch(e=>{saveFailed=true;statusDetail.textContent='Save failed. '+e.message;statusDetail.classList.add('error');});
  pending=write;
  try{await write;}
  finally{saving=false;saveButton.disabled=false;saveButton.removeAttribute('aria-busy');renderReportStatus();}
 });
 // Reviewed SQL values enter the same live draft and Save Draft workflow.
 async function refreshRecoveredSales(month){
  await pending;
  if(month!==data.month)throw Error('The reporting month changed.');
  const result=await request('calculate',{refreshRecoveredSales:true});
  if(month!==data.month||!result.recoveredSales||!result.cells)throw Error('The report returned an incomplete summary.');
  applyManualState(result);sqlIndicators();
  renderCalculation(result,true);
  if(data.savedAt)dirty=true;
  workingModified=true;statusDetail.textContent='';renderReportStatus();
  if(selected)select(selected);
 }
 const coopSyncStatus=document.querySelector('#coop-sync-status');
 function displayCoopSync(){
  coopSyncStatus.textContent=data.coopSyncWarning??'';
 }
 function soldTotalUnavailable(){
  return Object.entries(data.total).some(([ref,value])=>value===null&&!manual(ref)&&data.sqlLatest[ref]==null);
 }
 function refreshCoop(){
  if(data.savedAt)return Promise.resolve();
  const month=data.month;
  pending=pending.catch(()=>{}).then(async()=>{
   if(month!==data.month)return;
   const result=await request('calculate',{refreshTotal:true});
   if(month!==data.month)return;
   for(const key of ['total','sqlTargets','configuredRows','previewToken','coopSyncWarning'])data[key]=result[key];
   applyManualState(result);
   displayCoopSync();sqlIndicators();renderCalculation(result,true,true);
   if(selected&&!editingRef)select(selected);
  }).catch(()=>{
   coopSyncStatus.textContent='COOP sync failed. Last displayed values were kept.';
  });
  return pending;
 }
 window.genstatReport={confirmAction,refreshRecoveredSales,refreshCoop,notifySettings:notifyReport,notifyRecoveredSalesSaved:()=>notifyReport('Recovered Sales saved and GENSTAT summary updated successfully.'),snapshot:()=>({month:data.month,csrf:data.csrf,hash:data.hash,revision:data.revision,workingId:data.workingId,snapshotRevision:data.snapshotRevision,values:structuredClone(data.values),formulas:structuredClone(data.formulas),sqlReplacements:structuredClone(data.sqlReplacements),sqlSources:structuredClone(data.sqlSources)}),
  previewContext:()=>({preview:data.preview,total:data.total,previewToken:data.previewToken}),
  disableSqlLabels(rowKey){setRestoreRecovery(null);
   for(const [ref,key] of Object.entries(data.sqlTargets))if(key===rowKey)delete data.sqlTargets[ref];
   sqlIndicators();sizeNumericColumns();if(selected)select(selected);
  },
  applySqlResult(result){setRestoreRecovery(null);
   applyManualState(result);
   const configuredRow=Number(result.record?.template_row_key?.match(/\/A(\d+)$/)?.[1]);
   if(configuredRow&&!data.configuredRows.includes(configuredRow))data.configuredRows.push(configuredRow);
   data.values=result.values??data.values;data.formulas=result.formulas??data.formulas;
   data.sqlReplacements=result.sqlReplacements??data.sqlReplacements;
   data.sqlSources=result.sqlSources??data.sqlSources;
   dirty=true;
   statusDetail.textContent='SQL values applied · Unsaved changes';
   if(result.previewContext){
    const coopTargets=Object.fromEntries(Object.entries(data.sqlTargets).filter(([,source])=>source==='coop-registered-accounts'));
    Object.assign(data,result.previewContext);
    Object.assign(data.sqlTargets,coopTargets);
    sqlIndicators();renderCalculation(result.calculation,true);if(selected)select(selected);
    return;
   }
   sqlIndicators();
   const refreshTotal=!data.savedAt&&Boolean(result.record?.template_row_key.endsWith('/A18'));
   if(refreshTotal||Object.keys(data.preview).length||Object.keys(data.total).length){
    const version=++sequence;
    pending=pending.catch(()=>{}).then(()=>request('calculate',{refreshTotal})).then(calculation=>{
     if(version===sequence){
      applyManualState(calculation);
      data.total=calculation.total;data.previewToken=calculation.previewToken;
      data.sqlTargets=calculation.sqlTargets??data.sqlTargets;sqlIndicators();
      data.configuredRows=calculation.configuredRows??data.configuredRows;
      if(Object.hasOwn(calculation,'coopSyncWarning')){data.coopSyncWarning=calculation.coopSyncWarning;displayCoopSync();}
      renderCalculation(calculation,true);if(selected)select(selected);
      if(soldTotalUnavailable())statusDetail.textContent='Total KWH Sold is unavailable.';
     }
    }).catch(e=>{statusDetail.textContent=e.message;statusDetail.classList.add('error');});
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
  setLoading(true,data.month,undo?'Recovering previous formulas…':'Refreshing SQL values and restoring report formulas…');
  restoreButton.disabled=true;
  restoreButton.setAttribute('aria-busy','true');
  confirmationButton.disabled=true;
  try{
   await pending;
   statusDetail.classList.remove('error');
   statusDetail.textContent=undo?'Recovering previous formulas…':'Restoring report…';
   const result=await request(undo?'undo-restore':'restore-report',undo?{undoToken:recovery.token}:{});
   if(result.month!==data.month||!result.calculation?.cells||!result.values||!result.formulas||!result.sqlReplacements||!result.sqlSources||!result.preview||!result.total||(!undo&&!result.sqlTargets))throw Error('The restoration response is incomplete.');
   const {calculation,undoToken,restoredSqlCells,...state}=result;
   Object.assign(data,state);
   displayCoopSync();
   dirty=Boolean(data.savedAt);calculationFailed=false;
   setRestoreRecovery(undoToken?{token:undoToken}:null);
   const restored=new Set(restoredSqlCells);
   manualHistory.clear(command=>command.kind==='sql'&&!restored.has(command.ref));
   updateUndoButton();
   for(const [ref,content] of failedEdits)if(restored.has(ref)||data.metadata[ref]?.effectiveFormula)failedEdits.delete(ref);
   sqlIndicators();
   renderCalculation(calculation,true);
   if(selected)select(selected);
   statusDetail.classList.remove('error');
   workingModified=true;pendingInput=false;
   notifyReport(undo?(data.savedAt?'Previous formulas restored in the working report.':'Previous report formulas recovered and saved.'):'Report formulas and SQL values restored successfully.');
  }catch(e){
   statusDetail.classList.add('error');
   statusDetail.textContent=undo?'Undo Restore failed':'Report restore failed';
   notifyReport((undo?'Recovery failed. Your current report has been preserved.':'Restoration failed. Your previous report has been preserved.')+' '+e.message,true);
  }finally{
   setLoading(false);
   restoreButton.disabled=false;
   confirmationButton.disabled=false;
   restoreButton.removeAttribute('aria-busy');
   (undo&&restoreRecovery?undoRestore:restoreButton).focus({preventScroll:true});
  }
 }
 restoreButton.addEventListener('click',async()=>{
  if(loading||saving||exporting)return;
  const month=data.month;
  const message=data.savedAt?'This will restore the original report formulas and retrieve the latest SQL and Coop Consumption values. Your previously saved monthly report will remain unchanged until you click Save.':'This will restore the original Excel template formulas, refresh all configured SQL values, and reapply custom report formulas for the selected reporting period.';
  if(!await confirmAction(message,{restore:true,detail:formatPeriod(month)}))return;
  if(loading||saving||exporting||data.month!==month)return;
  notifyReport('');
  await rebuildReport();
 });
 undoRestore.addEventListener('click',()=>{
  if(restoreRecovery&&!loading&&!saving&&!exporting)void rebuildReport(true);
 });
 document.addEventListener('keydown',e=>{
  if(!(e.ctrlKey||e.metaKey)||e.altKey||e.shiftKey||e.key.toLowerCase()!=='z'||e.target.closest('dialog, textarea, [contenteditable="true"]')||loading||saving||exporting)return;
  const manual=manualHistory.peek('undo');
  if(e.target.matches('input:not([readonly])')&&
     (manual?.kind!=='sql'||(e.target===bar?editingRef!==null:
     !e.target.matches('td input[data-ref]')||e.target.value!==String(inputValue(e.target.dataset.ref)))))return;
  if(!manual&&!restoreRecovery)return;
  e.preventDefault();
  if(manual)void undoCellEdit(manual);
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
  loading=active;overlay.hidden=!active;renderReportStatus();
  if(active){document.querySelector('#loading-title').textContent=title;document.querySelector('#loading-period').textContent=formatPeriod(target);}
  viewport.setAttribute('aria-busy',String(active));
  for(const section of content)section.inert=active;
  for(const control of [yearSelector,monthSelector,exportButton,saveButton,unlock,document.querySelector('#sql-settings-open'),document.querySelector('#recovered-sales-open')])control.disabled=active;
 }
 async function loadPeriod(target){
  if(loading)return;
  if(!target||target===data.month||saving||exporting||settings.open){syncSelectors(data.month);return;}
  if((dirty||failedEdits.size)&&!await confirmAction('Discard unsaved report changes and switch reporting periods?')){syncSelectors(data.month);return;}
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
   failedEdits.clear();calculationFailed=false;
   displayCoopSync();
   syncSelectors(target);
   selectRowLabel(null);
   setWorksheetUnlocked(false);clearManualHistory();selected?.classList.remove('selected');selected=null;restoreSqlButton.hidden=true;
   document.querySelector('#selection').textContent='Select a cell';document.querySelector('#cell-kind').textContent='Formula cells protected';
   highlightColumn(null);
   bar.value='';bar.readOnly=true;setRestoreRecovery(null);notifyReport('');
   dirty=false;sqlIndicators();renderCalculation(result.calculation,true);
   const label=formatPeriod(target);periodLabel.textContent=label;table.setAttribute('aria-label',label+' KWH purchased, sold and system losses');
   const url=new URL(location.href);url.searchParams.set('month',target);history.replaceState(null,'',url);
   workingModified=false;saveFailed=false;pendingInput=false;statusDetail.textContent='';
   statusDetail.classList.remove('error');failedMonth=null;
  }catch(e){
   if(version!==loadSequence)return;
   syncSelectors(data.month);failedMonth=target;retry.hidden=false;
   statusDetail.textContent='Could not load '+formatPeriod(target)+'. '+e.message;statusDetail.classList.add('error');
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
   statusDetail.textContent='';statusDetail.classList.remove('error');
  }catch(e){statusDetail.textContent=e.message;statusDetail.classList.add('error');}
  finally{exporting=false;exportButton.disabled=false;}
 });
 addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
})();
