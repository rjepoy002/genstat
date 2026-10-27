'use strict';
((root)=>{
 root.createGenStatReferenceHighlighter=function(sheet,sheetName,note){
  const cols=[...sheet.querySelectorAll('.col-heading')].map(el=>({n:[...el.textContent].reduce((n,c)=>n*26+c.charCodeAt(0)-64,0),start:parseFloat(el.style.left),size:parseFloat(el.style.width)}));
  const rows=[...sheet.querySelectorAll('.row-heading')].map(el=>({n:Number(el.textContent),start:parseFloat(el.style.top),size:parseFloat(el.style.height)}));
  const layer=document.createElement('div');layer.className='reference-highlights';layer.setAttribute('aria-hidden','true');sheet.append(layer);
  const colors=['#3684c6','#a36ab0','#258b68','#cf8737','#c65d77','#6584a7'];const cache=new Map();let previous=null;
  return {show(formula){
   if(previous===formula)return;previous=formula;layer.replaceChildren();note.hidden=true;note.textContent='';note.title='';if(!formula)return;
   let parsed=cache.get(formula);if(!parsed){parsed=root.GenStatFormulaReferences.parse(formula,sheetName);if(cache.size>=100)cache.delete(cache.keys().next().value);cache.set(formula,parsed);}
   if(parsed.error){note.textContent='References unavailable';note.title=parsed.error;note.hidden=false;return;}
   const fragment=document.createDocumentFragment();const other=[];
   parsed.references.forEach((ref,index)=>{
    if(!ref.local){other.push((ref.external?'External workbook: ':ref.threeD?'Multiple worksheets: ':'Worksheet: ')+ref.sheet+'!'+ref.from+(ref.to!==ref.from?':'+ref.to:''));return;}
    const columns=cols.filter(col=>col.n>=ref.c1&&col.n<=ref.c2),lines=rows.filter(row=>row.n>=ref.r1&&row.n<=ref.r2);if(!columns.length||!lines.length)return;
    const firstCol=columns[0],lastCol=columns[columns.length-1],firstRow=lines[0],lastRow=lines[lines.length-1],overlay=document.createElement('div');
    overlay.className='reference-highlight';overlay.dataset.reference=ref.from+(ref.to!==ref.from?':'+ref.to:'');overlay.style.setProperty('--reference-color',colors[index]||`hsl(${(index*137.508)%360} 45% 48%)`);
    Object.assign(overlay.style,{left:firstCol.start+'px',top:firstRow.start+'px',width:lastCol.start+lastCol.size-firstCol.start+'px',height:lastRow.start+lastRow.size-firstRow.start+'px'});fragment.append(overlay);
   });layer.append(fragment);
   if(other.length){note.hidden=false;note.textContent='↗ '+other.length+' other-sheet reference'+(other.length===1?'':'s');note.title=other.join('\n');}
  }};
 };
})(window);
