'use strict';
(()=>{
 const $=id=>document.getElementById(id),dialog=$('sql-settings-dialog'),report=window.genstatReport;
 let meta=null,current=null,dirty=false,busy=false,testGrant=null,history=[],testedResult=null,testTimer=null,testedSql=null,validationIssues=[],recoveryOperation=null;
 function message(text,error=false){$('sql-message').textContent=text;$('sql-message').dataset.error=String(error);}
 const rowSelect=$('sql-row'),rowTrigger=$('sql-row-trigger'),rowValue=$('sql-row-value'),rowOptions=$('sql-row-options');
 let activeOption=-1;
 const configuredColor='font-medium text-emerald-600 dark:text-emerald-400';
 const defaultColor='font-normal text-slate-700 dark:text-slate-300';
 function configurationStatus(key=current?.key){
  const record=meta?.records[key];
  return record?(Number(record.enabled)?'Configured':'Disabled'):'Not Configured';
 }
 function renderRowPicker(){
  const rows=meta?.rows??[];
  rowOptions.replaceChildren(...rows.map((row,index)=>{
   const status=configurationStatus(row.key);
   const option=document.createElement('div');
   option.id='sql-row-option-'+index;
   option.dataset.index=index;
   option.setAttribute('role','option');
   option.setAttribute('aria-selected',String(row.key===rowSelect.value));
   option.className='cursor-pointer rounded-md px-2 py-2 hover:bg-slate-100 aria-selected:bg-slate-100 data-[active]:bg-slate-200 dark:hover:bg-[var(--gs-hover,#334155)] dark:aria-selected:bg-[var(--gs-header,#334155)] dark:data-[active]:bg-[var(--gs-hover,#475569)] '+(status==='Configured'?configuredColor:defaultColor);
   option.textContent=row.label+' / '+status;
   return option;
  }));
  const index=rowSelect.selectedIndex;
  const picked=rowOptions.children[index];
  rowValue.textContent=picked?.textContent??'Choose a report row';
  rowValue.className='min-w-0 whitespace-normal '+(configurationStatus()==='Configured'?configuredColor:defaultColor);
  rowTrigger.disabled=!rows.length;
 }
 function positionRowOptions(){
  if(rowOptions.hidden)return;
  const anchor=rowTrigger.getBoundingClientRect(),modal=dialog.getBoundingClientRect();
  if(anchor.bottom<=modal.top||anchor.top>=modal.bottom){closeRowPicker();return;}
  const gap=4,below=innerHeight-anchor.bottom-gap*2,above=anchor.top-gap*2;
  const openAbove=below<Math.min(192,rowOptions.scrollHeight)&&above>below;
  rowOptions.style.width=anchor.width+'px';
  rowOptions.style.left=anchor.left+'px';
  rowOptions.style.maxHeight=Math.max(0,Math.min(258,openAbove?above:below))+'px';
  rowOptions.style.top=(openAbove?anchor.top-gap-rowOptions.offsetHeight:anchor.bottom+gap)+'px';
 }
 function highlightRowOption(index){
  rowOptions.children[activeOption]?.removeAttribute('data-active');
  activeOption=Math.max(0,Math.min(index,rowOptions.children.length-1));
  const option=rowOptions.children[activeOption];
  option.setAttribute('data-active','');
  rowTrigger.setAttribute('aria-activedescendant',option.id);
  option.scrollIntoView({block:'nearest'});
 }
 function openRowPicker(index=rowSelect.selectedIndex){
  if(busy||rowTrigger.disabled)return;
  rowOptions.hidden=false;
  rowOptions.showPopover?.();
  rowTrigger.setAttribute('aria-expanded','true');
  positionRowOptions();
  if(!rowOptions.hidden)highlightRowOption(index);
 }
 function closeRowPicker(){
  if(rowOptions.hidden)return;
  rowOptions.hidePopover?.();
  rowOptions.hidden=true;
  rowOptions.children[activeOption]?.removeAttribute('data-active');
  activeOption=-1;
  rowTrigger.setAttribute('aria-expanded','false');
  rowTrigger.removeAttribute('aria-activedescendant');
 }
 function chooseRowOption(index){
  closeRowPicker();
  rowTrigger.focus({preventScroll:true});
  if(index===rowSelect.selectedIndex)return;
  rowSelect.selectedIndex=index;
  rowSelect.dispatchEvent(new Event('change',{bubbles:true}));
 }
 rowTrigger.addEventListener('click',()=>rowOptions.hidden?openRowPicker():closeRowPicker());
 rowTrigger.addEventListener('keydown',event=>{
  const {key}=event,open=!rowOptions.hidden;
  if(key==='Tab'){closeRowPicker();return;}
  if(key==='Escape'){
   if(open){event.preventDefault();event.stopPropagation();closeRowPicker();}
   return;
  }
  if(!['ArrowDown','ArrowUp','Home','End','Enter',' '].includes(key))return;
  event.preventDefault();
  const last=rowOptions.children.length-1;
  if(!open){openRowPicker(key==='Home'?0:key==='End'?last:rowSelect.selectedIndex);return;}
  if(key==='Enter'||key===' '){chooseRowOption(activeOption);return;}
  highlightRowOption(key==='Home'?0:key==='End'?last:activeOption+(key==='ArrowDown'?1:-1));
 });
 rowOptions.addEventListener('mousedown',event=>{if(event.target.closest('[role="option"]'))event.preventDefault();});
 rowOptions.addEventListener('click',event=>{
  const option=event.target.closest('[role="option"]');
  if(option)chooseRowOption(Number(option.dataset.index));
 });
 document.addEventListener('pointerdown',event=>{
  if(!rowTrigger.contains(event.target)&&!rowOptions.contains(event.target))closeRowPicker();
 });
 rowTrigger.addEventListener('blur',event=>{if(!rowOptions.contains(event.relatedTarget))closeRowPicker();});
 dialog.addEventListener('scroll',positionRowOptions,{passive:true});
 window.addEventListener('resize',positionRowOptions);
 dialog.addEventListener('close',closeRowPicker);
 function config(){const bindings={};for(const select of $('sql-bindings').querySelectorAll('select'))bindings[select.dataset.ref]={areaId:select.value,sacode:select.parentElement.querySelector('input').value.trim()};return {rowKey:$('sql-row').value,sql:$('sql-editor').value,areaColumn:$('sql-area-column').value,valueColumn:$('sql-value-column').value,sacodeColumn:$('sql-sacode-column').value,bindings,excluded:$('sql-excluded').value.split(',').map(x=>x.trim()).filter(Boolean),autoMatchColumn:$('sql-auto-column').value,matchingMethod:$('sql-match-method').value,mappingSources:Object.fromEntries(Array.from($('sql-bindings').querySelectorAll('select')).map(x=>[x.dataset.ref,x.dataset.source??'saved'])),formulaExpression:$('sql-calculation').value,formulaEnabled:$('sql-formula-enabled').checked};}

 function warnFixedMonth(){
  const sql=$('sql-editor').value;
  $('sql-month-warning').hidden=!/\b[1-9]\d{3}(?:0[1-9]|1[0-2])\b/.test(sql);
 }
 function invalidateTest(){testGrant=null;clearTimeout(testTimer);}
 function changed(){
  warnFixedMonth();dirty=true;invalidateTest();validationIssues=[];$('sql-formula-message').textContent='';
  if(testedSql!==$('sql-editor').value){testedSql=null;testedResult=null;fillTable($('sql-preview'),[],[]);$('sql-column-tokens').replaceChildren();}
  else if(testedResult)showResult(testedResult);
  $('sql-editor-state').textContent='Unapplied changes. Test Query before applying.';updateActions();
 }
 function updateActions(){
  const expired=testGrant&&Date.now()>=testGrant.expiresAt*1000;
  $('sql-apply').disabled=busy||!testGrant||Boolean(expired);
  $('sql-preview-formula').disabled=busy||!testedResult;
  $('sql-apply-reason').textContent=busy?'An operation is in progress.':expired?'Test expired. Run Test Query again.':!testGrant?(validationIssues[0]||'Test the SQL query first.'):'Ready to Apply & Save.';
  $('sql-apply-reason').dataset.ready=String(!$('sql-apply').disabled);
 }
 async function api(action,extra={}){const response=await fetch('query-settings-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...report.snapshot(),action,...extra})});let result;try{result=await response.json();}catch{throw Error('The server did not return a valid settings response.');}if(!response.ok||result.error)throw Error(result.error||'SQL settings request failed.');return result;}
 async function operation(fn){if(busy)return;closeRowPicker();busy=true;$('sql-fields').disabled=true;for(const id of ['sql-test','sql-history','sql-history-load','sql-disable-config','sql-recover','sql-settings-close','sql-preview-formula'])$(id).disabled=true;updateActions();dialog.setAttribute('aria-busy','true');try{await fn();}catch(e){message(e.message,true);}finally{busy=false;$('sql-fields').disabled=false;for(const id of ['sql-test','sql-history','sql-history-load','sql-recover'])$(id).disabled=!meta;$('sql-settings-close').disabled=false;$('sql-history-load').disabled=!history.length;$('sql-disable-config').disabled=!meta?.records[current?.key]||!Number(meta?.records[current?.key]?.enabled);dialog.removeAttribute('aria-busy');updateActions();}}
 function fillTable(container,columns,rows){container.replaceChildren();if(!rows.length){const empty=document.createElement('p');empty.className='sql-empty';empty.textContent='No applicable rows to display.';container.append(empty);return;}const table=document.createElement('table'),thead=document.createElement('thead'),tr=document.createElement('tr');for(const column of columns){const th=document.createElement('th');th.textContent=column;tr.append(th);}thead.append(tr);table.append(thead);const tbody=document.createElement('tbody');for(const row of rows){const tr=document.createElement('tr');for(const col of columns){const td=document.createElement('td');td.textContent=row[col]===null?'NULL':String(row[col]??'');const numeric=typeof row[col]==='number'||typeof row[col]==='string'&&/^[+-]?(?:\d[\d,]*)(?:\.\d+)?$/.test(row[col]);td.dataset.numeric=String(numeric);tr.append(td);}tbody.append(tr);}table.append(tbody);container.append(table);}
 function list(id,items){$(id).replaceChildren();for(const text of items){const li=document.createElement('li');li.textContent=text;$(id).append(li);}}
 function resetPreview(){$('sql-duration').textContent='';fillTable($('sql-preview'),[],[]);$('sql-preview').firstChild.textContent='Test your approved query to see its results. No workbook values will change.';$('sql-diagnostics').replaceChildren();$('sql-history-panel').hidden=true;$('sql-history').setAttribute('aria-expanded','false');}
 function populateRow(key,override=null){
  current=meta.rows.find(r=>r.key===key);if(!current)return;const record=meta.records[key],c=override??(record?{queryKey:record.query_key,sql:record.sql_text,areaColumn:record.area_column,valueColumn:record.value_column,sacodeColumn:record.sacode_column,bindings:record.bindings,excluded:record.excluded,enabled:Boolean(Number(record.enabled)),allowFormulaReplacement:Boolean(Number(record.allow_formula_replacement??0)),...JSON.parse(record.calculation_settings_json??'{}')}:null);
  $('sql-row').value=key;$('sql-editor').value=c?.sql??'';warnFixedMonth();$('sql-area-column').value=c?.areaColumn??'AreaID';$('sql-value-column').value=c?.valueColumn??'';$('sql-sacode-column').value=c?.sacodeColumn??'';$('sql-excluded').value=(c?.excluded??[]).join(', ');
  history=[];testedResult=null;testedSql=null;validationIssues=[];$('sql-column-tokens').replaceChildren();$('sql-formula-message').textContent='';$('sql-mapping-message').textContent='';
  $('sql-calculation').value=c?.formulaExpression??'';$('sql-formula-enabled').checked=c?.formulaEnabled??false;
  $('sql-formula-row').textContent=current.label;
  $('sql-result-columns').replaceChildren();
  $('sql-formula-preset').value='';
  for(const option of $('sql-formula-preset').options)option.disabled=Boolean(option.value);
  $('sql-match-method').value=c?.matchingMethod??'normalized';
  $('sql-auto-column').replaceChildren(new Option(c?.autoMatchColumn??'Test query to choose a column',c?.autoMatchColumn??''));
  $('sql-approved-note').hidden=!current.approvedSqlInputRow;
  current={...current,cells:{...current.cells}};if(current.approvedSqlInputRow)for(const [ref,cell] of Object.entries(current.protectedCells??{}))if(cell.replacementEligible)current.cells[ref]=cell;
  $('sql-bindings').replaceChildren();for(const [ref,cell] of Object.entries(current.cells)){const label=document.createElement('label');label.className='settings-label sql-mapping-item';label.append(document.createTextNode(`${cell.label} · ${ref}`));const select=document.createElement('select');select.className='settings-input';select.dataset.ref=ref;select.setAttribute('aria-label',`${ref} AreaID`);const empty=document.createElement('option');empty.value='';empty.textContent='Choose verified AreaID';select.append(empty);for(const area of meta.areas){const option=document.createElement('option');option.value=area.AreaID;option.textContent=`${area.AreaID} · ${area.AreaName}`;select.append(option);}select.value=c?.bindings?.[ref]?.areaId??'';select.dataset.source=c?.mappingSources?.[ref]??(c?.bindings?.[ref]?'saved':'auto');const sa=document.createElement('input');sa.className='settings-input';sa.placeholder='SACode (only if used)';sa.maxLength=2;sa.setAttribute('aria-label',`${ref} SACode`);sa.value=c?.bindings?.[ref]?.sacode??'';sa.hidden=!$('sql-sacode-column').value;const status=document.createElement('span');status.className='sql-mapping-status';status.dataset.mappingStatus='';status.textContent=select.value?'Previously saved':'Unmatched';status.dataset.state=status.textContent;
  const reset=document.createElement('button');reset.type='button';reset.className='ml-2 text-xs';reset.textContent='Reset to Auto';reset.addEventListener('click',()=>{select.dataset.source='auto';select.value='';sa.value='';changed();if(testedResult)operation(autoMatch);else{status.textContent='Unmatched';status.dataset.state=status.textContent;}});
  const manual=()=>{select.dataset.source='manual';status.textContent='Manually mapped';status.dataset.state=status.textContent;};select.addEventListener('change',manual);sa.addEventListener('input',manual);
  label.append(select,sa,status,reset);$('sql-bindings').append(label);}
  // Suggest only an explicitly verified alias when the existing binding is absent.
  for(const select of $('sql-bindings').querySelectorAll('select'))if(!select.value&&current.cells[select.dataset.ref].suggestedAreaId)select.value=current.cells[select.dataset.ref].suggestedAreaId;
  for(const [ref,cell] of Object.entries(current.protectedCells??{})){if(!cell.suggestedAreaId||current.cells[ref])continue;const area=meta.areas.find(a=>String(a.AreaID)===cell.suggestedAreaId);const note=document.createElement('div');note.className='rounded border border-slate-200 bg-slate-50 p-2 text-xs';note.dataset.protectedRef=ref;note.textContent=`${cell.label} · ${ref} → ${cell.suggestedAreaId} · ${area?.AreaName??''}. ${cell.reason}${cell.formula?' '+cell.formula:''}. Not a writable SQL target.`;$('sql-bindings').append(note);}
  const columnIndex=element=>{const ref=element.dataset.protectedRef??element.querySelector('select').dataset.ref;return ref.match(/^[A-Z]+/)[0].split('').reduce((n,c)=>n*26+c.charCodeAt(0)-64,0);};
  for(const element of Array.from($('sql-bindings').children).sort((a,b)=>columnIndex(a)-columnIndex(b)))$('sql-bindings').append(element);
  $('sql-row-status').textContent=configurationStatus();renderRowPicker();$('sql-editor-state').textContent=override?'Historical settings loaded as unapplied changes. Test the SQL again.':'No unapplied changes.';resetPreview();invalidateTest();updateActions();dirty=Boolean(override);message(override?'Historical settings loaded. Test and Apply & Save to commit them.':'Select a report row, test its SQL, complete mappings, then Apply & Save.');
 }
 function showRecovery(operation){recoveryOperation=operation;$('sql-recovery-panel').hidden=!operation;}
 async function loadMetadata(preferredKey=null){meta=await api('metadata');$('sql-context').textContent=`${meta.monthLabel} / :ReportMonth = ${meta.month} / read-only preview`;rowSelect.replaceChildren(...meta.rows.map(row=>new Option(row.label,row.key)));showRecovery(meta.recoverableOperation);if(meta.rows.length)populateRow(meta.rows.some(r=>r.key===preferredKey)?preferredKey:meta.rows[0].key);else{renderRowPicker();message('No eligible visible report input rows.');}}
 $('sql-settings-open').addEventListener('click',async()=>{
  if(dialog.open)return;
  invalidateTest();meta=null;renderRowPicker();dialog.showModal();
  await operation(()=>loadMetadata());
  if(dialog.open&&!$('settings-reporting').hidden){rowTrigger.focus({preventScroll:true});dialog.scrollTop=0;}
 });
 async function close(){if(busy)return;if(dirty&&!await report.confirmAction('Discard unsaved SQL configuration changes?'))return;dialog.close();dirty=false;$('sql-settings-open').focus();}
 $('sql-settings-close').addEventListener('click',close);dialog.addEventListener('cancel',e=>{e.preventDefault();close();});
 $('sql-fields').addEventListener('input',e=>{if(!['sql-row','sql-history-select'].includes(e.target.id))changed();});$('sql-fields').addEventListener('change',e=>{if(!['sql-row','sql-history-select'].includes(e.target.id))changed();});
 $('sql-row').addEventListener('change',async()=>{const next=$('sql-row').value;if(dirty&&!await report.confirmAction('Discard unsaved SQL configuration changes for this row?')){$('sql-row').value=current.key;return;}populateRow(next);});
 function showResult(result,computed=null){testedResult=result;fillTable($('sql-preview'),[...result.columns,...(computed?['Computed Value']:[])],result.rows.map((r,i)=>computed?{...r,'Computed Value':computed[i]}:r));}
 function returnedColumns(result){
  const saved=$('sql-auto-column').value;$('sql-auto-column').replaceChildren(new Option('Choose matching column',''),...result.columns.map(c=>new Option(c,c)));
  $('sql-auto-column').value=result.columns.includes(saved)?saved:(result.columns.includes('AreaName')?'AreaName':'');
  $('sql-result-columns').replaceChildren(...result.columns.map(c=>new Option(c,c)));
  const numericColumns=result.numericColumns??[];
  $('sql-column-tokens').replaceChildren();for(const column of numericColumns){const button=document.createElement('button');button.type='button';button.className='text-xs';button.textContent=column;button.addEventListener('click',()=>{const input=$('sql-calculation'),token=/^[A-Za-z_][A-Za-z0-9_]*$/.test(column)?column:'['+column.replaceAll(']',']]')+']';input.setRangeText(token,input.selectionStart,input.selectionEnd,'end');input.focus();changed();});$('sql-column-tokens').append(button);}
  for(const option of $('sql-formula-preset').options)option.disabled=Boolean(option.value)&&option.value.split(/ \+ /).some(c=>!numericColumns.includes(c));
  if(Array.from($('sql-formula-preset').options).some(o=>o.disabled))$('sql-formula-message').textContent='Some presets require numeric columns that this query does not return. Choose an available preset or enter a custom formula.';
 }
 async function autoMatch(){
  if(!testedResult)return;
  const result=await api('auto-match',{config:config()});let auto=0,unmatched=0,ambiguous=0;
  for(const select of $('sql-bindings').querySelectorAll('select')){const ref=select.dataset.ref,b=result.bindings[ref],status=result.statuses[ref];if(select.dataset.source==='auto'){select.value=b?.areaId??'';select.parentElement.querySelector('input').value=b?.sacode??'';}
   const indicator=select.parentElement.querySelector('[data-mapping-status]');indicator.textContent=status??'Unmatched';indicator.dataset.state=indicator.textContent;if(status==='Auto-matched')auto++;if(status==='Unmatched')unmatched++;if(status==='Ambiguous')ambiguous++;}
  $('sql-mapping-message').textContent=`${auto} areas automatically matched. ${unmatched} areas require manual mapping. ${ambiguous} areas have ambiguous matches.`;
 }
 $('sql-auto-column').addEventListener('change',()=>operation(autoMatch));$('sql-match-method').addEventListener('change',()=>operation(autoMatch));
 $('sql-formula-preset').addEventListener('change',()=>{if($('sql-formula-preset').value){$('sql-calculation').value=$('sql-formula-preset').value;$('sql-formula-enabled').checked=true;changed();}});
 $('sql-preview-formula').addEventListener('click',()=>operation(async()=>{try{const result=await api('preview-formula',{config:config()});showResult(result,result.computedValues);$('sql-formula-message').textContent='Formula validated successfully. Formula preview generated. No workbook changes applied.';}catch(e){$('sql-formula-message').textContent=e.message;if($('sql-formula-enabled').checked)validationIssues=[e.message];throw e;}}));
 $('sql-test').addEventListener('click',()=>operation(async()=>{
  resetPreview();invalidateTest();testedSql=null;testedResult=null;validationIssues=[];message('Validating and executing a bounded read-only preview...');
  try{const inspection=await api('inspect-result',{config:config()});testedSql=$('sql-editor').value;showResult(inspection);returnedColumns(inspection);await autoMatch();
   const result=await api('test',{config:config()});
   validationIssues=result.validation.errors;list('sql-diagnostics',[...result.validation.errors,...result.validation.notes]);
   if(result.validationPassed&&result.testToken){
    testGrant={token:result.testToken,expiresAt:result.expiresAt};
    testTimer=setTimeout(updateActions,Math.max(0,testGrant.expiresAt*1000-Date.now()+20));
   }
   $('sql-duration').textContent=result.durationMs+' ms / '+result.rows.length+' rows';
   $('sql-editor-state').textContent=testGrant?'Query and mappings validated.':'Resolve validation errors before applying.';
   message(testGrant?'Test passed. Apply & Save is ready.':'Resolve the listed errors and test again.',!testGrant);
  }catch(e){validationIssues=[e.message];$('sql-editor-state').textContent='Validation requires attention.';list('sql-diagnostics',validationIssues);throw e;}
 }));
 $('sql-sacode-column').addEventListener('input',()=>{for(const input of $('sql-bindings').querySelectorAll('input'))input.hidden=!$('sql-sacode-column').value.trim();});
 $('sql-history').addEventListener('click',()=>operation(async()=>{const result=await api('history',{rowKey:current.key});history=result.history;$('sql-history-select').replaceChildren();for(const item of history){const option=document.createElement('option');option.value=item.id;option.textContent=`v${item.revision} / ${item.created_at} UTC / ${item.current?'Current':'Available'} / ${item.config.enabled?'Enabled':'Disabled'}`;$('sql-history-select').append(option);}$('sql-history-panel').hidden=false;$('sql-history').setAttribute('aria-expanded','true');$('sql-history-load').disabled=!history.length;message(history.length?'Restore a version into the editor, then test before Apply & Save.':'No saved configuration versions for this row.');}));
 $('sql-history-load').addEventListener('click',async()=>{if(dirty&&!await report.confirmAction('Discard unapplied settings and restore the selected configuration into the editor?'))return;const item=history.find(x=>String(x.id)===$('sql-history-select').value);if(item)populateRow(current.key,item.config);});
 $('sql-disable-config').addEventListener('click',async()=>{if(!await report.confirmAction('Disable this saved row configuration? Its SQL and history will be kept; report values will not change.'))return;operation(async()=>{const result=await api('disable',{rowKey:current.key,mappingRevision:Number(meta.records[current.key]?.revision??0),confirmed:true});meta.records[current.key]=result.record;report.disableSqlLabels(current.key);populateRow(current.key);message(result.message);});});
 const loadingDialog=$('sql-apply-loading');
 loadingDialog.addEventListener('cancel',event=>event.preventDefault());
 async function withApplyLoading(fn){
  $('sql-loading-period').textContent=meta.monthLabel;
  loadingDialog.showModal();
  try{await fn();}finally{loadingDialog.close();}
 }
 async function finishApplied(result){
  report.applySqlResult(result);
  await report.ready();
  const key=result.record.template_row_key;meta.records[key]=result.record;
  if(current.key!==key)populateRow(key);
  dirty=false;invalidateTest();validationIssues=[];
  $('sql-row-status').textContent=configurationStatus();
  renderRowPicker();
  $('sql-editor-state').textContent='Configuration saved. Applied values are pending Save Draft.';
  showRecovery({operation_id:result.operationId,template_row_key:key});
  message(result.message);
 }
 $('sql-apply').addEventListener('click',()=>operation(()=>withApplyLoading(async()=>{
  await report.ready();
  const operationId=testGrant.token;
  showRecovery({operation_id:operationId,template_row_key:current.key});
  try{
   await finishApplied(await api('apply',{config:config(),operationId,...report.previewContext()}));
  }catch(e){
   if(/Test Query again/.test(e.message)){invalidateTest();validationIssues=[e.message];}
   throw e;
  }
 })));
 $('sql-recover').addEventListener('click',async()=>{
  if(!recoveryOperation||!await report.confirmAction('Recover the last committed Apply & Save result into the current report? Other edits will not be overwritten.'))return;
  operation(()=>withApplyLoading(async()=>{
   await report.ready();
   await finishApplied(await api('recover',{operationId:recoveryOperation.operation_id,confirmed:true}));
  }));
 });
 addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
})();
