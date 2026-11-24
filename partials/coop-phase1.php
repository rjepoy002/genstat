<section id="settings-coop" class="space-y-4 p-4 sm:p-5" hidden aria-labelledby="coop-title">
 <h3 id="coop-title" class="m-0 text-lg font-semibold">COOP Consumption</h3>
 <p id="coop-status" class="m-0 text-sm" role="status" aria-live="polite"></p>
 <fieldset id="coop-fields" class="m-0 min-w-0 space-y-4 border-0 p-0" disabled>
  <section class="sql-card space-y-3" aria-labelledby="coop-connection-title">
   <h4 id="coop-connection-title" class="sql-card-title">Database Connection</h4>
   <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3" id="coop-connection">
    <label class="settings-label">Connection type<select name="type" class="settings-input"><option value="local">Local XAMPP</option><option value="lan">Remote XAMPP (LAN)</option><option value="hosted">Remote Hosted MySQL</option><option value="https">Remote HTTPS API</option></select></label>
    <label class="settings-label" data-coop-direct>Database host or IP<input class="settings-input" name="host" value="127.0.0.1" spellcheck="false"></label>
    <label class="settings-label" data-coop-direct>Port<input class="settings-input" name="port" value="3306" type="number" min="1" max="65535"></label>
    <label class="settings-label" data-coop-direct>Database name<input class="settings-input" name="database" spellcheck="false"></label>
    <label class="settings-label" data-coop-direct>Username<input class="settings-input" name="username" value="root" autocomplete="off" spellcheck="false"></label>
    <label class="settings-label" data-coop-direct>Password<input class="settings-input" name="password" type="password" autocomplete="new-password" aria-describedby="coop-password-state"><span id="coop-password-state" class="sql-help"></span></label>
    <label class="settings-label flex items-center gap-2" data-coop-direct><input name="emptyPassword" type="checkbox"> Use no password</label>
    <label class="settings-label" data-coop-https hidden>API URL<input class="settings-input" name="apiUrl" type="url" value="https://paleco.net/genstat-api/coop.php" maxlength="2048" spellcheck="false"></label>
    <div class="settings-label" data-coop-https hidden>
     <label for="coop-api-key">API Key</label>
     <div class="flex items-center gap-2">
      <input class="settings-input" id="coop-api-key" name="apiKey" type="password" autocomplete="new-password" maxlength="1024" spellcheck="false">
      <button id="coop-api-key-toggle" type="button" aria-label="Show API key" aria-pressed="false">
       <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6-10-6-10-6Z"/><circle cx="12" cy="12" r="2.5"/></svg>
      </button>
     </div>
    </div>
   </div>
   <p class="sql-help" data-coop-direct>Local XAMPP can use existing credentials on loopback. Remote XAMPP requires a dedicated SELECT-only account.</p>
   <p id="coop-hosted-help" class="sql-help" hidden>Ensure remote database access is enabled on your hosting server.</p>
   <div class="flex flex-wrap gap-2">
    <button id="coop-test-connection" type="button">Test Connection</button>
    <button id="coop-save-connection" class="sql-primary" type="button" disabled>Save Connection</button>
   </div>
  </section>
  <p class="sql-help" data-coop-https hidden>Consumption data is retrieved through the configured HTTPS API.</p>
  <section class="sql-card space-y-3" data-coop-direct aria-labelledby="coop-query-title">
   <h4 id="coop-query-title" class="sql-card-title">SQL Configuration</h4>
   <label class="settings-label">Monthly consumption query<textarea id="coop-query" class="settings-input sql-editor" rows="5" maxlength="40000" spellcheck="false" aria-describedby="coop-query-help"></textarea></label>
   <p id="coop-query-help" class="sql-help">Use :ReportMonth in the query and alias the consumption column as KWH. For account previews, also return AreaID, AreaName and AccountID, with optional AccountName. Return one row per account using the approved billing rules. Test Query uses the saved connection.</p>
   <div class="flex flex-wrap gap-2">
    <button id="coop-test-query" type="button">Test Query</button>
    <button id="coop-save-query" class="sql-primary" type="button" disabled>Save Query</button>
   </div>
  </section>
 </fieldset>
  <section class="sql-card space-y-3" aria-labelledby="coop-accounts-title" id="coop-accounts">
   <h4 id="coop-accounts-title" class="sql-card-title">Account Management</h4>
   <div class="flex flex-wrap items-end gap-2">
    <label class="settings-label">GENSTAT area<select id="coop-add-area" class="settings-input"></select></label>
    <label class="settings-label">COOP account code<input id="coop-add-code" class="settings-input" maxlength="128" spellcheck="false"></label>
    <button id="coop-add-account" type="button" class="sql-primary" disabled>Add Account</button>
   </div>
   <p id="coop-account-status" class="m-0 text-sm" role="status"></p>
   <div class="flex items-center justify-between gap-2">
    <h5 class="m-0 font-semibold">All Added Accounts</h5>
    <button id="coop-filter-open" type="button" class="inline-flex items-center gap-1.5 px-2! py-1!" aria-haspopup="dialog" aria-controls="coop-filter-dialog" disabled>
     <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 4h18l-7 8v6l-4 2v-8z"/></svg>
     Filter<span id="coop-filter-count" class="rounded-full bg-slate-100 px-1.5 text-xs text-slate-700 dark:bg-gs-surface dark:text-gs-text" hidden></span>
    </button>
   </div>
   <div id="coop-account-list" class="settings-preview" role="region" aria-label="All added accounts" tabindex="0"></div>
   <div class="flex flex-wrap items-center gap-2">
    <button id="coop-account-prev" type="button" disabled>Previous</button>
    <span id="coop-account-page" aria-live="polite"></span>
    <button id="coop-account-next" type="button" disabled>Next</button>
   </div>
  </section>
 <dialog id="coop-filter-dialog" class="fixed inset-0 m-auto w-[min(380px,92vw)] rounded-xl border border-slate-200 bg-white p-4 text-slate-900 shadow-xl backdrop:bg-black/60 dark:border-gs-border dark:bg-gs-surface dark:text-gs-text" aria-labelledby="coop-filter-title">
  <form id="coop-filter-form" class="space-y-3">
   <div class="flex items-center justify-between gap-2">
    <h5 id="coop-filter-title" class="m-0 text-sm font-semibold">Filter accounts</h5>
    <button id="coop-filter-close" type="button" class="inline-flex items-center justify-center px-2! py-1!" aria-label="Close filters">
     <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
    </button>
   </div>
   <label class="settings-label">Search Accounts<input id="coop-account-search" class="settings-input" type="search" maxlength="200" placeholder="Account code, number, or name" autofocus></label>
   <label class="settings-label">GENSTAT Area<select id="coop-account-filter" class="settings-input"><option value="">All Areas</option></select></label>
   <div class="flex justify-end gap-2">
    <button id="coop-filter-clear" type="button">Clear</button>
    <button type="submit" class="sql-primary">Apply Filter</button>
   </div>
  </form>
 </dialog>
</section>
