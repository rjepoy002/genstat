'use strict';
const assert=require('assert'),{parse}=require('../assets/formula-references.js');
function refs(formula){const result=parse(formula,'Jun26');assert.equal(result.error,null);return result.references;}
assert.equal(refs('=A1')[0].from,'A1');assert.equal(refs('=SUM(A1:B12)')[0].r2,12);
assert.equal(refs('=$A$1+A$2+$B3').length,3);assert(refs('=Jun26!A1')[0].local);
assert.equal(refs('=IF(A1>0,VLOOKUP(B2,$C$1:$D$20,2,FALSE),INDEX(E1:E20,MATCH(F1,G1:G20,0)))').length,6);
assert.equal(refs('="A1"&"He said ""B12"""&SUM(A2,My_A1,Table1[A3],LOG10(B2),1E10)').length,2);
assert.equal(refs("='RecovBill26'!B12+A1").filter(r=>r.local).length,1);
assert(refs("='O''Brien'!$B$12")[0].sheet==="O'Brien");
assert(refs('=[1]RecovBill26!B12')[0].external);assert(refs("='[GENSTAT_2025.xlsx]Jun26'!A1")[0].external);
assert.equal(refs('=SUM(Table1[[#Headers],[A1]],Named_Range,XFE1,LOG10(A1))').length,1);
assert.equal(refs('=SUM(A1:XFD1048576)').length,1);assert.equal(refs('=A1+A1').length,1);
assert(refs('=Sheet1:Sheet3!A1')[0].threeD);assert(parse('=SUM(A1','Jun26').error);assert(parse('="A1','Jun26').error);assert(parse('=A1:','Jun26').error);
console.log('PASS: single/range/mixed references, nested unsupported functions, string escaping, names, tables, external workbooks, quoted sheets, 3D indicators, large ranges and invalid formulas');
