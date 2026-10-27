'use strict';
(() => {
 const inputs=[...document.querySelectorAll('.editable input')];
 const sheetName=document.body.dataset.sheet||'Jun26';
 const renderedSourceFormulas=JSON.parse(document.getElementById('worksheet-source-formulas').textContent);
 const referenceHighlights=window.createGenStatReferenceHighlighter(document.getElementById('sheet'),sheetName,document.getElementById('formula-reference-note'));
 const cellValues=new WeakMap(); // raw storage, editable text and preview are separate.
 const status=document.querySelector('#status'), period=document.querySelector('#period');
 const buttons=['open','save','export'].map(id=>document.getElementById(id));
 let revision=0, dirty=false, ready=false, busy=false, loadedValues={};
 let formulaOverrides={},sourceFormulas={...renderedSourceFormulas},authorization={unlocked:false},currentCell=null;
 let calculationErrors=0,unlockedCell=null,unlockTarget=null,autosaveTimer=null;
 let savedFormulaOverrides={},formulaHistory=[];
 const protectedFormula=ref=>sourceFormulas[ref]??(typeof savedFormulaOverrides[ref]==='string'&&savedFormulaOverrides[ref].startsWith('=')?savedFormulaOverrides[ref]:null);
 const hasOverride=ref=>Object.prototype.hasOwnProperty.call(formulaOverrides,ref);
 const csrf=document.querySelector('meta[name="csrf-token"]').content;
 const templateHash=document.querySelector('meta[name="template-hash"]').content;
 const versionHeaders={'X-Template-Hash':templateHash,'X-Layout-Hash':document.querySelector('meta[name="layout-hash"]').content};
 const setStatus=(message,error=false)=>{if(error&&/^(Conflict:|Error:)/.test(message)){status.hidden=true;status.textContent='';return;}status.hidden=false;status.classList.toggle('passive',!error&&calculationErrors===0&&/^(Source workbook loaded|Source worksheet loaded|Draft reopened|Draft saved)/.test(message));const critical=error||calculationErrors>0;status.textContent=message+(!error&&calculationErrors?' · '+calculationErrors+' calculation error(s); review Workbook notes.':'');status.classList.toggle('error',critical);status.setAttribute('role',critical?'alert':'status');status.closest('.notification-strip').dataset.state=critical?'error':'info';};
 function temporal(serial,code){
  if(serial<0||serial>2958465)return '########';
  let days=Math.floor(serial),seconds=Math.round((serial-days)*86400);days+=Math.floor(seconds/86400);seconds%=86400;
  const date=new Date(Date.UTC(1899,11,31)+(days>=60?days-1:days)*86400000),year=date.getUTCFullYear(),month=date.getUTCMonth()+1,day=days===60?29:date.getUTCDate(),hour=Math.floor(seconds/3600),minute=Math.floor(seconds%3600/60),second=seconds%60,am=code.toUpperCase().includes('AM/PM'),pad=n=>String(n).padStart(2,'0');
  return code.replace(/"[^"]*"|\\.|AM\/PM|\[h\]|yyyy|mmmm|dddd|mmm|ddd|yy|mm|dd|hh|ss|[ymdhs]/gi,(token,offset)=>{
   if(token[0]==='"')return token.slice(1,-1);if(token[0]==='\\')return token.slice(1);
   const minutes=/h|:/i.test(code.slice(0,offset))||/^m{1,2}:s/i.test(code.slice(offset));
   return {yyyy:String(year),yy:pad(year%100),mmmm:date.toLocaleString('en-US',{month:'long',timeZone:'UTC'}),mmm:date.toLocaleString('en-US',{month:'short',timeZone:'UTC'}),dddd:date.toLocaleString('en-US',{weekday:'long',timeZone:'UTC'}),ddd:date.toLocaleString('en-US',{weekday:'short',timeZone:'UTC'}),m:String(minutes?minute:month),mm:pad(minutes?minute:month),d:String(day),dd:pad(day),h:String(am?(hour%12||12):hour),hh:pad(am?(hour%12||12):hour),s:String(second),ss:pad(second),'am/pm':hour<12?'AM':'PM','[h]':String(Math.floor(serial*24))}[token.toLowerCase()]||token;
  });
 }
 function formatted(raw,spec){
  if(raw===''||!validNumber(raw))return raw;
  const n=Number(raw);
  if(spec.temporal)return temporal(n,spec.temporal);
  if(spec.general){
   const rounded=Number(n.toPrecision(15)),magnitude=Math.abs(rounded);
   if(magnitude!==0&&(magnitude>=1e11||magnitude<1e-9)){
    const [mantissa,exponent]=rounded.toExponential(14).split('e');
    return mantissa.replace(/\.?0+$/,'')+'E'+(Number(exponent)<0?'-':'+')+String(Math.abs(Number(exponent))).padStart(2,'0');
   }else return rounded.toLocaleString('en-US',{useGrouping:false,maximumSignificantDigits:15});
  }
  const index=n<0&&spec.sections.length>1?1:n===0&&spec.sections.length>2?2:0,section=spec.sections[index];
  const text=section.literal?'':(Math.abs(n)*100**section.percent).toLocaleString('en-US',{useGrouping:section.grouping,minimumFractionDigits:section.minDecimals,maximumFractionDigits:section.maxDecimals});
  return (n<0&&index===0?'-':'')+section.prefix+text+section.suffix;
 }
 function cleanSourceText(raw,spec){
  if(raw===''||!validNumber(raw))return raw;
  if(spec.general||spec.temporal||spec.sections[0].literal||spec.sections[0].percent>0)return formatted(raw,{general:true});
  return formatted(raw,{general:false,sections:[{...spec.sections[0],prefix:'',suffix:'',grouping:false}]});
 }
 function loadInput(input,raw,userEntered){
  const spec=JSON.parse(input.dataset.format);
  const editText=userEntered&&raw!==input.dataset.original?raw:cleanSourceText(raw,spec);
  cellValues.set(input,{raw,editText,spec});input.value=editText;
  input.removeAttribute('aria-invalid');preview(input);
 }
 function preview(input){
  const state=cellValues.get(input);
  if(hasOverride(input.dataset.ref)){input.parentElement.querySelector('.input-preview').textContent=input.parentElement.dataset.resultDisplay??'Pending calculation';return;}
  input.parentElement.querySelector('.input-preview').textContent=validNumber(state.editText.trim())?formatted(state.raw,state.spec):state.editText;
 }
 const validNumber=v=>v===''||(/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)(?:[eE][+-]?\d+)?$/.test(v)&&Number.isFinite(Number(v)));
 function values(){const out={...loadedValues};for(const input of inputs){const state=cellValues.get(input);if(hasOverride(input.dataset.ref)){delete out[input.dataset.ref];continue;}if(state.raw.length>32767){throw new Error('Cell text is too long.');}input.removeAttribute('aria-invalid');if(state.raw!==input.dataset.original||Object.prototype.hasOwnProperty.call(loadedValues,input.dataset.ref))out[input.dataset.ref]=state.raw;else delete out[input.dataset.ref];}return out;}
 function lock(on){busy=on;buttons.forEach(b=>b.disabled=on);inputs.forEach(i=>i.disabled=on||!ready);document.getElementById('formula-text').disabled=on;document.getElementById('formula-unlock').disabled=on;}
 async function request(action,body){const response=await fetch(`api.php?action=${action}&period=${encodeURIComponent(period.value)}&sheet=${encodeURIComponent(sheetName)}`,body?{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':csrf,...versionHeaders},body:JSON.stringify(body)}:{cache:'no-store',headers:versionHeaders});const data=await response.json();if(!response.ok)throw new Error(data.error||'Request failed.');return data;}
 async function open(){if(busy)return;if(dirty&&!confirm('Discard unsaved changes and reopen the saved draft?'))return;lock(true);try{const d=await request('open');loadedValues={...d.values};inputs.forEach(i=>{const entered=Object.prototype.hasOwnProperty.call(d.values,i.dataset.ref);loadInput(i,entered?d.values[i.dataset.ref]:i.dataset.original,entered);if(entered)i.parentElement.classList.add('manual-input');});adoptFormulaPayload(d);revision=d.revision;dirty=false;ready=true;setStatus(d.savedAt?`Draft reopened \u00b7 saved ${new Date(d.savedAt).toLocaleString()}`:'Source workbook loaded \u00b7 no saved draft.');}catch(e){setStatus(e.message,true);}finally{lock(false);}}
 async function save(){if(!ready)return false;const v=values();const d=await request('save',{values:v,revision,formulas:formulaOverrides});loadedValues={...d.values};adoptFormulaPayload(d);revision=d.revision;dirty=false;unlockedCell=null;savedFormulaOverrides={...formulaOverrides};formulaHistory=[];updatePending();setStatus(`Draft saved \u00b7 ${new Date(d.savedAt).toLocaleString()}`);return true;}
 document.getElementById('open').addEventListener('click',open);
 document.getElementById('save').addEventListener('click',async()=>{if(busy)return;lock(true);try{await save();}catch(e){setStatus(e.message,true);}finally{lock(false);}});
 document.getElementById('export').addEventListener('click',async()=>{if(busy||!ready)return;lock(true);try{if(dirty)await save();const response=await fetch(`api.php?action=export&period=${encodeURIComponent(period.value)}&sheet=${encodeURIComponent(sheetName)}`,{headers:versionHeaders});if(!response.ok){const d=await response.json();throw new Error(d.error||'Export failed.');}const url=URL.createObjectURL(await response.blob());const a=document.createElement('a');a.href=url;a.download=`PALECO-GenStat-${period.value}.xlsx`;document.body.append(a);a.click();a.remove();setTimeout(()=>URL.revokeObjectURL(url),10000);setStatus('Excel exported. Linked files may be needed for full recalculation.');}catch(e){setStatus(e.message,true);}finally{lock(false);}});
 for(const input of inputs){
  loadInput(input,input.dataset.original,false);
  input.addEventListener('dblclick',()=>{if(protectedFormula(input.dataset.ref))beginUnlock(input.parentElement);});
  input.addEventListener('focus',()=>{input.value=formulaOverrides[input.dataset.ref]||cellValues.get(input).editText;});
  input.addEventListener('blur',()=>preview(input));
  input.addEventListener('input',()=>{const state=cellValues.get(input);state.editText=input.value;const text=input.value,ref=input.dataset.ref;if(text.startsWith('=')){formulaOverrides[ref]=text;input.removeAttribute('aria-invalid');}else{delete formulaOverrides[ref];state.raw=text;input.removeAttribute('aria-invalid');}dirty=true;preview(input);setStatus('Unsaved changes \u00b7 Save Draft to recalculate.');input.parentElement.classList.add('manual-input');if(currentCell?.dataset.ref===ref)refreshFormulaBar();scheduleAutosave();});
  input.addEventListener('keydown',e=>{if(protectedFormula(input.dataset.ref)&&!canEditFormula(input.parentElement)&&(['F2','Enter','Backspace','Delete'].includes(e.key)||(e.key.length===1&&!e.ctrlKey&&!e.metaKey))){e.preventDefault();beginUnlock(input.parentElement);return;}if(e.key==='Enter'){e.preventDefault();const next=inputs[inputs.indexOf(input)+(e.shiftKey?-1:1)];if(next)next.focus();}});
 }
 let selectedCell=null;
 function cellInfo(cell){const info=document.getElementById('cell-info');info.textContent=cell.dataset.ref;info.title=sheetName+' \u00b7 '+cell.dataset.ref+(cell.classList.contains('formula')?(canEditFormula(cell)?' \u00b7 Authorized formula-bar editing':' \u00b7 \u{1F512} Read-only formula'):cell.classList.contains('editable')?' \u00b7 Numeric input':' \u00b7 Protected');}
 function updateFormulaIndicators(){for(const cell of document.querySelectorAll('.cell.formula')){cell.title=cell.dataset.baseTitle+' \u00b7 '+(canEditFormula(cell)?'Authorized formula-bar editing - ':'\u{1F512} Read-only ')+cell.dataset.formulaKind+' formula'+(cell.dataset.formulaText?' ='+cell.dataset.formulaText:'');}if(selectedCell)cellInfo(selectedCell);}
 document.querySelectorAll('.cell').forEach(cell=>{
  const select=()=>{if(selectedCell&&selectedCell!==cell){if(unlockedCell)finishFormulaEdit();selectedCell.classList.remove('formula-selected');unlockedCell=null;}selectedCell=cell;if(cell.classList.contains('formula'))cell.classList.add('formula-selected');cellInfo(cell);currentCell=cell;refreshFormulaBar();};
  cell.addEventListener('pointerdown',select);cell.addEventListener('focusin',select);
 });
 for(const cell of document.querySelectorAll('.cell.formula')){
  for(const event of ['beforeinput','paste','drop'])cell.addEventListener(event,e=>{e.preventDefault();beginUnlock(cell);});
  cell.addEventListener('dblclick',()=>beginUnlock(cell));
  cell.addEventListener('keydown',e=>{if(['Backspace','Delete','F2','Enter'].includes(e.key)||(e.key.length===1&&!e.ctrlKey&&!e.metaKey)||((e.ctrlKey||e.metaKey)&&['v','x'].includes(e.key.toLowerCase()))){e.preventDefault();beginUnlock(cell);}});
 }
 const formulaText=document.getElementById('formula-text'),unlockButton=document.getElementById('formula-unlock'),mode=document.getElementById('formula-mode');
 function canEditFormula(cell){return Boolean(unlockedCell===cell.dataset.ref&&protectedFormula(cell.dataset.ref)&&['ordinary','shared'].includes(cell.dataset.formulaKind));}
 function refreshFormulaBar(){
  if(!currentCell)return;const ref=currentCell.dataset.ref,input=currentCell.querySelector('input');
  formulaText.value=hasOverride(ref)?formulaOverrides[ref]:(sourceFormulas[ref]??(input?cellValues.get(input).raw:currentCell.dataset.rawValue||''));
  formulaText.readOnly=protectedFormula(ref)?!canEditFormula(currentCell):!input;
  referenceHighlights.show((hasOverride(ref)?formulaOverrides[ref]:sourceFormulas[ref])||'');
  const protection=document.getElementById('cell-protection');
  protection.dataset.state=protectedFormula(ref)?(canEditFormula(currentCell)?'unlocked':'locked'):(input?'editable':'locked');
  protection.textContent=protectedFormula(ref)?(canEditFormula(currentCell)?'Formula unlocked':'Protected formula'):(input?'Manual input':'Protected cell');
 }
 function setAuthorization(a){authorization=a;refreshFormulaBar();updateFormulaIndicators();}
 function updatePending(){
  for(const cell of document.querySelectorAll('.cell.formula')){const ref=cell.dataset.ref;cell.classList.toggle('pending-change',(formulaOverrides[ref]??null)!==(savedFormulaOverrides[ref]??null));}
  document.getElementById('formula-undo').disabled=!formulaHistory.length;
  mode.textContent=unlockedCell?'Editing '+unlockedCell:'Formula cells protected';
  document.body.classList.toggle('formula-editing-active',Boolean(unlockedCell));
 }
 function scheduleAutosave(){clearTimeout(autosaveTimer);autosaveTimer=setTimeout(async()=>{if(!ready||busy||!dirty||unlockedCell||formulaHistory.length||document.querySelector('dialog[open]'))return;lock(true);try{await save();}catch(e){setStatus(e.message,true);}finally{lock(false);}},1200);}
 function adoptFormulaPayload(d){
  const draftStamp=document.getElementById('draft-saved'),draftTime=document.getElementById('draft-saved-at');draftStamp.hidden=!d.savedAt;if(d.savedAt){draftTime.dateTime=d.savedAt;draftTime.textContent=new Date(d.savedAt).toLocaleString();}
  formulaOverrides={...d.formulas};sourceFormulas={...d.sourceFormulas};savedFormulaOverrides={...formulaOverrides};formulaHistory=[];setAuthorization(d.authorization);
  for(const [ref,result] of Object.entries(d.calculation?.cells||{})){
   const cell=document.querySelector('.cell[data-ref="'+ref+'"]');if(!cell)continue;cell.dataset.resultDisplay=result.display;cell.dataset.rawValue=result.raw;cell.dataset.formulaText=(sourceFormulas[ref]||'').replace(/^=/,'');
   const content=cell.querySelector('.cell-content');if(content)content.textContent=result.display;
   cell.classList.toggle('calculation-error',Boolean(result.error));cell.classList.toggle('cached-result',result.cached);cell.dataset.calculationNote=result.cached?'External/unsupported source cache':result.error||'Calculated';
  }
  for(const input of inputs){const ref=input.dataset.ref;input.readOnly=Boolean(protectedFormula(ref));input.parentElement.classList.toggle('formula',Boolean(protectedFormula(ref)));input.parentElement.dataset.formulaText=(protectedFormula(ref)||'').replace(/^=/,'');if(hasOverride(ref)){cellValues.get(input).editText=formulaOverrides[ref];input.value=formulaOverrides[ref];}preview(input);}
  updateFormulaIndicators();refreshFormulaBar();updatePending();
  const warnings=Object.entries(d.calculation?.warnings||{}),errors=Object.entries(d.calculation?.cells||{}).filter(([,r])=>r.error);calculationErrors=errors.length;
  const badge=document.getElementById('workbook-warning-count');badge.hidden=warnings.length===0&&errors.length===0;badge.textContent=[warnings.length?warnings.length+' warnings':'',errors.length?errors.length+' errors':''].filter(Boolean).join(' · ');badge.classList.toggle('has-errors',errors.length>0);
  document.getElementById('calculation-notice').textContent=errors.length?errors.length+' calculation error(s) need attention.':warnings.length?warnings.length+' formulas use external or unsupported source caches. Linked workbooks may be required.':'Supported formulas recalculated.';
  const list=document.getElementById('workbook-warning-list');list.replaceChildren();
  for(const [reference,message] of [...warnings,...errors.map(([ref,r])=>[sheetName+'!'+ref,r.error])]){const item=document.createElement('li'),title=document.createElement('strong');title.textContent=reference+': ';item.append(title,document.createTextNode(message));const ref=reference.slice(reference.lastIndexOf('!')+1);if(sourceFormulas[ref]){const code=document.createElement('code');code.textContent=sourceFormulas[ref];item.append(code);}list.append(item);}
 }
 function finishFormulaEdit(cancel=false){
  if(!currentCell)return;
  const ref=currentCell.dataset.ref,input=currentCell.querySelector('input');
  if(protectedFormula(ref)&&canEditFormula(currentCell)){
   if(!cancel){const text=formulaText.value,previous=hasOverride(ref)?formulaOverrides[ref]:sourceFormulas[ref];if(text!==previous){formulaHistory.push({ref,had:hasOverride(ref),value:formulaOverrides[ref]});if(text===sourceFormulas[ref])delete formulaOverrides[ref];else formulaOverrides[ref]=text;dirty=true;currentCell.classList.add('pending-change');setStatus('Pending change in '+ref+'. Save Draft to validate; Undo cancels it.');}}
   unlockedCell=null;
  }else if(input&&!cancel){input.value=formulaText.value;input.dispatchEvent(new Event('input',{bubbles:true}));}
  updatePending();refreshFormulaBar();updateFormulaIndicators();
 }
 formulaText.addEventListener('keydown',e=>{
  if(e.key==='Escape'){e.preventDefault();finishFormulaEdit(true);currentCell?.focus();return;}
  if(e.key==='Enter'&&!formulaText.readOnly){e.preventDefault();finishFormulaEdit();currentCell?.focus();}
 });
 formulaText.addEventListener('blur',()=>{if(unlockedCell)finishFormulaEdit();});
 formulaText.addEventListener('pointerdown',()=>{if(formulaText.readOnly&&currentCell&&protectedFormula(currentCell.dataset.ref))beginUnlock(currentCell);});
 const unlockDialog=document.getElementById('formula-unlock-dialog'),authError=document.getElementById('formula-auth-error');
 function beginUnlock(cell=currentCell){
  if(!cell||!protectedFormula(cell.dataset.ref)){setStatus('Select a formula cell to unlock.',true);return;}
  if(!['ordinary','shared'].includes(cell.dataset.formulaKind)){setStatus('Array/data-table formulas remain protected.',true);return;}
  if(!ready||busy)return;
  unlockTarget=cell;authError.hidden=true;unlockDialog.showModal();
 }
 unlockButton.addEventListener('click',()=>beginUnlock());
 document.getElementById('formula-unlock-cancel').addEventListener('click',()=>{unlockTarget=null;unlockDialog.close();});
 unlockDialog.addEventListener('cancel',()=>{unlockTarget=null;});
 document.getElementById('formula-unlock-form').addEventListener('submit',async e=>{
  e.preventDefault();if(!unlockTarget)return;const submit=e.currentTarget.querySelector('[type=submit]');submit.disabled=true;
  try{const target=unlockTarget;await request('unlock',{cell:target.dataset.ref});currentCell=target;unlockedCell=target.dataset.ref;unlockTarget=null;unlockDialog.close();updatePending();refreshFormulaBar();updateFormulaIndicators();formulaText.focus();formulaText.select();}
  catch(error){authError.textContent=error.message;authError.hidden=false;}finally{submit.disabled=false;}
 });
 function undoFormula(){const previous=formulaHistory.pop();if(!previous)return;if(previous.had)formulaOverrides[previous.ref]=previous.value;else delete formulaOverrides[previous.ref];unlockedCell=null;const same=(a,b)=>{const keys=new Set([...Object.keys(a),...Object.keys(b)]);return [...keys].every(k=>a[k]===b[k]);};dirty=!same(formulaOverrides,savedFormulaOverrides)||!same(values(),loadedValues);updatePending();refreshFormulaBar();setStatus('Pending formula change undone.');}
 document.getElementById('formula-undo').addEventListener('click',undoFormula);
 document.addEventListener('keydown',e=>{if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='z'&&e.target!==formulaText&&!e.target.matches('input')&&formulaHistory.length){e.preventDefault();undoFormula();}});
 updateFormulaIndicators();
 document.addEventListener('keydown',e=>{if(e.key==='Escape')document.getElementById('workbook-notes').open=false;});
 const zoomControl=document.getElementById('zoom'),zoomViewport=document.getElementById('viewport'),zoomSheet=document.getElementById('sheet'),zoomSize=document.getElementById('sheet-size');
 const zoomStorageKey='genstat.worksheetZoom';
 let worksheetZoom=Number(zoomControl.value),pendingZoom=null,zoomFrame=0;
 function syncZoomControl(percent){const value=String(percent/100);if(![...zoomControl.options].some(option=>option.value===value))zoomControl.add(new Option(percent+'%',value));zoomControl.value=value;document.querySelector('#zoom-menu summary').title='Zoom: '+percent+'%';document.querySelector('#zoom-menu summary').setAttribute('aria-label','Zoom: '+percent+'%');}
 function applyWorksheetZoom(percent,anchor=null){
  percent=Math.max(50,Math.min(200,Math.round(percent)));const z=percent/100;
  // Read scroll positions before changing geometry; write the transform once.
  const oldZoom=worksheetZoom,left=zoomViewport.scrollLeft,top=zoomViewport.scrollTop;
  zoomSheet.style.transform=`scale(${z})`;zoomSize.style.width=`${Number(zoomSheet.dataset.width)*z}px`;zoomSize.style.height=`${Number(zoomSheet.dataset.height)*z}px`;
  if(anchor){zoomViewport.scrollLeft=(left+anchor.x)/oldZoom*z-anchor.x;zoomViewport.scrollTop=(top+anchor.y)/oldZoom*z-anchor.y;}
  worksheetZoom=z;syncZoomControl(percent);try{sessionStorage.setItem(zoomStorageKey,String(percent));}catch(e){}
 }
 function flushWheelZoom(){zoomFrame=0;const next=pendingZoom;pendingZoom=null;if(next)applyWorksheetZoom(next.percent,next.anchor);}
 function onWorksheetWheel(event){
  if(!event.ctrlKey||event.deltaY===0||!event.target.closest?.('.cell')||!zoomSheet.contains(event.target)||document.querySelector('dialog[open], [aria-modal="true"]'))return;
  const rect=zoomViewport.getBoundingClientRect(),x=event.clientX-rect.left-zoomViewport.clientLeft,y=event.clientY-rect.top-zoomViewport.clientTop;
  if(x<0||y<0||x>=zoomViewport.clientWidth||y>=zoomViewport.clientHeight)return;
  event.preventDefault();
  const percent=Math.max(50,Math.min(200,(pendingZoom?.percent??Math.round(worksheetZoom*100))+(event.deltaY<0?10:-10)));
  pendingZoom={percent,anchor:{x,y}};if(!zoomFrame)zoomFrame=requestAnimationFrame(flushWheelZoom);
 }
 zoomControl.addEventListener('change',e=>{if(zoomFrame)cancelAnimationFrame(zoomFrame);zoomFrame=0;pendingZoom=null;applyWorksheetZoom(Number(e.target.value)*100);});
 function attachWorksheetWheel(){zoomViewport.addEventListener('wheel',onWorksheetWheel,{passive:false});}
 attachWorksheetWheel();
 window.addEventListener('pagehide',()=>{zoomViewport.removeEventListener('wheel',onWorksheetWheel);if(zoomFrame){cancelAnimationFrame(zoomFrame);flushWheelZoom();}});
 window.addEventListener('pageshow',e=>{if(e.persisted)attachWorksheetWheel();});
 try{const saved=Number(sessionStorage.getItem(zoomStorageKey));if(Number.isFinite(saved)&&saved>=50&&saved<=200)applyWorksheetZoom(saved);}catch(e){}
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
 const updateNotice=document.querySelector('.notification-strip'),updateText=document.getElementById('sync-status'),syncMeta=document.getElementById('sync-timestamp'),reload=document.getElementById('sync-reload'),syncNow=document.getElementById('sync-now');
 reload.addEventListener('click',()=>{if(dirty&&!confirm('Unsaved browser edits will be lost. Reload the updated Excel view?'))return;dirty=false;location.reload();});
 let checkingTemplate=false;
 async function checkTemplate(force=false){if(checkingTemplate||(!force&&document.hidden))return;checkingTemplate=true;syncNow.disabled=true;if(force||!updateText.textContent)updateText.textContent='Syncing: checking the Excel workbook...';try{const response=await fetch('api.php?action='+(force?'sync':'template-status'),{cache:'no-store'});if(!response.ok)throw new Error('Workbook synchronization is unavailable. Retry with Sync Now.');const data=await response.json();const blocked=data.status.state!=='ready',updated=data.hash!==templateHash;updateNotice.setAttribute('role',blocked?'alert':'status');updateNotice.dataset.state=blocked?data.status.state:updated?'update':'ready';updateText.textContent=blocked?data.status.message:updated?'Update available: newer Excel content is ready.'+(dirty?' Your unsaved browser edits have been kept.':''):'Synced: workbook and browser are up to date.';syncMeta.textContent=(data.status.workbook||'genstat.xlsx')+' · Last successful sync: '+(data.status.syncedAt?new Date(data.status.syncedAt).toLocaleString():'not yet');reload.hidden=blocked||!updated;if(force&&!blocked&&!updated&&!ready&&!dirty)await open();}catch(e){updateNotice.dataset.state='error';updateText.textContent='Error: '+e.message;reload.hidden=true;}finally{checkingTemplate=false;syncNow.disabled=false;}}
 syncNow.addEventListener('click',async()=>{if(!ready){await checkTemplate(true);return;}if(busy)return;lock(true);try{if(dirty)await save();const result=await request('synchronize',{});setStatus(result.message);if(result.hash!==templateHash){location.reload();return;}await checkTemplate(true);}catch(e){setStatus(e.message,true);updateText.textContent=e.message;}finally{lock(false);}});
 document.querySelectorAll('.worksheet-tab').forEach(tab=>tab.addEventListener('click',e=>{if(dirty&&!confirm('Discard unsaved edits and switch worksheets?')){e.preventDefault();return;}dirty=false;}));
 const zoomMenu=document.getElementById('zoom-menu');document.getElementById('zoom').addEventListener('change',()=>{zoomMenu.open=false;zoomMenu.querySelector('summary').focus();});
 document.addEventListener('pointerdown',e=>{if(!zoomMenu.contains(e.target))zoomMenu.open=false;});
 document.addEventListener('keydown',e=>{if(e.key==='Escape'&&zoomMenu.open){zoomMenu.open=false;zoomMenu.querySelector('summary').focus();}});
 setInterval(checkTemplate,10000);checkTemplate();
 open();
})();


