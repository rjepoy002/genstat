'use strict';
// Display-only OOXML A1 reference tokenizer. It never evaluates formulas.
((root)=>{
 const nameChar=c=>Boolean(c&&/[\p{L}\p{N}_.$\\]/u.test(c));
 function address(text){
  const match=/^\$?([A-Za-z]{1,3})\$?([1-9][0-9]*)$/.exec(text);if(!match)return null;
  let col=0;for(const char of match[1].toUpperCase())col=col*26+char.charCodeAt(0)-64;
  const row=Number(match[2]);return col<=16384&&row<=1048576?{col,row}:null;
 }
 function tokenize(formula){
  if(typeof formula!=='string'||!formula.startsWith('=')||formula.length>32768)throw new Error('Formula cannot be inspected.');
  const tokens=[];let i=1,depth=0;
  while(i<formula.length){const c=formula[i];if(/\s/.test(c)){i++;continue;}
   if(c==='"'||c==="'"){
    const quote=c;let value='',closed=false;i++;
    while(i<formula.length){if(formula[i]===quote){if(formula[i+1]===quote){value+=quote;i+=2;}else{i++;closed=true;break;}}else value+=formula[i++];}
    if(!closed)throw new Error('Unclosed formula string or worksheet name.');tokens.push({kind:quote==='"'?'string':'quoted',value});continue;
   }
   if(c==='['){let value='',nest=0;do{const ch=formula[i++];value+=ch;if(ch==='[')nest++;if(ch===']')nest--;}while(i<formula.length&&nest);if(nest)throw new Error('Unclosed workbook or structured reference.');tokens.push({kind:'bracket',value});continue;}
   if(/[0-9]/.test(c)||c==='.'&&/[0-9]/.test(formula[i+1]||'')){
    const numeric=/^(?:\d+(?:\.\d*)?|\.\d+)(?:[Ee][+-]?\d+)?/.exec(formula.slice(i))[0];tokens.push({kind:'number',value:numeric});i+=numeric.length;if(nameChar(formula[i]))throw new Error('Invalid numeric formula token.');continue;
   }
   if(nameChar(c)){let value='';while(i<formula.length&&nameChar(formula[i]))value+=formula[i++];tokens.push({kind:'name',value});continue;}
   if(c==='(')depth++;if(c===')'&&--depth<0)throw new Error('Unbalanced formula parentheses.');
   tokens.push({kind:c,value:c});i++;
  }
  if(depth)throw new Error('Unbalanced formula parentheses.');return tokens;
 }
 function parse(formula,currentSheet){
  try{
   const tokens=tokenize(formula),references=[],seen=new Set();let i=0;
   function readReference(start){let n=start,sheet=null,external=false,threeD=false;
    if(tokens[n]?.kind==='bracket'&&(tokens[n+1]?.kind==='name'||tokens[n+1]?.kind==='quoted')&&tokens[n+2]?.kind==='!'){external=true;sheet=tokens[n].value+tokens[n+1].value;n+=3;}
    else if(['name','quoted'].includes(tokens[n]?.kind)&&tokens[n+1]?.kind===':'&&['name','quoted'].includes(tokens[n+2]?.kind)&&tokens[n+3]?.kind==='!'){sheet=tokens[n].value+':'+tokens[n+2].value;threeD=true;n+=4;}
    else if(['name','quoted'].includes(tokens[n]?.kind)&&tokens[n+1]?.kind==='!'){sheet=tokens[n].value;external=/[\[\]\\/]/.test(sheet);n+=2;}
    const first=address(tokens[n]?.kind==='name'?tokens[n].value:'');
    if(!first||tokens[n+1]?.kind==='('||tokens[n+1]?.kind==='bracket')return null;
    const from=tokens[n].value;n++;let last=first,to=from;
    if(tokens[n]?.kind===':'){
     n++;if(['name','quoted'].includes(tokens[n]?.kind)&&tokens[n+1]?.kind==='!'){const endSheet=tokens[n].value;if((sheet||currentSheet).toLowerCase()!==endSheet.toLowerCase())threeD=true;n+=2;}
     const end=address(tokens[n]?.kind==='name'?tokens[n].value:'');if(!end)throw new Error('Incomplete formula range.');last=end;to=tokens[n++].value;
    }
    const local=!external&&!threeD&&(sheet===null||sheet.toLowerCase()===currentSheet.toLowerCase());
    return {end:n,reference:{sheet:sheet||currentSheet,external,threeD,local,from,to,c1:Math.min(first.col,last.col),c2:Math.max(first.col,last.col),r1:Math.min(first.row,last.row),r2:Math.max(first.row,last.row)}};
   }
   while(i<tokens.length){
    // Brackets belonging to tables and quoted literals are opaque tokens.
    const result=readReference(i);if(result){const r=result.reference,key=JSON.stringify(r);if(!seen.has(key)){references.push(r);seen.add(key);}i=result.end;}
    else{if(['name','quoted','bracket'].includes(tokens[i].kind)&&tokens[i+1]?.kind==='!'){i+=2;if(tokens[i]?.kind==='name')i++;}else i++;}
    if(references.length>512)throw new Error('Too many references to highlight.');
   }
   return {references,error:null};
  }catch(error){return {references:[],error:error.message};}
 }
 root.GenStatFormulaReferences={parse};
 if(typeof module!=='undefined'&&module.exports)module.exports=root.GenStatFormulaReferences;
})(typeof window==='undefined'?globalThis:window);
