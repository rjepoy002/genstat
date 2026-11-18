'use strict';
const {Parser}=require('node-sql-parser');
// A lexical pass handles quoting/parameters; a real MySQL grammar/AST performs
// structural validation. This layer is complemented by SELECT-only DB grants.
function validate(input){
 const {sql,allowedTables,database}=input;
 if(typeof sql!=='string'||!sql.trim()||sql.length>40000)throw Error('Enter one SQL statement (maximum 40,000 characters).');
 let prepared='',plain='',params=[],i=0;
 while(i<sql.length){const c=sql[i];
  if(c==='\\'||c==='\0')throw Error('Backslash escapes and NUL characters are not supported.');
  if(c==="'"||c==='"'||c==='`'){const quote=c;let token=c;i++;let closed=false;while(i<sql.length){const ch=sql[i++];if(ch==='\\'||ch==='\0')throw Error('Backslash escapes are not supported.');token+=ch;if(ch===quote){if(sql[i]===quote){token+=sql[i++];continue;}closed=true;break;}}if(!closed)throw Error('Unterminated SQL string or identifier.');prepared+=token;plain+=' quoted ';continue;}
  if(c==='#'||sql.slice(i,i+2)==='--'||sql.slice(i,i+2)==='/*')throw Error('SQL comments and executable hints are not permitted.');
  if(c==='@'||c==='?')throw Error('Server/user variables and positional parameters are not permitted.');
  if(c===':'){const m=sql.slice(i).match(/^:([A-Za-z_][A-Za-z0-9_]*)/);if(!m||m[1]!=='ReportMonth')throw Error('Only :ReportMonth is supported.');params.push(m[1]);prepared+='?';plain+=' :ReportMonth ';i+=m[0].length;continue;}
  if(c===';'){if(sql.slice(i+1).trim())throw Error('Multiple SQL statements are not permitted.');i++;continue;}
  prepared+=c;plain+=c;i++;
 }
 if(!params.length)throw Error('The query must bind :ReportMonth to the selected worksheet month.');
 if(/\b(INTO|OUTFILE|DUMPFILE|LOCK|UNLOCK|PROCEDURE|SQL_CALC_FOUND_ROWS|HIGH_PRIORITY|STRAIGHT_JOIN|FOR|RETURNING)\b/i.test(plain))throw Error('File access, locking clauses and unsafe SELECT options are not permitted.');
 const parser=new Parser();let ast;
 try{ast=parser.astify(sql.trim().replace(/;\s*$/,''),{database:'MySQL'});}catch{throw Error('The SQL parser could not validate this MySQL SELECT/CTE.');}
 if(Array.isArray(ast)||ast.type!=='select')throw Error('Only one SELECT or WITH … SELECT statement is permitted.');
 const ctes=new Set();function names(n){if(!n||typeof n!=='object')return;for(const w of n.with||[])ctes.add(w.name.value.toLowerCase());for(const v of Object.values(n))if(v&&typeof v==='object'){if(Array.isArray(v))v.forEach(names);else names(v);}}names(ast);
 const safeFunctions=new Set('SUM COUNT AVG MIN MAX FLOOR CEIL CEILING ROUND TRUNCATE ABS COALESCE IFNULL NULLIF IF GREATEST LEAST CAST CONVERT CONCAT CONCAT_WS SUBSTRING SUBSTR LEFT RIGHT TRIM UPPER LOWER LENGTH CHAR_LENGTH DATE DATE_FORMAT YEAR MONTH DAY LAST_DAY STR_TO_DATE DATE_ADD DATE_SUB TIMESTAMPDIFF ROW_NUMBER RANK DENSE_RANK LAG LEAD FIRST_VALUE LAST_VALUE'.split(' '));
 const tables=new Set();let nodes=0;
 function walk(n){if(!n||typeof n!=='object')return;if(++nodes>10000)throw Error('Query is too complex.');
  if(n.type&&['insert','update','delete','replace','drop','alter','truncate','create','call','set','use','var','assign','load','show','explain'].includes(String(n.type).toLowerCase()))throw Error('Non-read-only SQL node rejected.');
  if(n.type==='select'){
   if(n.options?.length||n.locking_read||n.into?.position)throw Error('Unsafe SELECT option rejected.');
   for(const f of n.from||[]){if(f.table){const t=f.table.toLowerCase();if(f.db&&f.db.toLowerCase()!==database.toLowerCase())throw Error('Cross-database access is not allowed.');if(!ctes.has(t)){if(!allowedTables.includes(t))throw Error('Table is outside the approved reporting allowlist: '+t);tables.add(t);}}}
  }
  if(n.type==='function'||n.type==='aggr_func'){
   const name=typeof n.name==='string'?n.name:n.name?.name?.map(p=>p.value).join('');
   if(n.name?.schema||!safeFunctions.has(String(name).toUpperCase()))throw Error('Function is not in the read-only allowlist: '+(name||'unknown'));
  }
  if(n.type==='param'&&n.value!=='ReportMonth')throw Error('Unknown parameter.');
  for(const [k,v] of Object.entries(n)){if(['locking_read','outfile','dumpfile'].includes(k)&&v)throw Error('Unsafe SQL clause rejected.');if(v&&typeof v==='object'){if(Array.isArray(v))v.forEach(walk);else walk(v);}}
 }
 walk(ast);return {prepared:prepared.trim(),params,tables:[...tables]};
}
module.exports={validate};
if(require.main===module){let data='';process.stdin.setEncoding('utf8');process.stdin.on('data',x=>{data+=x;if(data.length>100000)process.exit(1);});process.stdin.on('end',()=>{try{process.stdout.write(JSON.stringify({ok:true,...validate(JSON.parse(data))}));}catch(e){process.stdout.write(JSON.stringify({ok:false,error:e.message}));process.exitCode=1;}});}
