'use strict';
// Session-only cell commands. Saving a draft does not erase editing history.
class GenstatEditHistory {
 constructor(limit=50){this.limit=limit;this.past=[];this.future=[];}
 record(command){if(JSON.stringify(command.before)===JSON.stringify(command.after))return;this.past.push(command);if(this.past.length>this.limit)this.past.shift();this.future=[];}
 peek(direction){return (direction==='undo'?this.past:this.future).at(-1);}
 commit(direction){const from=direction==='undo'?this.past:this.future,to=direction==='undo'?this.future:this.past;const command=from.pop();if(command)to.push(command);}
 clear(){this.past=[];this.future=[];}
}
if(typeof window!=='undefined')window.GenstatEditHistory=GenstatEditHistory;
if(typeof module!=='undefined')module.exports=GenstatEditHistory;
