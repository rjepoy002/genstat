'use strict';
(()=>{
 const byId=id=>document.getElementById(id);
 const gear=byId('sql-settings-open'),settings=byId('sql-settings-dialog');
 const dialog=byId('settings-pin-dialog'),form=byId('settings-pin-form'),message=byId('settings-pin-message');
 const group=byId('settings-pin-inputs'),digits=[...group.querySelectorAll('input')],unlock=byId('settings-pin-unlock');
 const change=byId('settings-pin-change-dialog'),changeForm=byId('settings-pin-change-form'),changeMessage=byId('settings-pin-change-message');
 const report=window.genstatReport;
 const expiredMessage='Settings have been locked for security.';
 let token='',expires=0,lockedUntil=0,configured=false,busy=false,changeBusy=false,attempt=0,lastActivity=0;
 const pin=()=>digits.map(input=>input.value).join('');
 const clear=()=>digits.forEach(input=>{input.value='';});
 function context(action,extra={},authorization=token){
  const {csrf,month}=report.snapshot();
  return JSON.stringify({action,csrf,month,authorization,...extra});
 }
 async function request(action,extra={}){
  const response=await fetch('settings-security-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:context(action,extra)});
  return {status:response.status,data:await response.json()};
 }
 function revoke(authorization=token){
  if(!authorization)return;
  if(authorization===token){token='';expires=0;}
  const body=context('revoke',{},authorization);
  fetch('settings-security-api.php',{method:'POST',headers:{'Content-Type':'application/json'},body,keepalive:true}).catch(()=>{});
 }
 function lock(){
  if(!token&&!settings.open)return;
  revoke();
  if(change.open)change.close();
  if(settings.open)settings.close();
  gear.focus();
  report.notifySettings(expiredMessage);
 }
 window.genstatSettings={
  headers:()=>({'Content-Type':'application/json','X-Settings-Authorization':token}),
  onResponse(response){if(response.status===403&&token)lock();},
  async saveAppearance(){
   const {status,data}=await request('appearance');
   if(status!==200){if(status===403)lock();throw Error(data.error);}
   expires=data.expires;
  }
 };
 function update(){
  const seconds=Math.max(0,Math.ceil((lockedUntil-Date.now())/1000));
  for(const input of digits)input.disabled=busy||!configured||seconds>0;
  unlock.disabled=busy||!configured||seconds>0||pin().length!==6;
  for(const control of changeForm.elements)if(control.type!=='button')control.disabled=changeBusy||seconds>0;
  if(seconds){
   const countdown=Math.floor(seconds/60)+':'+String(seconds%60).padStart(2,'0');
   const target=change.open?changeMessage:message;
   if(target.closest('dialog').open)target.textContent='Too many attempts. Try again in '+countdown+'.';
  }else if(lockedUntil){
   lockedUntil=0;message.textContent='';changeMessage.textContent='';
   if(dialog.open&&configured&&!busy)digits[0].focus();
  }
 }
 function showFailure(data,target){
  if(data.lockedFor)lockedUntil=Date.now()+data.lockedFor*1000;
  target.textContent=[data.error,Number.isInteger(data.remaining)&&!data.lockedFor?'Remaining attempts: '+data.remaining+'.':''].filter(Boolean).join(' ');
  update();
 }
 gear.addEventListener('click',async()=>{
  if(dialog.open||settings.open)return;
  const opening=++attempt;
  clear();configured=false;lockedUntil=0;message.textContent='Checking Settings protection…';
  dialog.showModal();update();
  try{
   const {status,data}=await request('state');
   if(opening!==attempt||!dialog.open)return;
   configured=status===200&&data.configured===true;
   message.textContent=configured?(data.remaining<5?'Remaining attempts: '+data.remaining+'.':''):
    (data.error||'Settings are locked. Ask a server administrator to set up the security PIN.');
   lockedUntil=data.lockedFor?Date.now()+1000*data.lockedFor:0;
   update();if(configured&&!lockedUntil)digits[0].focus();
  }catch{if(opening===attempt&&dialog.open)message.textContent='Unable to check Settings protection.';}
 });
 async function verify(){
  if(unlock.disabled||!dialog.open)return;
  const opening=attempt;
  busy=true;update();
  try{
   const {status,data}=await request('verify',{pin:pin()});
   if(opening!==attempt||!dialog.open){if(data.token)revoke(data.token);return;}
   clear();
   if(status===200&&data.token){
    token=data.token;expires=data.expires;lastActivity=Date.now();
    dialog.close();
    document.dispatchEvent(new Event('genstat-settings-unlocked'));
   }else{
    showFailure(data,message);
    if(status===401){
     group.classList.remove('settings-pin-shake');void group.offsetWidth;
     group.classList.add('settings-pin-shake');
    }
   }
  }catch{if(opening===attempt&&dialog.open){clear();message.textContent='Unable to verify the security PIN.';}}
  finally{busy=false;update();if(dialog.open&&configured&&!lockedUntil&&!pin())digits[0].focus();}
 }
 form.addEventListener('submit',event=>{event.preventDefault();void verify();});
 for(const [index,input] of digits.entries()){
  input.addEventListener('input',()=>{
   input.value=input.value.replace(/[^0-9]/g,'').slice(0,1);
   if(input.value&&index<digits.length-1)digits[index+1].focus();
   update();if(!unlock.disabled)void verify();
  });
  input.addEventListener('paste',event=>{
   event.preventDefault();
   const pasted=event.clipboardData.getData('text').trim();
   if(!/^[0-9]{6}$/.test(pasted))return;
   digits.forEach((box,i)=>{box.value=pasted[i];});
   update();void verify();
  });
  input.addEventListener('keydown',event=>{
   const previous=event.key==='ArrowLeft'||(event.key==='Backspace'&&!input.value);
   const next=event.key==='ArrowRight';
   if(previous||next){
    event.preventDefault();
    const target=digits[index+(previous?-1:1)];
    if(target){target.focus();target.select();}
   }
  });
  input.addEventListener('focus',()=>input.select());
 }
 for(const button of dialog.querySelectorAll('[data-pin-close]'))button.addEventListener('click',()=>dialog.close());
 dialog.addEventListener('close',()=>{++attempt;clear();if(!token)gear.focus();});
 settings.addEventListener('close',()=>{if(change.open)change.close();revoke();});
 window.addEventListener('pagehide',()=>revoke());
 window.addEventListener('pageshow',event=>{if(event.persisted&&settings.open)lock();});

 async function check(action){
  const active=token;
  if(!active)return;
  if(expires*1000<=Date.now()){lock();return;}
  try{
   const {status,data}=await request(action);
   if(token!==active)return;
   if(status===403)lock();
   else if(status===200)expires=data.expires;
  }catch{}
 }
 function activity(){
  if(!token||Date.now()-lastActivity<1000)return;
  lastActivity=Date.now();void check('activity');
 }
 for(const element of [settings,change])
  for(const event of ['pointerdown','keydown','input','wheel'])element.addEventListener(event,activity,{passive:true});
 setInterval(update,1000);
 setInterval(()=>void check('status'),10000);

 byId('settings-pin-change-open').addEventListener('click',()=>{
  changeForm.reset();changeMessage.textContent='';change.showModal();update();
 });
 byId('settings-pin-change-cancel').addEventListener('click',()=>change.close());
 change.addEventListener('close',()=>changeForm.reset());
 changeForm.addEventListener('input',event=>{
  if(event.target instanceof HTMLInputElement)event.target.value=event.target.value.replace(/[^0-9]/g,'');
 });
 changeForm.addEventListener('submit',async event=>{
  event.preventDefault();if(changeBusy||!token||lockedUntil>Date.now())return;
  const values=Object.fromEntries(new FormData(changeForm));
  if(values.newPin!==values.confirmation){changeMessage.textContent='The new PIN and confirmation must match.';return;}
  changeBusy=true;update();
  try{
   if(!await report.confirmAction('Save the new security PIN and lock Settings?')||!token)return;
   const {status,data}=await request('change',{...values,confirmed:true});
   if(status===403){lock();return;}
   if(status===200){
    revoke();change.close();settings.close();report.notifySettings(data.message);gear.focus();
   }else{changeForm.reset();showFailure(data,changeMessage);}
  }catch{changeMessage.textContent='Unable to change the security PIN.';}
  finally{changeBusy=false;update();}
 });
})();
