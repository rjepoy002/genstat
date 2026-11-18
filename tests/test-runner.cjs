'use strict';
const assert=require('assert'),{plan,aliases,unit,browser}=require('../tools/run-tests.cjs');
assert.deepEqual(plan(Object.keys(aliases).concat(['tests/query-apply-save-browser.cjs','tests/cell-editing-browser.cjs'])),['tests/query-apply-save-browser.cjs','tests/cell-editing-browser.cjs']);
assert.deepEqual(plan(['tests/query-settings.php','tests/query-settings.php']),['tests/query-settings.php']);
assert.deepEqual(plan([...unit,...browser]),[...unit,...browser]);
assert.deepEqual(plan(['.\\tests\\ui.cjs','./tests/cell-editing-browser.cjs']),['tests/cell-editing-browser.cjs']);
console.log('PASS: compatibility aliases resolve once; distinct suites are preserved.');
