'use strict';
window.handleWorksheetEditKey=function(event,{cell,candidates,coordinates,commit,cancel,focus,leave,arrows=false}){
 if(event.defaultPrevented||event.isComposing||event.ctrlKey||event.altKey||event.metaKey)return;
 if(event.key==='Escape'){
  event.preventDefault();cancel();return;
 }
 const arrow=arrows&&['ArrowLeft','ArrowRight','ArrowUp','ArrowDown'].includes(event.key);
 if(!arrow&&event.key!=='Enter'&&event.key!=='Tab')return;
 const horizontal=event.key==='Tab'||event.key==='ArrowLeft'||event.key==='ArrowRight';
 const direction=arrow?(['ArrowLeft','ArrowUp'].includes(event.key)?-1:1):(event.shiftKey?-1:1);
 const {row,column,rowEnd=row,columnEnd=column}=coordinates(cell);
 let next=null,distance=Infinity;
 for(const candidate of candidates()){
  const point=coordinates(candidate);
  const lastRow=point.rowEnd??point.row,lastColumn=point.columnEnd??point.column;
  const step=horizontal
   ?(direction>0?point.column-columnEnd:column-lastColumn)
   :(direction>0?point.row-rowEnd:row-lastRow);
  const sameAxis=horizontal?point.row<=row&&lastRow>=row:point.column<=column&&lastColumn>=column;
  if(sameAxis&&step>0&&step<distance&&!candidate.closest('[hidden],[inert]')&&candidate.getClientRects().length&&getComputedStyle(candidate).visibility==='visible'){
   next=candidate;distance=step;
  }
 }
 event.preventDefault();
 if(!arrow)commit();
 if(next)focus(next);
 else if(horizontal&&!arrow)leave?.(direction);
};
