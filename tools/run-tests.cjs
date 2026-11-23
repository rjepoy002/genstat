'use strict';
const fs=require('fs'),path=require('path'),os=require('os'),{execFile,spawn}=require('child_process');
const {once}=require('events');
const root=path.resolve(__dirname,'..');
const aliases=Object.fromEntries(['query-settings-browser','query-calculation-lifecycle','sql-formula-replacement-browser','brookes-point-review-browser'].map(x=>['tests/'+x+'.cjs','tests/query-apply-save-browser.cjs']));
aliases['tests/ui.cjs']='tests/cell-editing-browser.cjs';
const unit=['query-settings','query-calculation-settings','sql-formula-replacement','brookes-point','base-report-view','auth-setup','editing-policy','auto-refresh','sync-reconciliation','monthly-worksheets','refresh','formulas','number-format','verify','base-report','workbook-storage'].map(x=>'tests/'+x+'.php').concat(['tests/query-parser.cjs','tests/test-runner.cjs','tests/edit-history.cjs','tests/formula-references.cjs']);
const compact=['sql-settings-style','sql-settings-ui','query-calculation-browser','reporting-allowlist-browser','brookes-point-browser','base-report-compact','base-report-browser','recovered-sales-browser'].map(x=>'tests/'+x+'.cjs');
const browser=compact.concat(['query-apply-save-browser','cell-editing-browser','undo-resync-browser','headers-browser'].map(x=>'tests/'+x+'.cjs'));
function plan(files){return [...new Set(files.map(f=>{f=f.replace(/\\/g,'/').replace(/^\.\//,'');return aliases[f]||f;}))];}
function copyDir(from,to){fs.cpSync(from,to,{recursive:true});}
function sandbox(file){const temp=fs.mkdtempSync(path.join(os.tmpdir(),'genstat-test-runner-'));for(const dir of ['lib','tools','assets'])copyDir(path.join(root,dir),path.join(temp,dir));for(const dir of ['tests','config','storage','docs','excel-files/active','excel-files/reference'])fs.mkdirSync(path.join(temp,dir),{recursive:true});for(const f of ['inputs.php','layout.json'])fs.copyFileSync(path.join(root,'config',f),path.join(temp,'config',f));fs.copyFileSync(path.join(root,file),path.join(temp,file));for(const target of ['genstat.xlsx','excel-files/active/genstat_2026.xlsx'])fs.copyFileSync(path.join(root,'genstat.xlsx'),path.join(temp,target));fs.copyFileSync(path.join(root,'excel-files/reference/GENSTAT_BASE.xlsx'),path.join(temp,'excel-files/reference/GENSTAT_BASE.xlsx'));return temp;}
const phpBinary=process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe';
function startCompactServer(fixture){
  const server=spawn(phpBinary,['-d','session.save_path=sessions','-S','127.0.0.1:0','-t',fixture,'router.php'],{cwd:fixture,windowsHide:true,stdio:['ignore','ignore','pipe']});
  let output='';
  const ready=new Promise((resolve,reject)=>{
    const timeout=setTimeout(()=>reject(Error('Compact fixture server startup timed out')),5000);
    const finish=(callback,value)=>{clearTimeout(timeout);callback(value);};
    server.on('error',error=>finish(reject,error));
    server.once('close',()=>finish(reject,Error('Compact fixture server exited: '+output)));
    server.stderr.on('data',chunk=>{
      output=(output+chunk).slice(-4000);
      const address=output.match(/\((http:\/\/127\.0\.0\.1:\d+)\) started/);
      if(address)finish(resolve,address[1]);
    });
  });
  return {server,ready,log:()=>output};
}
function prepareCompact(fixture){
  copyDir(path.join(root,'partials'),path.join(fixture,'partials'));
  for(const name of ['base-report.php','query-settings-api.php'])
    fs.copyFileSync(path.join(root,name),path.join(fixture,name));
  fs.mkdirSync(path.join(fixture,'sessions'));
  // The lifecycle suite owns settings writes. UI suites can only inspect settings.
  fs.writeFileSync(path.join(fixture,'router.php'),`<?php
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/query-settings-api.php') {
    $action = json_decode(file_get_contents('php://input'), true)['action'] ?? '';
    if (!in_array($action, ['metadata','inspect-result','preview-formula','auto-match','test','history'], true)) {
        http_response_code(403);
        return true;
    }
}
return false;
`);
  fs.copyFileSync(path.join(root,'config/query-settings.local.php'),path.join(fixture,'config/query-settings.local.php'));
  fs.symlinkSync(path.join(root,'node_modules'),path.join(fixture,'node_modules'),'junction');
}
async function main(){
  const args=process.argv.slice(2);let files=args.filter(x=>!x.startsWith('--'));const group=args.find(x=>x.startsWith('--group='));if(!files.length)files=group==='--group=unit'?unit:group==='--group=browser'?browser:unit.concat(browser);
  const selected=plan(files);if(args.includes('--list')){console.log(selected.join('\n'));return;}
  const logs=fs.mkdtempSync(path.join(os.tmpdir(),'genstat-test-results-'));let failures=0;
  for(const file of selected){
    let fixture=null,server=null,serverLog=null;
    try{
      if(!unit.concat(browser).includes(file))throw Error('Unsupported standard suite: '+file);if(['formulas','number-format','verify','base-report'].some(x=>file==='tests/'+x+'.php'))fixture=sandbox(file);
      const env={...process.env};
      if(compact.includes(file)){
        fixture=sandbox(file);
        prepareCompact(fixture);
        if(file==='tests/base-report-compact.cjs'&&env.GENSTAT_THEME_SCREENSHOTS){
          const annual='excel-files/active/genstat_2026.xlsx';
          fs.copyFileSync(path.join(root,annual),path.join(fixture,annual));
        }
        if(file==='tests/recovered-sales-browser.cjs'){
          for(const input of ['excel-files/active/genstat_2026.xlsx','tests/recovered-sales-export.php'])
            fs.copyFileSync(path.join(root,input),path.join(fixture,input));
        }
        const hosted=startCompactServer(fixture);
        server=hosted.server;
        serverLog=hosted.log;
        env.GENSTAT_TEST_COMPACT_URL=(await hosted.ready)+'/base-report.php?month=202607';
      }
      const executable=file.endsWith('.php')?phpBinary:process.execPath;
      const result=await new Promise(resolve=>{
        execFile(executable,[file],{cwd:fixture||root,env,encoding:'utf8',maxBuffer:32*1024*1024,windowsHide:true},(error,stdout,stderr)=>resolve({error,stdout,stderr}));
      });
      const output=result.stdout+result.stderr+(result.error&&serverLog?'\n'+serverLog():'');fs.writeFileSync(path.join(logs,path.basename(file)+'.log'),output);if(result.error){failures++;console.error('FAIL '+file+': '+result.error.message.split('\n')[0]+'\n'+output.slice(-3500));}else console.log('PASS '+file);
    }catch(e){failures++;console.error('FAIL '+file+': '+e.message);
    }finally{
      if(server&&server.pid&&server.exitCode===null&&server.signalCode===null){
        const closed=once(server,'close');
        server.kill();
        await closed;
      }
      if(fixture){const resolved=path.resolve(fixture);if(path.dirname(resolved)!==path.resolve(os.tmpdir())||!path.basename(resolved).startsWith('genstat-test-runner-'))throw Error('Unsafe cleanup target');fs.rmSync(resolved,{recursive:true,force:true});}
    }}
  console.log(`${selected.length-failures}/${selected.length} suites passed. Logs: ${logs}`);process.exitCode=failures?1:0;}
module.exports={plan,aliases,unit,browser};if(require.main===module)main().catch(error=>{console.error(error);process.exitCode=1;});
