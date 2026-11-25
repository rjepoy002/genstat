'use strict';
(()=>{
 const key='genstat:appearance:v1';
 const defaults={showHeadings:'on',showEmptyRows:'off',theme:'light',rowHeight:36,fontSize:12,columnSpacing:'comfortable',selectionBorderWidth:2,selectionBorderColor:'#2563EB'};
 const options={showHeadings:['on','off'],showEmptyRows:['off','on'],theme:['light','dark'],rowHeight:[28,36,44],fontSize:[11,12,13,14],columnSpacing:['compact','comfortable'],selectionBorderWidth:[2,3]};
 const root=document.documentElement;
 function validated(input){
  const result={...defaults};
  for(const [name,choices] of Object.entries(options))if(choices.includes(input?.[name]))result[name]=input[name];
  if(typeof input?.selectionBorderColor==='string'&&/^#[0-9a-f]{6}$/i.test(input.selectionBorderColor))result.selectionBorderColor=input.selectionBorderColor;
  return result;
 }
 let applied=defaults;
 try{applied=validated(JSON.parse(localStorage.getItem(key)));}catch{}
 function style(target,settings){
  const dark=settings.theme==='dark';
  target.classList.toggle('dark',dark);
  target.dataset.theme=dark?'dark':'light';
  target.style.colorScheme=target.dataset.theme;
  target.dataset.excelHeadings=settings.showHeadings;
  target.dataset.showEmptyRows=settings.showEmptyRows;
  for(const [name,value] of Object.entries({
   '--report-row-height':settings.rowHeight+'px','--report-font-size':settings.fontSize+'px',
   '--report-cell-padding':settings.columnSpacing==='compact'?'4px':'7px',
   '--report-edge-width':settings.selectionBorderWidth+'px','--report-edge-color':settings.selectionBorderColor
  }))target.style.setProperty(name,value);
 }
 style(root,applied);
 document.addEventListener('DOMContentLoaded',()=>{
  const byId=id=>document.getElementById(id);
  const toggle=byId('report-theme-toggle');
  function updateToggle(){
   if(!toggle)return;
   const label=applied.theme==='dark'?'Switch to Light Mode':'Switch to Dark Mode';
   toggle.setAttribute('aria-label',label);
   toggle.dataset.tooltip=label;
  }
  function apply(settings){
   applied=settings;
   style(root,applied);
   updateToggle();
   dispatchEvent(new Event('genstat-appearance'));
  }
  function save(settings){
   apply(settings);
   try{localStorage.setItem(key,JSON.stringify(applied));return true;}catch{return false;}
  }
  updateToggle();
  toggle?.addEventListener('click',()=>save({...applied,theme:applied.theme==='dark'?'light':'dark'}));
  addEventListener('storage',event=>{
   if(event.storageArea!==localStorage||(event.key!==key&&event.key!==null))return;
   try{apply(validated(JSON.parse(event.newValue)));}catch{}
  });
  const dialog=byId('sql-settings-dialog');
  if(!dialog)return;
  const form=byId('report-appearance-form'),preview=byId('appearance-preview');
  const reportingTab=byId('settings-reporting-tab'),appearanceTab=byId('settings-appearance-tab');
  const status=byId('appearance-status');
  function draft(){
   const values=Object.fromEntries(new FormData(form));
   for(const name of ['rowHeight','fontSize','selectionBorderWidth'])values[name]=Number(values[name]);
   return validated(values);
  }
  function fill(settings){
   for(const [name,value] of Object.entries(settings))form.elements.namedItem(name).value=value;
   style(preview,settings);status.textContent='';
  }
  function showSection(name){
   for(const section of ['reporting','coop','appearance','security']){
    byId('settings-'+section).hidden=section!==name;
    byId('settings-'+section+'-tab').setAttribute('aria-pressed',String(section===name));
   }
   byId('sql-context').hidden=name!=='reporting';
   dialog.scrollTop=0;
  }
  const showAppearance=show=>showSection(show?'appearance':'reporting');
  function previewEmptyRows(value){
   root.dataset.showEmptyRows=value;
   dispatchEvent(new Event('genstat-appearance'));
  }
  function restoreEmptyRows(){previewEmptyRows(applied.showEmptyRows);}
  reportingTab.addEventListener('click',()=>showSection('reporting'));
  appearanceTab.addEventListener('click',()=>showSection('appearance'));
  byId('settings-security-tab').addEventListener('click',()=>showSection('security'));
  byId('settings-coop-tab').addEventListener('click',()=>showSection('coop'));
  document.addEventListener('genstat-settings-unlocked',()=>{fill(applied);showAppearance(false);});
  dialog.addEventListener('close',()=>{fill(applied);restoreEmptyRows();});
  form.addEventListener('input',event=>{
   const next=draft();style(preview,next);status.textContent='';
   if(event.target.name==='showEmptyRows')previewEmptyRows(next.showEmptyRows);
  });
  byId('appearance-reset').addEventListener('click',()=>{fill(defaults);previewEmptyRows(defaults.showEmptyRows);});
  byId('appearance-cancel').addEventListener('click',()=>{fill(applied);restoreEmptyRows();showAppearance(false);reportingTab.focus();});
  form.addEventListener('submit',async event=>{
   event.preventDefault();
   const button=form.querySelector('[type=submit]');
   if(button.disabled)return;
   const next=draft();
   button.disabled=true;status.textContent='Saving appearance…';
   try{await window.genstatSettings.saveAppearance();}
   catch(error){status.textContent=error.message||'Unable to authorize appearance changes.';return;}
   finally{button.disabled=false;}
   if(!dialog.open)return;
   status.textContent=save(next)?'Appearance saved.':'Appearance applied for this page. Browser storage is unavailable.';
  });
  fill(applied);
 });
})();
