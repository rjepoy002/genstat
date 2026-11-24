'use strict';
(()=>{
 const key='genstat:appearance:v1';
 const defaults={showHeadings:'on',theme:'system',rowHeight:36,fontSize:12,columnSpacing:'comfortable',selectionBorderWidth:2,selectionBorderColor:'#2563EB'};
 const options={showHeadings:['on','off'],theme:['system','light','dark'],rowHeight:[28,36,44],fontSize:[11,12,13,14],columnSpacing:['compact','comfortable'],selectionBorderWidth:[2,3]};
 const system=matchMedia('(prefers-color-scheme: dark)');
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
  const dark=settings.theme==='dark'||(settings.theme==='system'&&system.matches);
  target.classList.toggle('dark',dark);
  target.dataset.theme=dark?'dark':'light';
  target.style.colorScheme=target.dataset.theme;
  target.dataset.excelHeadings=settings.showHeadings;
  for(const [name,value] of Object.entries({
   '--report-row-height':settings.rowHeight+'px','--report-font-size':settings.fontSize+'px',
   '--report-cell-padding':settings.columnSpacing==='compact'?'4px':'7px',
   '--report-edge-width':settings.selectionBorderWidth+'px','--report-edge-color':settings.selectionBorderColor
  }))target.style.setProperty(name,value);
 }
 style(root,applied);
 document.addEventListener('DOMContentLoaded',()=>{
  const byId=id=>document.getElementById(id);
  const dialog=byId('sql-settings-dialog');
  if(!dialog){system.addEventListener('change',()=>style(root,applied));return;}
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
   for(const section of ['reporting','coop','appearance']){
    byId('settings-'+section).hidden=section!==name;
    byId('settings-'+section+'-tab').setAttribute('aria-pressed',String(section===name));
   }
   byId('sql-context').hidden=name!=='reporting';
   dialog.scrollTop=0;
  }
  const showAppearance=show=>showSection(show?'appearance':'reporting');
  reportingTab.addEventListener('click',()=>showSection('reporting'));
  appearanceTab.addEventListener('click',()=>showSection('appearance'));
  byId('settings-coop-tab').addEventListener('click',()=>showSection('coop'));
  byId('sql-settings-open').addEventListener('click',()=>{fill(applied);showAppearance(false);});
  dialog.addEventListener('close',()=>fill(applied));
  form.addEventListener('input',()=>{style(preview,draft());status.textContent='';});
  byId('appearance-reset').addEventListener('click',()=>fill(defaults));
  byId('appearance-cancel').addEventListener('click',()=>{fill(applied);showAppearance(false);reportingTab.focus();});
  form.addEventListener('submit',event=>{
   event.preventDefault();
   applied=draft();style(root,applied);
   try{localStorage.setItem(key,JSON.stringify(applied));status.textContent='Appearance saved.';}
   catch{status.textContent='Appearance applied for this page. Browser storage is unavailable.';}
   dispatchEvent(new Event('genstat-appearance'));
  });
  system.addEventListener('change',()=>{style(root,applied);style(preview,draft());});
  fill(applied);
 });
})();
