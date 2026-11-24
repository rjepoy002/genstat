function registeredAccounts({byId,request,report,tableHead}){
 const area=byId('coop-add-area'),code=byId('coop-add-code'),add=byId('coop-add-account');
 const search=byId('coop-account-search'),filter=byId('coop-account-filter');
 const filterDialog=byId('coop-filter-dialog'),filterOpen=byId('coop-filter-open'),filterCount=byId('coop-filter-count');
 const previous=byId('coop-account-prev'),next=byId('coop-account-next'),info=byId('coop-account-page');
 const list=byId('coop-account-list'),status=byId('coop-account-status');
 let sequence=0,page=1,areas=[],mutating=false,opened=false;
 let activeSearch='',activeArea='';
 function note(message,error=false){
  status.textContent=message;
  status.classList.toggle('text-red-700',error);
  status.classList.toggle('dark:text-red-400',error);
 }
 function options(select,placeholder,selected){
  select.replaceChildren(new Option(placeholder,''));
  for(const group of areas)select.add(new Option(group.name,group.id));
  select.value=selected;
  if(select.selectedIndex<0)select.value='';
 }
 function controls(){
  add.disabled=mutating||!area.value||!code.value.trim();
  for(const control of [area,code])control.disabled=mutating;
  filterOpen.disabled=mutating||!areas.length;
 }
 function applyFilters(term,selectedArea){
  activeSearch=term.trim();activeArea=selectedArea;
  const count=Number(Boolean(activeSearch))+Number(Boolean(activeArea));
  filterCount.textContent=count;filterCount.hidden=count===0;
  filterDialog.close();
  void load(1);
 }
 function row(table,values){
  const line=table.insertRow();
  for(const value of values){
   const cell=line.insertCell();
   if(value instanceof Node)cell.append(value);
   else cell.textContent=value;
  }
  return line;
 }
 function icon(label,path){
  const button=document.createElement('button');
  button.type='button';button.title=label;button.setAttribute('aria-label',label);
  const ns='http://www.w3.org/2000/svg',svg=document.createElementNS(ns,'svg'),shape=document.createElementNS(ns,'path');
  svg.setAttribute('viewBox','0 0 24 24');svg.setAttribute('width','16');svg.setAttribute('height','16');
  svg.setAttribute('aria-hidden','true');shape.setAttribute('d',path);shape.setAttribute('fill','none');
  shape.setAttribute('stroke','currentColor');shape.setAttribute('stroke-width','2');
  svg.append(shape);button.append(svg);
  return button;
 }
 function render(data){
  areas=data.areas;page=data.page;
  options(area,'Select area',area.value);
  info.textContent=data.registered+' registered / '+data.matching+' matching / Page '+page+' of '+data.pages;
  previous.disabled=page<=1;next.disabled=page>=data.pages;
  if(!data.rows.length){list.textContent='No registered accounts match.';return;}
  const table=document.createElement('table'),body=tableHead(table,['Account code','Account number','Account name','GENSTAT area','Current month KWH']);
  for(const account of data.rows){
   const number=document.createElement('span');number.className='inline-flex items-center gap-1 whitespace-nowrap';
   const actions=document.createElement('div');actions.className='flex flex-wrap items-center gap-2';
   const edit=icon('Edit Area for '+account.AcctCode,'M4 20h4L20 8l-4-4L4 16zM13 7l4 4');
   const remove=icon('Delete account','M3 6h18 M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6 M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2 M10 11v6 M14 11v6');
   const identity=account.AcctNo??account.AcctCode;
   remove.setAttribute('aria-label','Delete account '+identity);
   remove.className='inline-flex size-6 shrink-0 items-center justify-center rounded-sm border-0! bg-transparent! p-0! text-red-600! hover:bg-red-50! focus-visible:ring-2 focus-visible:ring-red-500 dark:text-red-400! dark:hover:bg-red-950!';
   const trash=remove.querySelector('svg');
   trash.setAttribute('width','14');trash.setAttribute('height','14');
   trash.setAttribute('stroke-linecap','round');trash.setAttribute('stroke-linejoin','round');
   const params={id:account.ID,expectedArea:account.GenstatAreaID,confirmed:true};
   const current=areas.find(group=>group.id===account.GenstatAreaID)?.name;
   edit.addEventListener('click',()=>{
    if(actions.querySelector('select'))return;
    const select=document.createElement('select'),apply=document.createElement('button'),cancel=document.createElement('button');
    select.className='settings-input';select.setAttribute('aria-label','New GENSTAT area for '+account.AcctCode);
    options(select,'Select area',String(account.GenstatAreaID));
    apply.type=cancel.type='button';apply.textContent='Transfer';cancel.textContent='Cancel';
    apply.disabled=true;
    select.addEventListener('change',()=>{apply.disabled=!select.value||Number(select.value)===account.GenstatAreaID;});
    cancel.addEventListener('click',()=>{select.remove();apply.remove();cancel.remove();edit.focus();});
    apply.addEventListener('click',async()=>{
     const target=areas.find(group=>group.id===Number(select.value));
     if(target&&await report.confirmAction('Transfer '+account.AcctCode+' to '+target.name+'?'))
      void mutate('accounts-transfer',{...params,area:target.id});
    });
    actions.append(select,apply,cancel);select.focus();
   });
   remove.addEventListener('click',async()=>{
    if(await report.confirmAction('Delete account '+identity+' from registered COOP consumption in '+current+'?'))
     void mutate('accounts-remove',params);
   });
   number.append(account.AcctNo??'—',remove);
   actions.append(current,edit);
   const line=row(body,[account.AcctCode,number,account.AccountName??'—',actions,account.kwh??account.status]);
   if(account.kwh===null)line.lastElementChild.classList.add('text-amber-900','dark:text-amber-100');
  }
  list.replaceChildren(table);
 }
 async function load(target=1,message=''){
  if(!opened||mutating)return;
  const token=++sequence;
  const currentMonth=report.snapshot().month;
  note('Loading registered accounts…');
  list.textContent='Loading accounts…';previous.disabled=next.disabled=true;
  try{
   const data=await request('accounts-list',{page:target,area:activeArea?Number(activeArea):null,search:activeSearch});
   if(token!==sequence||!opened||currentMonth!==data.month||report.snapshot().month!==data.month)return;
   render(data);note([message,data.billingError].filter(Boolean).join(' '),!!data.billingError);
   if(data.resolved)void report.refreshCoop();
  }catch(error){
   if(token!==sequence||!opened)return;
   list.textContent='Accounts could not be loaded.';info.textContent='';note(error.message,true);
  }finally{if(token===sequence)controls();}
 }
 async function mutate(action,payload){
  if(mutating||!opened)return;
  const token=++sequence;
  mutating=true;controls();note('Saving account changes…');
  let message='';
  try{
   const result=await request(action,payload);
   void report.refreshCoop();
   if(token!==sequence)return;
   if(action==='accounts-add')code.value='';
   message=result.message;
  }catch(error){if(token===sequence)note(error.message,true);}
  finally{if(token===sequence){mutating=false;controls();}}
  if(message&&token===sequence)await load(action==='accounts-add'?1:page,message);
 }
 add.addEventListener('click',()=>void mutate('accounts-add',{area:Number(area.value),code:code.value}));
 code.addEventListener('input',controls);area.addEventListener('change',controls);
 code.addEventListener('keydown',event=>{
  if(event.key==='Enter'){event.preventDefault();if(!add.disabled)add.click();}
 });
 filterOpen.addEventListener('click',()=>{
  search.value=activeSearch;options(filter,'All Areas',activeArea);
  filterDialog.showModal();
 });
 byId('coop-filter-form').addEventListener('submit',event=>{
  event.preventDefault();applyFilters(search.value,filter.value);
 });
 byId('coop-filter-clear').addEventListener('click',()=>applyFilters('',''));
 byId('coop-filter-close').addEventListener('click',()=>filterDialog.close());
 previous.addEventListener('click',()=>void load(page-1));next.addEventListener('click',()=>void load(page+1));
 return {
  open(){opened=true;controls();void load();},
  refresh(){void load(page);},
  close(){
   opened=false;sequence++;mutating=false;
   if(filterDialog.open)filterDialog.close();
   code.value='';list.replaceChildren();info.textContent='';
   areas=[];activeSearch=activeArea='';filterCount.hidden=true;
   options(area,'Select area','');note('');controls();
  }
 };
}
