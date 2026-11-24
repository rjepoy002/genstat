'use strict';
(()=>{
 const byId=id=>document.getElementById(id);
 const dialog=byId('sql-settings-dialog'),fields=byId('coop-fields');
 const connection=byId('coop-connection'),query=byId('coop-query'),status=byId('coop-status');
 const type=connection.querySelector('[name=type]'),apiKey=byId('coop-api-key'),keyToggle=byId('coop-api-key-toggle');
 const report=window.genstatReport;
 let state=null,busy=false,connectionTest=false,queryTest=false,generation=0;
 const accounts=registeredAccounts({byId,request,report,tableHead});

 function feedback(message,error=false){
  status.textContent=message;status.dataset.error=String(error);
 }
 function updateButtons(){
  fields.disabled=busy||!state;
  byId('coop-save-connection').disabled=!connectionTest;
  byId('coop-test-query').disabled=!state?.saved||state.connection.type!==type.value||type.value==='https';
  byId('coop-save-query').disabled=!queryTest;
  byId('coop-hosted-help').hidden=type.value!=='hosted';
  for(const element of fields.querySelectorAll('[data-coop-direct]'))element.hidden=type.value==='https';
  for(const element of fields.querySelectorAll('[data-coop-https]'))element.hidden=type.value!=='https';
  fields.setAttribute('aria-busy',String(busy));
 }
 function connectionInput(){
  return Object.fromEntries([...connection.querySelectorAll('[name]')].filter(input=>!input.closest('[hidden]'))
   .map(input=>[input.name,input.type==='checkbox'?input.checked:input.value]));
 }
 async function request(action,extra={}){
  const {month,csrf}=report.snapshot();
  const response=await fetch('coop-phase1-api.php',{method:'POST',headers:window.genstatSettings.headers(),
   body:JSON.stringify({action,month,csrf,...extra})});
  window.genstatSettings.onResponse(response);
  const result=await response.json();
  if(!response.ok)throw Error(result.error||'The COOP request failed.');
  return result;
 }
 function tableHead(table,labels){
  const heading=table.createTHead().insertRow();
  for(const label of labels){
   const header=document.createElement('th');
   header.scope='col';header.textContent=label;heading.append(header);
  }
  return table.createTBody();
 }
 function showKey(visible){
  apiKey.type=visible?'text':'password';
  keyToggle.setAttribute('aria-pressed',String(visible));
  keyToggle.setAttribute('aria-label',visible?'Hide API key':'Show API key');
 }
 function applyConnection(profile){
  for(const name of ['type','host','port','database','username','apiUrl']){
   const input=connection.querySelector('[name="'+name+'"]');
   if(Object.hasOwn(profile,name))input.value=profile[name];
  }
  connection.querySelector('[name=password]').value='';
  connection.querySelector('[name=emptyPassword]').checked=false;
  byId('coop-password-state').textContent=profile.hasPassword?'A password is stored. Leave blank to keep it.':'No password is stored.';
  apiKey.value='';apiKey.placeholder=profile.hasApiKey?'••••••••':'';
  showKey(false);
 }
 function applyMetadata(data){
  state=data;connectionTest=queryTest=false;
  applyConnection(data.connection);
  query.value=data.query;
 }
 function connectionFeedback(result){
  feedback(result.apiVersion?result.message+' API Version: '+result.apiVersion+'. Hosted Database: Connected.':result.message);
 }
 async function operation(label,work){
  if(busy)return;
  const token=generation;
  busy=true;updateButtons();feedback(label);
  try{await work(()=>token===generation);}
  catch(error){
   if(token===generation){
    feedback(error.message,true);
   }
  }
  finally{if(token===generation){busy=false;updateButtons();}}
 }
 byId('settings-coop-tab').addEventListener('click',()=>{
  if(busy)return;
  state=null;updateButtons();
  accounts.open();
  operation('Loading COOP settings…',async current=>{
   const data=await request('metadata');
   if(!current())return;
   applyMetadata(data);
   feedback(data.saved?'Connection settings loaded.':'COOP storage is ready. Enter and test a database connection.');
  });
 });
 connection.addEventListener('input',()=>{connectionTest=false;updateButtons();});
 type.addEventListener('change',()=>{
  connectionTest=queryTest=false;
  const saved=state?.profiles[type.value];
  if(saved)applyConnection(saved);
  else{
   const selected=type.value;
   const suggestedUrl=connection.querySelector('[name=apiUrl]').defaultValue;
   applyConnection({type:selected,host:selected==='local'?'127.0.0.1':'',port:3306,database:'',
    username:selected==='local'?'root':'',apiUrl:suggestedUrl});
  }
  updateButtons();
 });
 keyToggle.addEventListener('click',()=>showKey(apiKey.type==='password'));
 query.addEventListener('input',()=>{queryTest=false;updateButtons();});
 byId('coop-test-connection').addEventListener('click',()=>operation('Testing connection…',async current=>{
  connectionTest=false;
  const result=await request('test-connection',{connection:connectionInput()});
  if(current()){connectionTest=true;connectionFeedback(result);}
 }));
 byId('coop-save-connection').addEventListener('click',async()=>{
  if(!connectionTest||busy)return;
  if(state.saved&&!await report.confirmAction('Replace the saved COOP connection?'))return;
  operation('Saving connection…',async current=>{
   const result=await request('save-connection',{connection:connectionInput(),revision:state.revision,confirmed:true});
   const metadata=await request('metadata');
   if(current()){
    applyMetadata(metadata);accounts.refresh();
    void report.refreshCoop();
    if(current())connectionFeedback(result);
   }
  });
 });
 byId('coop-test-query').addEventListener('click',()=>operation('Testing the monthly query…',async current=>{
  queryTest=false;
  await request('test-query',{query:query.value});
  void report.refreshCoop();
  if(current()){queryTest=true;feedback('Query passed for the selected month.');}
 }));
 byId('coop-save-query').addEventListener('click',async()=>{
  if(!queryTest||busy)return;
  if(state.query&&!await report.confirmAction('Replace the saved COOP consumption query?'))return;
  operation('Saving query…',async current=>{
   const result=await request('save-query',{query:query.value,revision:state.revision,confirmed:true});
   if(!current())return;
   state.query=query.value;state.revision=result.revision;queryTest=false;
   feedback(result.message);
  });
 });
 dialog.addEventListener('close',()=>{
  accounts.close();
  generation++;state=null;busy=false;connectionTest=queryTest=false;
  connection.querySelector('[name=password]').value='';
  apiKey.value='';showKey(false);
  updateButtons();
 });
})();
