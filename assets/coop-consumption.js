'use strict';
(()=>{
 const byId=id=>document.getElementById(id);
 const dialog=byId('coop-dialog'),report=window.genstatReport;
 const enrollment=byId('coop-enrollment'),connection=byId('coop-connection'),search=byId('coop-search');
 const apply=byId('coop-apply'),refresh=byId('coop-refresh'),status=byId('coop-status');
 const connectionFields=connection.querySelector('fieldset'),password=connection.elements.password,passwordToggle=byId('coop-password-toggle');
 let connectionInfo=null,testExpires=0,testTimer;
 let meta=null,preview=null,previewState=null,busy=false,expiryTimer;
 const connectionSection=byId('coop-admin'),enrollmentSection=byId('coop-enrollment-section'),connectionHelp=byId('coop-connection-help');
 const context=()=>({...report.snapshot(),...report.previewContext()});
 const currentState=()=>JSON.stringify(context());
 function message(text,error=false){
  status.textContent=text;
  status.hidden=!text;
  status.classList.toggle('text-red-700',error);
  status.classList.toggle('dark:text-red-300',error);
 }
 function buttons(){
  connectionFields.disabled=busy||!connectionInfo?.editable||!window.isSecureContext||location.protocol!=='https:';
  byId('coop-test').disabled=!connectionInfo?.testEnabled;
  byId('coop-connection-save').disabled=testExpires<=Date.now()/1000;
  byId('coop-connection-remove').disabled=!connectionInfo?.configured;
  refresh.disabled=busy||!(meta?.ready||meta?.localPreviewReady);
  byId('coop-enroll').disabled=busy||!meta?.enrollmentReady;
  apply.disabled=busy||!meta?.owned||meta.conflict||!preview?.complete||preview.provisional===true||preview.expiresAt<=Date.now()/1000||previewState!==currentState();
 }
 async function api(action,extra={}){
  await report.ready();
  const r=await fetch('coop-consumption-api.php',{
   method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({...context(),action,...extra})
  });
  const payload=await r.json();
  if(!r.ok||payload.error&&!Object.hasOwn(payload,'complete')){
   const error=Error(payload.error||'The COOP request failed.');error.status=r.status;throw error;
  }
  byId('coop-development').hidden=payload.developmentMode!==true;
  return payload;
 }
 async function operation(fn){
  if(busy)return;
  busy=true;dialog.setAttribute('aria-busy','true');
  byId('coop-working').hidden=false;buttons();
  try{await fn();}catch(error){message(error.message,true);}
  finally{busy=false;dialog.removeAttribute('aria-busy');byId('coop-working').hidden=true;buttons();}
 }
 function table(id,headings,rows){
  const container=byId(id);
  container.replaceChildren();
  if(!rows.length){container.textContent='No records.';return;}
  const table=document.createElement('table'),head=table.createTHead().insertRow(),body=table.createTBody();
  table.className='min-w-full border-collapse text-left text-xs';
  for(const label of headings){
   const th=document.createElement('th');th.scope='col';th.textContent=label;
   th.className='border-b border-slate-300 p-2 dark:border-slate-600';head.append(th);
  }
  for(const values of rows){
   const row=body.insertRow();
   for(const value of values){
    const cell=row.insertCell();
    cell.className='whitespace-nowrap border-b border-slate-200 p-2 dark:border-slate-600';
    if(value instanceof Node)cell.append(value);
    else cell.textContent=value??'--';
   }
  }
  container.append(table);
 }
 function invalidate(){
  preview=null;previewState=null;clearTimeout(expiryTimer);
  for(const id of ['coop-preview','coop-areas','coop-total'])byId(id).textContent='';
  byId('coop-preview-status').textContent='Pending verification';
  byId('coop-preview-policy').hidden=true;
  buttons();
 }
 function kwh(value){
  if(value==null||value==='')return 'Unavailable';
  const [whole,fraction='']=String(value).split('.');
  return whole+'.'+fraction.replace(/0+$/,'').padEnd(2,'0')+' KWH';
 }
 function billingStatus(row){
  if(!row)return 'Pending verification';
  if(row.status==='Retrieved')return 'Verified';
  if(row.status==='No Bill History')return 'No billing data';
  if(row.status==='Connection Failed')return 'Retrieval failed';
  return row.reason&&row.status==='Requires billing review'?row.status+' — '+row.reason:row.status;
 }
 function accountRows(){
  if(!meta)return;
  const filter=search.value.toLowerCase();
  const consumption=new Map((preview?.accounts||[]).map(item=>[item.id,item]));
  table('coop-accounts',['Area','AcctCode','From','To (exclusive)','Enrollment Status','Month KWH','Billing Status','Actions'],
   meta.accounts.filter(row=>[row.acct_code,meta.areas[row.area_key]?.label].join(' ').toLowerCase().includes(filter)).map(row=>{
    const actions=document.createElement('div');actions.className='flex gap-2';
    for(const action of ['deactivate','reassign']){
     const button=document.createElement('button');button.type='button';
     button.textContent=action==='deactivate'?'Deactivate':'Reassign';
     button.addEventListener('click',async()=>{
      const values=Object.fromEntries(new FormData(enrollment));
      const verb=action==='reassign'?'Reassign to '+enrollment.elements.area.selectedOptions[0]?.textContent:'Deactivate';
      if(!await report.confirmAction(verb+' from '+values.effective+'? Historical membership will be retained.'))return;
      await operation(async()=>{
       const result=await api(action,{id:row.id,area:values.area,effective:values.effective});
       invalidate();await load();message(result.message);
      });
     });
     actions.append(button);
    }
    const bill=consumption.get(String(row.id));
    return [meta.areas[row.area_key]?.label??'Mapping review required',row.acct_code,row.effective_from,row.effective_to,row.status,bill?.kwh==null?billingStatus(bill):kwh(bill.kwh),billingStatus(bill),actions];
   }));
 }
 async function load(){
  meta=await api('view');
  byId('coop-period').textContent='Reporting Period: '+meta.monthLabel;
  const options=Object.entries(meta.areas).map(([key,area])=>new Option(area.label,key));
  enrollment.elements.area.replaceChildren(...options);
  enrollment.elements.effective.value=context().month;
  accountRows();
  byId('coop-last-success').textContent='Last successful refresh: '+(meta.lastSuccess??'None');
  byId('coop-preview-period').textContent=meta.monthLabel;
  byId('coop-last-refresh').textContent=meta.history[0]?.created_at??'None';
  byId('coop-preview-connection').textContent=connectionInfo?.status??(meta.enrollmentReady?'Available':'Configuration required');
  table('coop-history',['Attempt','Status','Accounts','Applied'],meta.history.map(row=>[row.created_at,row.status,row.account_count,row.applied_at]));
  byId('coop-report').hidden=false;
  byId('coop-enrollment-status').textContent=meta.enrollmentReady?'Ready':'Configuration Required';
  byId('coop-billing-status').textContent=meta.ready?'Ready':'Pending Review';
  if(meta.conflict)message('An active SQL configuration owns this row. Resolve it in SQL Settings before applying.',true);
  else message('');
 }
 function showPassword(visible){
  password.type=visible?'text':'password';
  passwordToggle.setAttribute('aria-pressed',String(visible));
  passwordToggle.title=visible?'Hide password':'Show password';
  passwordToggle.setAttribute('aria-label',passwordToggle.title);
  byId('coop-password-slash').toggleAttribute('hidden',!visible);
 }
 function clearConnectionTest(){
  testExpires=0;clearTimeout(testTimer);buttons();
 }
 function connectionStatus(state){
  byId('coop-connection-status').textContent=state.status;
  byId('coop-last-test-success').textContent='Last Successful Test: '+(state.lastSuccess??'None');
  byId('coop-last-test-attempt').textContent='Last Test Attempt: '+(state.lastAttempt??'None');
 }
 async function loadConnection(){
  try{connectionInfo=await api('connection-view');}
  catch(error){
   if(error.status!==403)throw error;
   connectionStatus({status:'Configuration Requires Attention'});
   connectionHelp.textContent='Trusted administrator authentication is required to manage the connection.';
   return;
  }
  connectionSection.hidden=false;
  for(const [key,value] of Object.entries(connectionInfo.connection))connection.elements[key].value=value;
  const {host,port,database}=connectionInfo.connection;
  byId('coop-source').textContent=`${connectionInfo.sourceType} source · ${host} · Port ${port} · ${database}`;
  password.value='';showPassword(false);clearConnectionTest();
  connectionStatus(connectionInfo);
  byId('coop-preview-connection').textContent=connectionInfo.status;
  if(connectionInfo.developmentMode&&(location.protocol!=='https:'||!window.isSecureContext))
   connectionHelp.textContent='Local development access enabled. Configure trusted HTTPS to test or save database credentials.';
  else if(!connectionInfo.editable||location.protocol!=='https:'||!window.isSecureContext)
   connectionHelp.textContent='Configuration required. Use trusted HTTPS and configure the protected secret store.';
  else if(!connectionInfo.testEnabled)connectionHelp.textContent='Connection testing is disabled by deployment.';
  else connectionHelp.textContent='Test the submitted settings before saving.';
 }
 function expandSettings(){
  connectionSection.open=Boolean(connectionInfo&&!connectionInfo.configured);
  enrollmentSection.open=!connectionSection.open;
 }
 connection.addEventListener('input',clearConnectionTest);
 passwordToggle.addEventListener('click',()=>showPassword(password.type==='password'));
 window.addEventListener('genstat-coop-open',()=>{
  meta=null;connectionInfo=null;clearConnectionTest();invalidate();
  byId('coop-period').textContent='';
  byId('coop-report').hidden=true;connectionSection.hidden=true;
  message('Loading COOP settings...');
  dialog.showModal();void operation(async()=>{await load();await loadConnection();expandSettings();});
 });
 byId('coop-close').addEventListener('click',()=>{if(!busy)dialog.close();});
 dialog.addEventListener('cancel',event=>{if(busy)event.preventDefault();});
 dialog.addEventListener('close',()=>{
  invalidate();clearConnectionTest();password.value='';showPassword(false);byId('sql-settings-open').focus();
 });
 search.addEventListener('input',accountRows);
 enrollment.addEventListener('submit',event=>{
  event.preventDefault();
  void operation(async()=>{
   const result=await api('enroll',Object.fromEntries(new FormData(enrollment)));
   enrollment.elements.acctCode.value='';invalidate();await load();message(result.message);
  });
 });
 connection.addEventListener('submit',event=>{
  event.preventDefault();
  if(connectionFields.disabled||testExpires<=Date.now()/1000)return;
  const submitted=Object.fromEntries(new FormData(connection));
  void operation(async()=>{
   const result=await api('connection-save',{connection:submitted});
   invalidate();await load();await loadConnection();expandSettings();message(result.message);
  });
 });
 byId('coop-test').addEventListener('click',()=>{
  if(connectionFields.disabled||!connectionInfo?.testEnabled||!connection.reportValidity())return;
  const submitted=Object.fromEntries(new FormData(connection));
  clearConnectionTest();
  void operation(async()=>{
   try{
    const result=await api('connection-test',{connection:submitted});
    testExpires=result.expiresAt;
    testTimer=setTimeout(clearConnectionTest,Math.max(0,testExpires*1000-Date.now()));
    message(result.message);
   }catch(error){
    const state=await api('connection-view');
    connectionStatus({...state,status:'Connection Failed'});
    throw error;
   }
   const state=await api('connection-view');
   connectionStatus({...state,status:'Connection Successful'});
  });
 });
 byId('coop-connection-remove').addEventListener('click',async()=>{
  if(connectionFields.disabled||!connectionInfo?.configured)return;
  if(!await report.confirmAction('Remove the saved COOP connection? Enrollment, history and report values will be retained.'))return;
  await operation(async()=>{
   const result=await api('connection-remove',{confirmed:true});
   invalidate();await load();await loadConnection();expandSettings();message(result.message);
  });
 });
 refresh.addEventListener('click',()=>void operation(async()=>{
  invalidate();await report.ready();
  const month=context().month,state=currentState(),result=await api(meta.ready?'refresh':'local-preview');
  if(context().month!==month){accountRows();throw Error('Reporting month changed. Refresh the selected month.');}
  await load();
  preview=result;previewState=state;
  accountRows();
  const codes=new Map(meta.accounts.map(row=>[String(row.id),row.acct_code]));
  table('coop-preview',['Area','AcctCode','BillMonth','Billing Records','KWH','Status'],
   result.accounts.map(row=>[row.area,codes.get(row.id),month,row.count,kwh(row.kwh),billingStatus(row)]));
  const issues=new Map();
  for(const row of result.accounts){
   if(row.kwh!==null)continue;
   if(!issues.has(row.area))issues.set(row.area,new Set());
   issues.get(row.area).add(billingStatus(row));
  }
  table('coop-areas',['Area','Enrolled Accounts','Verified Accounts','Current Summary KWH','Proposed COOP KWH','Difference','Status'],
   Object.values(result.areas).map(row=>[row.label,row.enrolled,row.retrieved,kwh(row.current),kwh(row.kwh),kwh(row.difference),
    row.error||(row.missing?[...(issues.get(row.label)??[])].join(', '):row.enrolled?(result.provisional?'Provisional':'Ready'):'No enrollment')]));
  byId('coop-total').textContent='Total COOP Consumption: '+kwh(result.total);
  byId('coop-preview-status').textContent=result.complete?(result.provisional?'Provisional preview ready':'Ready for review'):result.error?'Retrieval failed':'Incomplete';
  byId('coop-preview-policy').hidden=!result.provisional;
  expiryTimer=setTimeout(()=>{invalidate();accountRows();message('Preview expired. Refresh to retrieve current results.');},Math.max(0,result.expiresAt*1000-Date.now()));
  message(result.complete?(result.provisional?'Provisional preview ready.':'Preview ready for review.'):result.error||'Preview is incomplete. Review the account statuses.',!result.complete);
 }));
 apply.addEventListener('click',async()=>{
  buttons();if(apply.disabled)return;
  const count=Object.values(preview.areas).filter(area=>area.kwh!==null).length;
  const confirmation=meta.monthLabel+' / '+preview.accountCount+' accounts / '+count+' areas and cells / '+preview.total+
    ' KWH. Replace these COOP inputs and recalculate dependent Summary formulas (including area subtotals and COOP totals)?';
  if(!await report.confirmAction(confirmation))return;
  await operation(async()=>{
   if(previewState!==currentState())throw Error('The report changed. Refresh the preview.');
   const result=await api('apply',{run:preview.id,confirmed:true});
   report.applyCoopResult(result);invalidate();await load();message(result.message);
  });
 });
})();
