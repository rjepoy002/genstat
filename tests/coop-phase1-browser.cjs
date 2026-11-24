'use strict';
const fs=require('node:fs'),os=require('node:os'),path=require('node:path'),crypto=require('node:crypto');
const assert=require('node:assert/strict'),{spawn,execFileSync}=require('node:child_process');
const {once}=require('node:events');
const {chromium}=require(process.env.TEMP+'\\genstat-browser\\node_modules\\playwright');
const root=path.resolve(__dirname,'..');
const fixture=fs.mkdtempSync(path.join(os.tmpdir(),'genstat-coop-phase1-'));
const web=path.join(fixture,'web'),secret=path.join(fixture,'settings.json');
const php=process.env.PHP_BINARY||'C:\\xampp\\php\\php.exe';
const db=action=>execFileSync(php,[path.join(root,'tests/coop-phase1-fixture.php'),action,fixture],{windowsHide:true});
(async()=>{
 let server,browser,serverLog='';
 try{
  fs.mkdirSync(web);
  for(const folder of ['assets','node_modules','excel-files'])
   fs.symlinkSync(path.join(root,folder),path.join(web,folder),'junction');
  for(const folder of ['lib','partials','tools'])fs.cpSync(path.join(root,folder),path.join(web,folder),{recursive:true});
  const httpsClient=path.join(web,'lib','coop-https-client.php');
  const source=fs.readFileSync(httpsClient,'utf8');
  assert.equal(source.split('function coop_https_request(').length,2);
  fs.writeFileSync(httpsClient,source.replace('function coop_https_request(','function fixture_disabled_https_transport(')+
   fs.readFileSync(path.join(root,'tests/coop-https-mock.php'),'utf8').replace(/^<\?php\s*/,'\n'));
  fs.mkdirSync(path.join(web,'config'));
  for(const file of ['inputs.php','layout.json','monthly-source.php','coop-hosted-endpoints.php'])
   fs.copyFileSync(path.join(root,'config',file),path.join(web,'config',file));
  for(const folder of ['storage','sessions'])fs.mkdirSync(path.join(web,folder));
  for(const file of ['base-report.php','query-settings-api.php','coop-phase1-api.php','settings-security-api.php'])
   fs.copyFileSync(path.join(root,file),path.join(web,file));
  db('setup');
  const fixturePin='085271';
  const cli=mode=>execFileSync(php,[path.join(web,'tools/settings-pin.php'),mode],
   {input:fixturePin+'\n'+fixturePin+'\n',windowsHide:true,encoding:'utf8'});
  const settings=JSON.parse(fs.readFileSync(path.join(fixture,'fixture.json')));
  server=spawn(php,['-d','session.save_path=sessions','-S','127.0.0.1:0','-t',web],{
   cwd:web,windowsHide:true,stdio:['ignore','ignore','pipe'],
   env:{...process.env,GENSTAT_COOP_PHASE1_FILE:secret,GENSTAT_COOP_PHASE1_KEY:crypto.randomBytes(32).toString('base64'),
    GENSTAT_COOP_HOSTED_ENDPOINTS:'{}'}
  });
  const origin=await new Promise((resolve,reject)=>{
   const timeout=setTimeout(()=>reject(Error('COOP fixture server timed out.')),5000);
   server.once('error',reject);
   server.stderr.on('data',chunk=>{
    serverLog=(serverLog+chunk.toString()).slice(-50000);
    const address=chunk.toString().match(/\((http:\/\/127\.0\.0\.1:\d+)\) started/);
    if(address){clearTimeout(timeout);resolve(address[1]);}
   });
  });
  browser=await chromium.launch({executablePath:'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',headless:true});
  const page=await browser.newPage({viewport:{width:1200,height:850}}),errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  const url=origin+'/base-report.php?month=202607';
  await page.goto(url);
  assert(await page.locator('#report-data').count(),await page.locator('body').innerText());
  const report=()=>page.evaluate(()=>window.genstatReport.snapshot());
  const before=await report();
  const field=name=>page.locator('#coop-connection [name="'+name+'"]');
  const button=name=>page.locator('#settings-coop').getByRole('button',{name,exact:true});
  const status=message=>page.locator('#coop-status').getByText(message,{exact:true}).waitFor();
  const ready=async()=>{
   await page.locator('#sql-settings-open').click();
   const pinDialog=page.locator('#settings-pin-dialog');
   for(const [index,digit] of [...fixturePin].entries())await pinDialog.getByLabel('PIN digit '+(index+1),{exact:true}).fill(digit);
   await page.locator('#sql-settings-dialog').waitFor();
   await page.locator('#settings-coop-tab').click();
   await page.waitForFunction(()=>!document.getElementById('coop-fields').disabled);
  };
  const api=(action,extra={})=>page.evaluate(async({action,extra})=>{
   const {month,csrf}=window.genstatReport.snapshot();
   const response=await fetch('coop-phase1-api.php',{method:'POST',headers:window.genstatSettings.headers(),
    body:JSON.stringify({month,csrf,action,...extra})});
   return {status:response.status,data:await response.json()};
  },{action,extra});
  const security=async(action,extra={})=>page.evaluate(async ({action,extra})=>{
   const {month,csrf}=window.genstatReport.snapshot();
   const response=await fetch('settings-security-api.php',{method:'POST',headers:{'Content-Type':'application/json'},
    body:JSON.stringify({action,month,csrf,...extra})});
   return {status:response.status,data:await response.json()};
  },{action,extra});
  assert.equal((await api('metadata')).status,403);
  assert.equal((await security('state')).data.configured,false);
  assert.equal((await security('verify',{pin:'12345A'})).status,400);
  assert.match(cli('setup'),/configured/);
  await ready();
  await page.locator('#settings-reporting-tab').click();
  await page.locator('#sql-row-trigger').click();
  const reportOptions=page.locator('#sql-row-options').getByRole('option');
  assert.equal(await reportOptions.count(),5);
  assert((await reportOptions.allTextContents()).every(text=>text.endsWith(' / Not Configured')));
  assert(!(await reportOptions.allTextContents()).some(text=>/coop consumption/i.test(text)));
  assert.deepEqual(await page.locator('#sql-row option').evaluateAll(options=>options.map(option=>option.value)),
   [11,18,24,25,39].map(row=>'genstat-base/Jul26/A'+row));
  await page.locator('#settings-coop-tab').click();
  assert.equal(await field('host').inputValue(),'127.0.0.1');
  assert.equal(await field('username').inputValue(),'root');
  const rootConnection={...settings,username:'root',password:'',emptyPassword:true};
  const localRoot=await api('test-connection',{connection:rootConnection});
  assert.equal(localRoot.status,200);
  assert.equal(localRoot.data.message,'Connection successful.');
  const lanRoot=await api('test-connection',{connection:{...rootConnection,type:'lan'}});
  assert.equal(lanRoot.status,400);
  assert.match(lanRoot.data.error,/dedicated read-only account/);
  assert.equal((await api('test-connection',{connection:{...settings,type:'lan'}})).status,200);
  await field('type').selectOption('hosted');
  assert(await page.getByText('Ensure remote database access is enabled on your hosting server.').isVisible());
  const hosted={...settings,type:'hosted',host:'paleco.net'};
  const invalidHost=await api('test-connection',{connection:{...hosted,host:'https://paleco.net/path?x=1'}});
  assert.equal(invalidHost.status,400);
  assert.match(invalidHost.data.error,/valid database hostname/);
  const unapproved=await api('test-connection',{connection:hosted});
  assert.equal(unapproved.status,400);
  assert.match(unapproved.data.error,/host and port are not approved/);
  await field('type').selectOption('local');
  assert(!await page.getByText('Ensure remote database access is enabled on your hosting server.').isVisible());
  for(const name of ['host','port','database','username'])await field(name).fill(String(settings[name]));
  await field('password').fill('wrong test password');
  await button('Test Connection').click();
  await status('Database authentication failed. Check the credentials and account permissions.');
  assert(await button('Save Connection').isDisabled());
  await field('password').fill(settings.password);
  await button('Test Connection').click();
  await status('Connection successful.');
  await button('Save Connection').click();await status('Connection saved.');
  const management=page.locator('#coop-accounts'),areaSelect=management.locator('#coop-add-area');
  assert.equal(await management.getByRole('heading',{name:'Registered Account Area Summary'}).count(),0);
  assert.deepEqual(await management.getByRole('heading').allTextContents(),['Account Management','All Added Accounts']);
  await areaSelect.locator('option').nth(20).waitFor({state:'attached'});
  assert.equal(await areaSelect.locator('option').count(),21);
  const areaIds=await areaSelect.locator('option').evaluateAll(options=>options.slice(1).map(option=>Number(option.value)));
  const [firstArea,secondArea]=areaIds;
  await areaSelect.selectOption(String(firstArea));
  await management.getByLabel('COOP account code').fill('001');
  await management.getByRole('button',{name:'Add Account',exact:true}).click();
  await management.getByText('Account added.',{exact:true}).waitFor();
  const accountList=management.locator('#coop-account-list');
  const numberCell=accountList.getByRole('cell').filter({has:page.getByRole('button',{name:'Delete account 0001'})});
  assert.equal(await numberCell.textContent(),'0001');
  await accountList.getByRole('cell',{name:'12.35',exact:true}).waitFor();
  const coopCell=ref=>page.locator('td[data-ref="'+ref+'"]');
  await coopCell('C26').locator('.value').getByText('12.35',{exact:true}).waitFor();
  assert.equal(await coopCell('C26').getAttribute('data-sql-applied'),'true');
  assert.equal((await report()).values.C26,before.values.C26);
  await page.locator('#sql-settings-close').click();
  const download=page.waitForEvent('download');
  await page.locator('#report-export').click();
  await (await download).saveAs(path.join(fixture,'coop-export.xlsx'));
  db('check-export');
  db('billing-off');
  try{
   await page.getByRole('button',{name:'Resync COOP'}).click();
   await page.locator('#coop-sync-status').getByText(/COOP sync failed/).waitFor();
   assert.equal(await coopCell('C26').locator('.value').textContent(),'12.35');
   assert.equal((await api('accounts-list')).status,403);
   await ready();
   const unavailable=(await api('accounts-list')).data.rows.find(row=>row.AcctCode==='001');
   assert.deepEqual([unavailable.kwh,unavailable.status],[null,'Unable to retrieve data']);
   await page.locator('#sql-settings-close').click();
  }finally{db('billing-on');}
  await page.getByRole('button',{name:'Resync COOP'}).click();
  await page.waitForFunction(()=>!document.querySelector('#coop-resync').disabled&&document.querySelector('#coop-sync-status').textContent==='');
  await ready();
  assert.equal(await management.getByLabel('COOP account code').inputValue(),'');
  for(const [code,area,response] of [
   ['001',secondArea,409],['005',secondArea,409],
   ['002',firstArea,200],['003',firstArea,200],['004',firstArea,200]
  ])assert.equal((await api('accounts-add',{code,area})).status,response,code);
  const registrations=(await api('accounts-list')).data;
  assert.equal(registrations.areas.length,20);
  assert.equal(registrations.areas.find(area=>area.id===firstArea).total,'12.35');
  assert.equal(registrations.areas.find(area=>area.id===firstArea).incomplete,2);
  assert.equal(registrations.rows.find(account=>account.AcctCode==='002').kwh,'0.00');
  assert.equal(registrations.rows.find(account=>account.AcctCode==='003').status,'Incomplete billing data');
  assert.match(registrations.rows.find(account=>account.AcctCode==='004').status,/No billing record/);
  assert.equal((await api('accounts-list',{month:'202608'})).status,403);
  await accountList.getByRole('button',{name:'Edit Area for 001'}).click();
  await accountList.getByLabel('New GENSTAT area for 001').selectOption(String(secondArea));
  await accountList.getByRole('button',{name:'Transfer',exact:true}).click();
  await page.locator('#report-confirmation').getByRole('button',{name:'Continue'}).click();
  await management.getByText('Account transferred.',{exact:true}).waitFor();
  const filterButton=management.getByRole('button',{name:/^Filter/});
  const filters=page.getByRole('dialog',{name:'Filter accounts'});
  const editFilters=async(search,area)=>{
   await filterButton.click();
   await filters.getByLabel('Search Accounts').fill(search);
   await filters.getByLabel('GENSTAT Area').selectOption(String(area));
  };
  const apply=()=>filters.getByRole('button',{name:'Apply Filter'}).click();
  assert.equal(await management.getByLabel('Search Accounts').isVisible(),false);
  assert.equal(await page.getByRole('heading',{name:'Monthly Preview'}).count(),0);
  await editFilters('Fixture Alpha','');
  await apply();
  await management.getByText('4 registered / 1 matching / Page 1 of 1',{exact:true}).waitFor();
  await editFilters('Fixture Alpha',firstArea);
  await apply();
  await management.getByText('4 registered / 0 matching / Page 1 of 1',{exact:true}).waitFor();
  assert.equal(await filterButton.locator('#coop-filter-count').textContent(),'2');
  await editFilters('unsaved filter',secondArea);
  await filters.getByRole('button',{name:'Close filters'}).click();
  await management.getByText('4 registered / 0 matching / Page 1 of 1',{exact:true}).waitFor();
  await filterButton.click();
  assert.equal(await filters.getByLabel('Search Accounts').inputValue(),'Fixture Alpha');
  assert.equal(await filters.getByLabel('GENSTAT Area').inputValue(),String(firstArea));
  await filters.getByLabel('GENSTAT Area').selectOption(String(secondArea));
  await apply();
  await management.getByText('4 registered / 1 matching / Page 1 of 1',{exact:true}).waitFor();
  const metadata=await api('metadata');
  const failedHosted=(await api('test-connection',{connection:hosted}));
  assert.equal(failedHosted.status,400);
  const untestedSave=await api('save-connection',{connection:hosted,confirmed:true,revision:metadata.data.revision});
  assert.equal(untestedSave.status,400);
  assert.deepEqual((await api('metadata')).data.connection,metadata.data.connection);
  assert.equal(metadata.data.hasPassword,true);
  assert(!Object.hasOwn(metadata.data.connection,'password'));
  assert(!fs.readFileSync(secret,'utf8').includes(settings.password));
  assert.equal((await api('metadata',{csrf:'invalid'})).status,403);
  await page.reload();await ready();
  assert.equal(await field('password').inputValue(),'');
  assert.match(await page.locator('#coop-password-state').textContent(),/stored/);
  assert.equal(await field('database').inputValue(),settings.database);
  await field('password').fill('invalid replacement');
  await button('Test Connection').click();
  await status('Database authentication failed. Check the credentials and account permissions.');
  assert.equal((await api('metadata')).data.revision,metadata.data.revision);
  await field('password').fill('');
  await button('Test Connection').click();
  await status('Connection successful.');
  await button('Save Connection').click();
  await page.locator('#report-confirmation').getByRole('button',{name:'Cancel'}).click();
  assert.equal((await api('metadata')).data.revision,metadata.data.revision);
  const query='SELECT Area, KWH FROM monthly WHERE BillMonth = :ReportMonth ORDER BY Area';
  assert.equal((await api('test-query',{query:'DELETE FROM monthly WHERE BillMonth = :ReportMonth'})).status,400);
  assert.equal((await api('test-query',{query:query+'; SELECT 1'})).status,400);
  assert.equal((await api('test-query',{query:'SELECT SLEEP(1) AS KWH FROM monthly WHERE BillMonth = :ReportMonth'})).status,400);
  await page.locator('#coop-query').fill(query);
  await button('Test Query').click();await status('Query passed for the selected month.');
  await button('Save Query').click();await status('Query saved.');
  const julyPreview=(await api('preview')).data;
  assert.equal(julyPreview.total,'124.123456790');
  assert.equal(julyPreview.count,2);
  assert(julyPreview.rows.some(row=>row.includes('Narra')));
  assert.deepEqual(await report(),before);
  await page.locator('#sql-settings-close').click();
  await page.locator('#report-month').selectOption('202608');
  await page.waitForFunction(()=>window.genstatReport.snapshot().month==='202608');
  await page.emulateMedia({colorScheme:'dark'});
  await ready();await status('Connection settings loaded.');
  await accountList.getByRole('cell',{name:'22.55',exact:true}).waitFor();
  await coopCell('O26').locator('.value').getByText('22.55',{exact:true}).waitFor();
  const augustAccounts=(await api('accounts-list')).data;
  assert.equal(augustAccounts.areas.find(area=>area.id===secondArea).total,'22.55');
  assert.equal(await accountList.getByRole('columnheader',{name:'Actions',exact:true}).count(),0);
  const deleteAccount=accountList.getByRole('cell',{name:/0001/}).getByRole('button',{name:'Delete account 0001'});
  assert.equal(await deleteAccount.getAttribute('title'),'Delete account');
  await deleteAccount.click();
  const confirmation=page.locator('#report-confirmation');
  const assignedArea=await areaSelect.locator('option[value="'+secondArea+'"]').textContent();
  const confirmationText=await confirmation.locator('#report-confirmation-message').textContent();
  assert(confirmationText.includes('0001')&&confirmationText.includes(assignedArea));
  await confirmation.getByRole('button',{name:'Cancel'}).click();
  assert.equal((await api('accounts-list')).data.registered,4);
  await deleteAccount.click();
  await page.locator('#report-confirmation').getByRole('button',{name:'Continue'}).click();
  await management.getByText('Account removed.',{exact:true}).waitFor();
  await coopCell('O26').locator('.value').getByText('0.00',{exact:true}).waitFor();
  assert.equal(await deleteAccount.count(),0);
  const afterDelete=(await api('accounts-list')).data;
  assert.equal(afterDelete.registered,3);
  const clearedArea=afterDelete.areas.find(area=>area.id===secondArea);
  assert.equal(clearedArea.count,0);
  assert.equal(clearedArea.total,'0.00');
  db('seed-pagination');
  await editFilters('Page','');await apply();
  await management.getByText('28 registered / 25 matching / Page 1 of 1',{exact:true}).waitFor();
  await filterButton.click();
  await filters.getByRole('button',{name:'Clear',exact:true}).click();
  assert.equal(await filterButton.locator('#coop-filter-count').isVisible(),false);
  await management.getByText('28 registered / 28 matching / Page 1 of 2',{exact:true}).waitFor();
  assert.equal(await accountList.locator('tbody tr').count(),25);
  await management.getByRole('button',{name:'Next',exact:true}).click();
  await management.getByText('28 registered / 28 matching / Page 2 of 2',{exact:true}).waitFor();
  assert.equal(await accountList.locator('tbody tr').count(),3);
  await editFilters('Page','');await apply();
  await management.getByText('28 registered / 25 matching / Page 1 of 1',{exact:true}).waitFor();
  const augustPreview=(await api('preview')).data;
  assert.equal(augustPreview.month,'202608');
  assert.equal(augustPreview.total,'2.250000000');
  assert.equal(augustPreview.count,1);
  assert(!augustPreview.rows.some(row=>row.includes('Narra')));
  assert.equal(await page.locator('#settings-coop').evaluate(el=>getComputedStyle(el).colorScheme),'dark');
  await page.setViewportSize({width:390,height:760});
  assert(await page.locator('#sql-settings-dialog').evaluate(el=>el.getBoundingClientRect().right<=innerWidth));
  await page.locator('#sql-settings-close').click();
  await page.locator('#report-month').selectOption('202607');
  await page.waitForFunction(()=>window.genstatReport.snapshot().month==='202607');
  await ready();
  const accountQuery='SELECT AreaID, AreaName, AccountID, AccountName, KWH FROM accounts WHERE BillMonth = :ReportMonth';
  const duplicate=await api('test-query',{query:accountQuery});
  assert.equal(duplicate.status,400);
  assert.match(duplicate.data.error,/multiple areas/);
  const empty=await api('test-query',{query:accountQuery+" AND AreaID = '00'"});
  assert.equal(empty.status,200);
  assert.deepEqual(empty.data.areas,[]);
  const timeout=await api('test-query',{query:accountQuery.replace('FROM accounts','FROM slow_accounts')});
  assert.equal(timeout.status,400);
  assert.match(timeout.data.error,/timed out/);
  const selectedQuery=accountQuery+" AND AccountName <> 'Duplicate account'";
  await page.locator('#coop-query').fill(selectedQuery);
  await button('Test Query').click();await status('Query passed for the selected month.');
  const narra=await api('account-details',{query:selectedQuery,area:'02'});
  assert.equal(narra.data.rows[0].account,'7000');
  const main=await api('account-details',{query:selectedQuery,area:'01',page:2});
  assert.equal(main.data.pages,11);
  assert.equal(main.data.page,2);
  const found=await api('account-details',{query:selectedQuery,area:'01',search:'Fixture 0510'});
  assert.equal(found.data.rows[0].account,'0510');
  await button('Save Query').click();
  await page.locator('#report-confirmation').getByRole('button',{name:'Continue'}).click();
  await status('Query saved.');
  const fullJuly=(await api('preview')).data;
  assert.equal(fullJuly.count,511);
  assert.equal(fullJuly.total,'512.500000510');
  await page.locator('#sql-settings-close').click();
  await page.locator('#report-month').selectOption('202608');
  await page.waitForFunction(()=>window.genstatReport.snapshot().month==='202608');
  await ready();await status('Connection settings loaded.');
  const fullAugust=await api('account-details',{area:'01'});
  assert.deepEqual(fullAugust.data.rows.map(row=>row.account),['9999']);
  const pendingCode='70-0550-0750';
  const cagayancillo=19;
  const insertLog=new Promise(resolve=>{
   const log=chunk=>{
    if(chunk.toString().includes("COOP registry accounts-add failed (MariaDB 1048, SQLSTATE 23000): Column 'AcctNo' cannot be null")){
     server.stderr.off('data',log);
     resolve(true);
    }
   };
   server.stderr.on('data',log);
  });
  db('require-canonical');
  try{
   const blocked=await api('accounts-add',{code:pendingCode,area:cagayancillo});
   assert.equal(blocked.status,503);
   assert.match(blocked.data.error,/Apply GENSTAT migration 006/);
   const logged=await Promise.race([insertLog,require('node:timers/promises').setTimeout(500,false)]);
   assert.equal(logged,true);
  }finally{db('allow-pending');}
  await areaSelect.selectOption(String(cagayancillo));
  await management.getByLabel('COOP account code').fill(pendingCode);
  await management.getByRole('button',{name:'Add Account',exact:true}).click();
  await management.getByText('Account added.',{exact:true}).waitFor();
  const accountRow=code=>accountList.getByRole('row').filter({has:page.getByRole('cell',{name:code,exact:true})});
  await accountRow(pendingCode).getByRole('cell',{name:'Account not found',exact:true}).waitFor();
  assert.equal((await api('accounts-add',{code:pendingCode,area:firstArea})).status,409);
  assert.equal((await api('accounts-add',{code:'AMB',area:firstArea})).status,200);
  const registry=async(area=cagayancillo)=>(await api('accounts-list',{area})).data;
  const pending=(await registry()).rows.find(row=>row.AcctCode===pendingCode);
  assert.deepEqual([pending.AcctNo,pending.AccountName,pending.kwh],[null,null,null]);
  assert.equal((await registry(firstArea)).rows.find(row=>row.AcctCode==='AMB').status,'Multiple active account matches');
  await page.reload();await ready();
  await accountRow(pendingCode).getByRole('cell',{name:'Account not found',exact:true}).waitFor();
  db('connection-off');
  try{
   assert.equal((await api('accounts-add',{code:'OFFLINE',area:firstArea})).status,200);
   assert.equal((await registry()).rows.find(row=>row.AcctCode===pendingCode).status,'Unable to retrieve data');
   await editFilters(pendingCode,'');await apply();
   await accountRow(pendingCode).getByRole('cell',{name:'Unable to retrieve data',exact:true}).waitFor();
  }finally{db('connection-on');}
  db('seed-inactive');
  assert.equal((await api('accounts-add',{code:'INACTIVE',area:firstArea})).status,200);
  const inactiveOnly=(await api('accounts-list',{search:'INACTIVE'})).data.rows.find(row=>row.AcctCode==='INACTIVE');
  assert.deepEqual([inactiveOnly.AcctNo,inactiveOnly.kwh,inactiveOnly.status],[null,null,'No active account']);
  await editFilters(pendingCode,'');await apply();
  await accountRow(pendingCode).getByRole('cell',{name:'No active account',exact:true}).waitFor();
  assert.equal((await registry()).areas.find(area=>area.id===cagayancillo).total,null);
  db('activate-pending');
  await editFilters(pendingCode,'');await apply();
  await accountRow(pendingCode).getByRole('cell',{name:'9.50',exact:true}).waitFor();
  await coopCell('M26').locator('.value').getByText('9.50',{exact:true}).waitFor();
  const resolved=(await registry()).rows.find(row=>row.AcctCode===pendingCode);
  assert.deepEqual([resolved.ID,resolved.AcctNo,resolved.AccountName,resolved.kwh],[pending.ID,'0070','Fixture Pending','9.50']);
  const conflict=(await api('accounts-list',{search:'OFFLINE'})).data;
  assert.equal(conflict.rows[0].status,'Canonical account already registered');
  assert.equal(conflict.rows[0].kwh,null);
  assert.equal(conflict.areas.find(area=>area.id===cagayancillo).total,'9.50');
  assert.equal(conflict.areas.find(area=>area.id===firstArea).total,null);
  assert(conflict.areas.find(area=>area.id===firstArea).incomplete>0);
  assert.equal((await api('accounts-add',{code:'STILL-MISSING',area:cagayancillo})).status,200);
  await page.evaluate(()=>window.genstatReport.refreshCoop());
  assert.match(await page.locator('#coop-sync-status').textContent(),/COOP sync incomplete.*M26/);
  assert.equal(await coopCell('M26').locator('.value').textContent(),'9.50');
  db('deactivate-pending');
  await page.evaluate(()=>window.genstatReport.refreshCoop());
  assert.notEqual(await coopCell('M26').locator('.value').textContent(),'9.50');
  const inactive=(await registry()).rows.find(row=>row.AcctCode===pendingCode);
  assert.deepEqual([inactive.ID,inactive.GenstatAreaID,inactive.AcctNo,inactive.kwh,inactive.status],
   [pending.ID,cagayancillo,null,null,'No active account']);

  const https={type:'https',apiUrl:'https://paleco.net/genstat-api/coop.php',apiKey:'fixture-secret'};
  const prior=(await api('metadata')).data;
  for(const [apiKey,message] of [
   ['bad-key','Invalid API key'],['database-off','Hosted database connection failed'],
   ['bad-json','Invalid API response'],['bad-version','Unsupported API version'],
   ['timeout','API request timed out'],['redirect','redirected the request'],['missing-endpoint','API endpoint not found'],
  ]){
   const failed=await api('test-connection',{connection:{...https,apiKey}});
   assert.equal(failed.status,400);
   assert(failed.data.error.includes(message),failed.data.error);
  }
  assert.equal((await api('test-connection',{connection:{...https,apiUrl:'https://other.example/coop.php'}})).status,400);
  assert.deepEqual((await api('metadata')).data.connection,prior.connection);

  await field('type').selectOption('https');
  assert.deepEqual(await field('type').locator('option').allTextContents(),['Local XAMPP','Remote XAMPP (LAN)','Remote Hosted MySQL','Remote HTTPS API']);
  assert.equal(await field('apiUrl').inputValue(),https.apiUrl);
  assert(!await field('host').isVisible());
  assert(!await button('Test Query').isVisible());
  assert(await page.getByText('Consumption data is retrieved through the configured HTTPS API.').isVisible());
  await field('apiKey').fill(https.apiKey);
  await page.getByRole('button',{name:'Show API key'}).click();
  assert.equal(await field('apiKey').getAttribute('type'),'text');
  await page.getByRole('button',{name:'Hide API key'}).click();
  const testResponse=page.waitForResponse(response=>response.url().endsWith('/coop-phase1-api.php')&&
   response.request().postDataJSON().action==='test-connection');
  await button('Test Connection').click();
  await status('Testing connection…');
  assert(await button('Save Connection').isDisabled());
  await testResponse;
  await status('Connection successful. API Version: 1.1. Hosted Database: Connected.');
  assert.equal((await api('save-connection',{connection:{...https,apiKey:'bad-key'},confirmed:true,revision:prior.revision})).status,400);
  await button('Save Connection').click();
  await page.locator('#report-confirmation').getByRole('button',{name:'Continue'}).click();
  await status('Connection saved. API Version: 1.1. Hosted Database: Connected.');
  const saved=(await api('metadata')).data;
  assert.equal(saved.connection.hasApiKey,true);
  assert(saved.connection.lastSuccessfulTest);
  assert(!JSON.stringify(saved).includes(https.apiKey));
  assert(!fs.readFileSync(secret,'utf8').includes(https.apiKey));
  assert.equal((await api('test-query',{query:'SELECT 1'})).status,400);
  await accountRow(pendingCode).getByRole('cell',{name:'8.00',exact:true}).waitFor();
  await coopCell('M26').locator('.value').getByText('8.00',{exact:true}).waitFor();
  const hostedPending=(await registry()).rows.find(row=>row.AcctCode===pendingCode);
  assert.deepEqual([hostedPending.ID,hostedPending.AcctNo,hostedPending.GenstatAreaID],[pending.ID,'RX2018780000305',cagayancillo]);
  assert.equal((await api('accounts-add',{code:'03-0152-0325',area:firstArea})).status,200);
  assert.equal((await api('accounts-add',{code:'03-0152-0325',area:firstArea})).status,409);
  assert.equal((await api('accounts-add',{code:'HTTP-MISSING',area:firstArea})).status,200);
  const missingHosted=(await api('accounts-list',{search:'HTTP-MISSING'})).data.rows[0];
  assert.equal(missingHosted.status,'Account not found');
  assert.equal((await api('accounts-remove',{id:missingHosted.ID,expectedArea:firstArea,confirmed:true})).status,200);
  assert.equal((await api('accounts-list',{search:'HTTP-MISSING'})).data.matching,0);
  await page.locator('#sql-settings-close').click();
  await page.locator('#report-month').selectOption('202607');
  await page.waitForFunction(()=>window.genstatReport.snapshot().month==='202607');
  await coopCell('M26').locator('.value').getByText('40.00',{exact:true}).waitFor();
  await coopCell('C26').locator('.value').getByText(/^1,?407\.00$/).waitFor();
  assert.match(await coopCell('M26').getAttribute('title'),/SQL-controlled COOP Consumption/);
  await ready();
  await accountRow(pendingCode).getByRole('cell',{name:'40.00',exact:true}).waitFor();
  await accountRow('03-0152-0325').getByRole('cell',{name:'1407.00',exact:true}).waitFor();
  for(const [code,display] of [['HTTP-ZERO','0.00'],['HTTP-NO-BILL','No billing record']]){
   assert.equal((await api('accounts-add',{code,area:firstArea})).status,200);
   const row=(await api('accounts-list',{search:code})).data.rows[0];
   assert.equal(row.kwh??row.status,display);
   assert.equal((await api('accounts-remove',{id:row.ID,expectedArea:firstArea,confirmed:true})).status,200);
  }
  assert.equal(await field('apiKey').inputValue(),'');
  assert.equal(await field('apiKey').getAttribute('placeholder'),'••••••••');
  await button('Test Connection').click();
  await status('Connection successful. API Version: 1.1. Hosted Database: Connected.');
  await field('type').selectOption('local');
  assert.equal(await field('database').inputValue(),settings.database);
  assert(await button('Test Query').isVisible());
  await button('Test Connection').click();await status('Connection successful.');
  await button('Save Connection').click();
  await page.locator('#report-confirmation').getByRole('button',{name:'Continue'}).click();
  await status('Connection saved.');
  const restored=(await api('metadata')).data;
  assert.equal(restored.connection.type,'local');
  assert.equal(restored.query,selectedQuery);
  assert.equal(restored.queryReady,true);
  assert.equal(restored.profiles.https.hasApiKey,true);
  assert(!serverLog.includes(https.apiKey));
  await page.locator('#sql-settings-close').click();
  assert.equal((await api('metadata')).status,403);
  const sqlStatus=await page.evaluate(async()=>{
   const response=await fetch('query-settings-api.php',{method:'POST',headers:window.genstatSettings.headers(),
    body:JSON.stringify({...window.genstatReport.snapshot(),action:'metadata'})});
   return response.status;
  });
  assert.equal(sqlStatus,403);
  assert.equal((await security('appearance')).status,403);
  const pinDialog=page.locator('#settings-pin-dialog'),firstDigit=pinDialog.getByLabel('PIN digit 1',{exact:true});
  await page.locator('#sql-settings-open').click();
  await firstDigit.press('A');
  assert.equal(await firstDigit.inputValue(),'');
  await firstDigit.press('0');
  await pinDialog.getByLabel('PIN digit 2',{exact:true}).press('ArrowLeft');
  assert.equal(await firstDigit.evaluate(input=>document.activeElement===input),true);
  const paste=value=>firstDigit.evaluate((input,value)=>{
   const clipboardData=new DataTransfer();clipboardData.setData('text',value);
   input.dispatchEvent(new ClipboardEvent('paste',{clipboardData,bubbles:true,cancelable:true}));
  },value);
  await paste('NOTPIN');
  assert.equal(await firstDigit.inputValue(),'0');
  await paste('001739');
  await pinDialog.getByText(/Incorrect security PIN.*Remaining attempts: 4/).waitFor();
  assert.equal(await firstDigit.inputValue(),'');
  for(let count=3;count>=0;count--){
   const failed=await security('verify',{pin:'001739'});
   assert.equal(failed.status,count?401:429);
   assert.equal(failed.data.remaining,count);
   if(!count)assert.equal(failed.data.lockedFor,300);
  }
  await page.reload();await page.locator('#sql-settings-open').click();
  await pinDialog.getByText(/Too many attempts. Try again in/).waitFor();
  assert(await firstDigit.isDisabled());
  assert.equal((await security('verify',{pin:fixturePin})).status,429);
  db('expire-settings-lockout');
  await pinDialog.press('Escape');
  await page.locator('#sql-settings-open').click();
  await firstDigit.waitFor();
  let verificationRequests=0;
  page.on('request',request=>{
   if(request.url().endsWith('/settings-security-api.php')&&request.postDataJSON().action==='verify')verificationRequests++;
  });
  await firstDigit.fill('0');
  await paste(fixturePin);
  await page.locator('#sql-settings-dialog').waitFor();
  assert.equal(verificationRequests,1);
  const context=await browser.newContext(),second=await context.newPage();
  await second.goto(url);
  const secondGrant=await second.evaluate(async pin=>{
   const {month,csrf}=window.genstatReport.snapshot();
   const response=await fetch('settings-security-api.php',{method:'POST',body:JSON.stringify({action:'verify',pin,month,csrf})});
   return response.json();
  },fixturePin);
  assert(secondGrant.token);
  await page.locator('#settings-security-tab').click();
  await page.getByRole('button',{name:'Change PIN',exact:true}).click();
  const change=page.getByRole('dialog',{name:'Change PIN'});
  await change.getByLabel('Current PIN',{exact:true}).fill(fixturePin);
  const replacementPin='073586';
  await change.getByLabel('New PIN',{exact:true}).fill(replacementPin);
  await change.getByLabel('Confirm New PIN',{exact:true}).fill(replacementPin);
  await change.getByRole('button',{name:'Save Changes'}).click();
  await page.locator('#report-confirmation').getByRole('button',{name:'Continue'}).click();
  await page.getByText('Security PIN changed successfully.',{exact:true}).waitFor();
  assert.equal((await security('verify',{pin:fixturePin})).status,401);
  assert.equal((await security('verify',{pin:replacementPin})).status,200);
  assert.equal(await second.evaluate(async authorization=>{
   const {month,csrf}=window.genstatReport.snapshot();
   return (await fetch('settings-security-api.php',{method:'POST',body:JSON.stringify({action:'status',authorization,month,csrf})})).status;
  },secondGrant.token),403);
  await context.close();
  await page.locator('#sql-settings-open').click();
  await firstDigit.fill('0');
  await paste(replacementPin);
  await page.locator('#sql-settings-dialog').waitFor();
  const session=(await page.context().cookies()).find(cookie=>cookie.name==='PHPSESSID');
  assert.match(session.value,/^[a-zA-Z0-9,-]+$/);
  const expireScript=path.join(web,'expire-settings-session.php');
  fs.writeFileSync(expireScript,`<?php
if(PHP_SAPI!=='cli')exit;
session_save_path(__DIR__.'/sessions');
session_id($argv[1]);
session_start();
if(!isset($_SESSION['settingsAuthorization']))exit(1);
$_SESSION['settingsAuthorization']['expires']=1;
session_write_close();
`);
  execFileSync(php,[expireScript,session.value],{windowsHide:true});
  await page.getByText('Settings have been locked for security.',{exact:true}).waitFor();
  assert(!await page.locator('#sql-settings-dialog').isVisible());
  assert.match(cli('reset'),/PIN reset/);
  assert(!serverLog.includes(fixturePin)&&!serverLog.includes(replacementPin));
  assert.deepEqual(errors,[]);
  console.log('Verified direct MariaDB and mocked HTTPS COOP settings, registration, July/August billing, summary preservation, and light/dark UI.');
 }finally{
  if(browser)await browser.close();
  if(server&&server.exitCode===null){const exited=once(server,'exit');server.kill();await exited;}
  try{db('cleanup');}
  finally{
   const resolved=fs.realpathSync(fixture);
   if(path.dirname(resolved)===fs.realpathSync(os.tmpdir())&&path.basename(resolved).startsWith('genstat-coop-phase1-'))
    fs.rmSync(resolved,{recursive:true,force:true});
  }
 }
})().catch(error=>{console.error(error);process.exitCode=1;});
