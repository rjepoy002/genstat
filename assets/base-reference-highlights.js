'use strict';
window.createBaseReportReferenceHighlights=function(table,metadata,bar){
 const palette=['#2563EB','#16A34A','#D97706','#DC2626','#0891B2'];
 const mirror=document.querySelector('#report-formula-colors');
 const note=document.querySelector('#report-reference-note');
 const field=bar.parentElement;
 const cells=[...table.querySelectorAll('[data-classification][data-ref]')].map(cell=>{
  const {row,column}=metadata[cell.dataset.ref];
  const end=metadata[cell.dataset.excelRangeEnd];
  return {cell,row,column,lastRow:end?.row??row,lastColumn:end?.column??column};
 });
 const edges=['top','right','bottom','left'];
 const cache=new Map();
 let marked=[],previous=null;
 const syncScroll=()=>{mirror.scrollLeft=bar.scrollLeft;};
 bar.addEventListener('scroll',syncScroll);
 bar.addEventListener('blur',syncScroll);
 bar.addEventListener('input',()=>field.removeAttribute('data-color-formula'));
 function show(formula,sheet){
  const collapsed=table.dataset.totalCollapsed==='true';
  const key=formula?JSON.stringify([formula,sheet]):null;
  const state=key===null?null:JSON.stringify([key,collapsed]);
  if(previous===state){
   field.toggleAttribute('data-color-formula',mirror.hasChildNodes()&&bar.value===formula);
   syncScroll();
   return;
  }
  previous=state;
  for(const cell of marked){
   cell.removeAttribute('data-formula-reference');
   for(const edge of edges)cell.style.removeProperty('--formula-ref-'+edge);
  }
  marked=[];
  field.removeAttribute('data-color-formula');
  mirror.replaceChildren();
  note.hidden=true;note.textContent='';note.title='';
  if(!key)return;
  let parsed=cache.get(key);
  if(!parsed){
   parsed=GenStatFormulaReferences.parse(formula,sheet);
   if(cache.size>=100)cache.delete(cache.keys().next().value);
   cache.set(key,parsed);
  }
  const visibleRows=[...table.querySelectorAll('tr[data-excel-row]:not([hidden])')].map(row=>Number(row.dataset.excelRow));
  const visibleColumns=[...table.querySelectorAll('col[data-excel-column]:not([hidden])')]
   .filter(col=>!collapsed||!col.hasAttribute('data-total-column')).map(col=>Number(col.dataset.excelColumn));
  const visible=cells.filter(({cell})=>!cell.hidden&&!cell.closest('tr[hidden]')&&(!collapsed||!cell.classList.contains('total-column')));
  const highlights=new Map(),unavailable=[];
  parsed.references.forEach((ref,index)=>{
   const label=ref.from+(ref.to!==ref.from?':'+ref.to:'');
   if(!ref.local){unavailable.push(ref.sheet+'!'+label);return;}
   const rows=visibleRows.filter(row=>row>=ref.r1&&row<=ref.r2);
   const columns=visibleColumns.filter(column=>column>=ref.c1&&column<=ref.c2);
   const hits=visible.filter(c=>columns.some(column=>column>=c.column&&column<=c.lastColumn)&&rows.some(row=>row>=c.row&&row<=c.lastRow));
   if(!hits.length){unavailable.push(label);return;}
   const top=Math.min(...hits.map(c=>c.row)),bottom=Math.max(...hits.map(c=>c.lastRow));
   const left=Math.min(...hits.map(c=>c.column)),right=Math.max(...hits.map(c=>c.lastColumn));
   const color=palette[index%palette.length];
   for(const c of hits){
    let sides=highlights.get(c.cell);
    if(!sides){sides={};highlights.set(c.cell,sides);}
    // At coincident edges the first reference keeps its color.
    if(c.row===top)sides.top??=color;
    if(c.lastColumn===right)sides.right??=color;
    if(c.lastRow===bottom)sides.bottom??=color;
    if(c.column===left)sides.left??=color;
   }
  });
  for(const [cell,sides] of highlights){
   cell.setAttribute('data-formula-reference','');
   for(const [edge,color] of Object.entries(sides))cell.style.setProperty('--formula-ref-'+edge,color);
   marked.push(cell);
  }
  if(parsed.occurrences.length){
   const fragment=document.createDocumentFragment();
   let position=0;
   for(const {start,end,index} of parsed.occurrences){
    fragment.append(formula.slice(position,start));
    const token=document.createElement('span');
    token.textContent=formula.slice(start,end);
    token.style.setProperty('--formula-token-color',palette[index%palette.length]);
    fragment.append(token);
    position=end;
   }
   fragment.append(formula.slice(position));
   mirror.append(fragment);
   field.toggleAttribute('data-color-formula',bar.value===formula);
   syncScroll();
  }
  if(parsed.error||parsed.unsupported||unavailable.length){
   note.hidden=false;
   note.textContent=parsed.error?'Formula references unavailable':unavailable.length?'Some references are outside the visible report':'Some reference syntax is unavailable';
   note.title=[parsed.error,parsed.unsupported?'Named, structured, or dynamic references cannot be outlined.':'',...unavailable].filter(Boolean).join('\n');
  }
 }
 return {show};
};
