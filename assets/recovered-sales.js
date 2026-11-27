'use strict';
(() => {
 const dialog=document.querySelector('#recovered-sales');
 const open=document.querySelector('#recovered-sales-open');
 const fields=document.querySelector('#recovered-sales-fields');
 const rows=document.querySelector('#recovered-sales-rows');
 const template=document.querySelector('#recovered-sales-row');
 const title=document.querySelector('#recovered-sales-title');
 const period=document.querySelector('#recovered-sales-period');
 const status=document.querySelector('#recovered-sales-status');
 const empty=document.querySelector('#recovered-sales-empty');
 const add=document.querySelector('#recovered-sales-add');
 const save=document.querySelector('#recovered-sales-save');
 const totals=[...dialog.querySelectorAll('[data-recov-total]')];
 const closeButtons=[...dialog.querySelectorAll('[data-close-recovered]')];
 const report=window.genstatReport;
 let context,revision,monthLabel,areas=[],maxRows=0,deleted=[],dirty=false,busy=false,pendingSync=false;

 function cents(value){
  const parts=/^(\d{1,10}|\d{1,3}(?:,\d{3}){1,3})(?:\.(\d{1,2}))?$/.exec(value.trim());
  if(!parts)return null;
  const whole=parts[1].replaceAll(',','');
  return whole.length>10?null:BigInt(whole)*100n+BigInt((parts[2]||'').padEnd(2,'0'));
 }

 function decimal(value){
  return (value/100n).toLocaleString('en-US')+'.'+String(value%100n).padStart(2,'0');
 }

 function formatInput(input){
  const amount=cents(input.value);
  if(amount!==null)input.value=decimal(amount);
 }

 function refresh(){
  let usedTotal=0n,paidTotal=0n,valid=true;
  [...rows.children].forEach((row,index)=>{
   const usedInput=row.querySelector('[data-field="used"]');
   const paidInput=row.querySelector('[data-field="paid"]');
   const usedError=row.querySelector('[data-error="used"]');
   const paidError=row.querySelector('[data-error="paid"]');
   const used=cents(usedInput.value),paid=cents(paidInput.value);
   usedError.textContent=used===null?'Enter a nonnegative amount with up to 2 decimal places.':'';
   paidError.textContent=paid===null?'Enter a nonnegative amount with up to 2 decimal places.':
    used!==null&&paid>used?'Cannot exceed KWH Used.':'';
   for(const [input,error,key] of [[usedInput,usedError,'used'],[paidInput,paidError,'paid']]){
    error.id='recov-'+key+'-error-'+index;
    input.setAttribute('aria-describedby',error.id);
    input.setAttribute('aria-invalid',String(Boolean(error.textContent)));
   }
   const rowValid=!usedError.textContent&&!paidError.textContent;
   valid=valid&&rowValid;
   row.querySelector('[data-remaining]').textContent=rowValid?decimal(used-paid):'—';
   if(rowValid){usedTotal+=used;paidTotal+=paid;}
   const labels={area:'Area',used:'KWH Used',paid:'KWH Paid',remarks:'Remarks'};
   for(const field of row.querySelectorAll('[data-field]'))field.setAttribute('aria-label',labels[field.dataset.field]+' for row '+(index+1));
  });
  [usedTotal,paidTotal,usedTotal-paidTotal].forEach((amount,index)=>{totals[index].textContent=valid?decimal(amount):'—';});
  empty.hidden=rows.children.length>0;
  add.disabled=busy||rows.children.length>=maxRows;
  save.disabled=busy||(!dirty&&!pendingSync)||!valid;
  save.textContent=pendingSync&&!dirty?'Retry Summary':'Save Changes';
 }

 function append(entry){
  const fragment=template.content.cloneNode(true);
  const row=fragment.firstElementChild;
  if(entry.id)row.dataset.id=entry.id;
  const area=row.querySelector('select');
  for(const option of areas)area.add(new Option(option,option));
  if(!areas.includes(entry.area))area.add(new Option(entry.area+' (legacy)',entry.area));
  for(const input of row.querySelectorAll('[data-field]'))input.value=entry[input.dataset.field];
  for(const input of row.querySelectorAll('input[inputmode="decimal"]'))formatInput(input);
  rows.append(fragment);
  return row;
 }

 function setBusy(value){
  busy=value;fields.disabled=value;
  for(const button of closeButtons)button.disabled=value;
  refresh();
 }

 async function request(action,extra={}){
  const response=await fetch('base-report.php',{method:'POST',headers:{'Content-Type':'application/json'},
   body:JSON.stringify({...context,action,...extra})});
  const data=await response.json();
  if(!response.ok)throw Error(data.error||'Recovered sales request failed.');
  return data;
 }

 async function close(){
  if(busy)return;
  if(dirty&&!await report.confirmAction('Discard unsaved recovered sales changes?'))return;
  dialog.close();
 }

 open.addEventListener('click',async()=>{
  const {month,csrf,hash,workingId,snapshotRevision}=report.snapshot();
  context={month,csrf,hash,workingId,snapshotRevision};
  dirty=false;pendingSync=false;deleted=[];rows.replaceChildren();fields.hidden=true;
  period.textContent=document.querySelector('#report-period').textContent+' · RecovBill';
  status.textContent='Loading recovered sales…';
  setBusy(true);dialog.showModal();title.focus();
  try{
   const result=await request('recovered-sales-load');
   revision=result.revision;areas=result.areas;maxRows=result.maxRows;
   for(const record of result.records)append(record);
   monthLabel=result.monthLabel;
   period.textContent=monthLabel+' · RecovBill';
   fields.hidden=false;status.textContent='';
  }catch(error){status.textContent=error.message;}
  finally{setBusy(false);}
 });

 for(const button of closeButtons)button.addEventListener('click',()=>void close());
 dialog.addEventListener('cancel',event=>{event.preventDefault();void close();});
 dialog.addEventListener('close',()=>open.focus());
 add.addEventListener('click',()=>{
  if(rows.children.length>=maxRows)return;
  const row=append({area:areas[0],used:'',paid:'0.00',remarks:''});
  dirty=true;refresh();row.querySelector('select').focus();
 });
 rows.addEventListener('input',()=>{dirty=true;refresh();});
 rows.addEventListener('focusin',({target})=>{
  if(!target.matches('input[inputmode="decimal"]')||!target.value.includes(',')||cents(target.value)===null)return;
  const {value,selectionStart,selectionEnd,selectionDirection}=target;
  const position=index=>value.slice(0,index).replaceAll(',','').length;
  target.value=value.replaceAll(',','');
  target.setSelectionRange(position(selectionStart),position(selectionEnd),selectionDirection);
 });
 rows.addEventListener('focusout',({target})=>{
  if(target.matches('input[inputmode="decimal"]'))formatInput(target);
 });
 rows.addEventListener('click',async event=>{
  const button=event.target.closest('[data-delete-recovered]');
  if(!button||!await report.confirmAction('Delete this recovered sales entry?'))return;
  const row=button.closest('tr');
  if(row.dataset.id)deleted.push(row.dataset.id);
  const nextFocus=row.nextElementSibling?.querySelector('select')||row.previousElementSibling?.querySelector('select')||add;
  row.remove();dirty=true;refresh();nextFocus.focus();
 });
 save.addEventListener('click',async()=>{
  if(save.disabled)return;
  const records=[...rows.children].map(row=>({
   id:row.dataset.id||null,
   ...Object.fromEntries([...row.querySelectorAll('[data-field]')].map(field=>[field.dataset.field,field.value]))
  }));
  let saved=false;
  const confirmed=await report.confirmAction(
   dirty?'Are you sure you want to save the recovered-sales breakdown for '+monthLabel+'?':
    'Refresh the GENSTAT Summary from the saved breakdown for '+monthLabel+'?',
   {recoverySave:{
    totals:[...totals.map(total=>total.textContent),records.length],
    persist:async()=>{
     setBusy(true);
     status.textContent='Saving recovered sales…';
     try{
      if(dirty){
       const result=await request('recovered-sales-save',{revision,records,deleted});
       revision=result.revision;deleted=[];dirty=false;pendingSync=true;
       rows.replaceChildren();
       for(const record of result.records)append(record);
      }
      await report.refreshRecoveredSales(context.month);
      pendingSync=false;
      saved=true;
     }catch(error){
      status.textContent=(pendingSync&&!dirty?'Recovered Sales was saved. Summary refresh failed. ':'')+error.message;
     }
     finally{setBusy(false);}
    }
   }}
  );
  if(saved){
   dialog.close();
   report.notifyRecoveredSalesSaved();
  }else if(confirmed)status.focus();
 });
 window.addEventListener('beforeunload',event=>{
  if(!dirty||!dialog.open)return;
  event.preventDefault();event.returnValue='';
 });
})();
