<dialog id="coop-dialog" class="fixed inset-0 m-auto max-h-[90dvh] w-[min(1100px,94vw)] overflow-auto rounded-xl border border-slate-200 bg-white p-4 text-slate-900 shadow-xl backdrop:bg-black/60 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100 sm:p-6" aria-labelledby="coop-title">
 <header class="flex items-start justify-between gap-3">
  <div><h2 id="coop-title" class="m-0 text-xl font-semibold">COOP Consumption</h2><p id="coop-period" class="my-2 text-sm"></p><span id="coop-development" class="rounded bg-slate-100 px-2 py-1 text-xs text-slate-600 dark:bg-slate-700 dark:text-slate-200" title="Temporary localhost access without login." <?php if(!coop_development_active()):?>hidden<?php endif;?>>Development Mode</span></div>
  <button id="coop-close" type="button" autofocus class="rounded-md focus-visible:ring-2" aria-label="Close COOP Consumption" title="Close">&#10005;</button>
 </header>
 <div id="coop-status" class="my-3 rounded-md bg-slate-100 p-2 text-sm dark:bg-slate-700" role="status"></div>
 <div id="coop-working" role="status" class="fixed inset-0 z-50 items-center justify-center bg-black/60 text-white not-hidden:flex" hidden>
  <span class="rounded-md bg-slate-800 p-4">Working on the COOP request...</span>
 </div>
 <details id="coop-admin" class="my-3 rounded-md border border-slate-200 p-3 dark:border-slate-600" hidden>
  <summary class="cursor-pointer font-semibold">Database Connection</summary>
  <p id="coop-source" class="my-2 text-sm"></p>
  <p id="coop-connection-status" class="m-0 text-sm font-semibold"></p>
  <p id="coop-last-test-success" class="my-1 text-xs"></p>
  <p id="coop-last-test-attempt" class="my-1 text-xs"></p>
  <p class="my-1 text-xs">Status reflects the last applicable test.</p>
  <p id="coop-connection-help" class="my-2 text-sm"></p>
  <form id="coop-connection" method="post"><fieldset class="grid gap-3 sm:grid-cols-2" disabled>
   <label>Database Host <input name="host" required></label>
   <label>Port <input name="port" type="number" min="1" max="65535" required></label>
   <label>Database <input name="database" required></label>
   <label>Username <input name="username" autocomplete="off" required></label>
   <div><label for="coop-password">Password</label>
    <div class="flex items-center gap-2">
     <input id="coop-password" class="min-w-0 flex-1" name="password" type="password" autocomplete="new-password">
     <button id="coop-password-toggle" type="button" class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md focus-visible:ring-2" aria-label="Show password" aria-pressed="false" title="Show password">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/><path id="coop-password-slash" d="m3 3 18 18" hidden/></svg>
     </button>
    </div>
   </div>
   <label>TLS CA path <input name="ca" aria-describedby="coop-ca-help"><span id="coop-ca-help" class="block text-xs">Required for remote connections. Leave blank for approved loopback development.</span></label>
   <div class="flex flex-wrap gap-2 sm:col-span-2">
    <button id="coop-test" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md focus-visible:ring-2" aria-label="Test Connection" title="Test Connection"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 3h12M9 3v7l-5 8a2 2 0 0 0 2 3h12a2 2 0 0 0 2-3l-5-8V3M8 15h8"/></svg></button>
    <button id="coop-connection-save" type="submit" class="inline-flex h-8 w-8 items-center justify-center rounded-md focus-visible:ring-2" aria-label="Save Connection" title="Save Connection" disabled><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h12l4 4v12a2 2 0 0 1-2 2Z"/><path d="M7 3v6h10V3M7 21v-7h10v7"/></svg></button>
    <button id="coop-connection-remove" type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md focus-visible:ring-2" aria-label="Remove Connection" title="Remove Connection" disabled><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 6h18M8 6V4h8v2M5 6l1 15h12l1-15M10 10v7m4-7v7"/></svg></button>
   </div>
  </fieldset></form>
 </details>
 <section id="coop-report" class="space-y-4" hidden>
  <details id="coop-enrollment-section" class="rounded-md border border-slate-200 p-3 dark:border-slate-600">
  <summary class="cursor-pointer font-semibold">Account Enrollment</summary>
  <p class="my-2 text-sm" role="status">Account Enrollment: <span id="coop-enrollment-status"></span></p>
  <form id="coop-enrollment" class="mt-2 flex flex-wrap items-end gap-3">
   <label>Summary Area <select name="area" required></select></label>
   <label>AcctCode <input name="acctCode" required maxlength="128" autocomplete="off"></label>
   <label>Effective From <input name="effective" required inputmode="numeric" pattern="[0-9]{6}" maxlength="6"></label>
   <button id="coop-enroll" type="submit" disabled>Enroll Account</button>
  </form>
  <section>
   <h3 class="my-2 text-base font-semibold">Enrolled Accounts</h3>
   <label>Search enrollments <input id="coop-search" type="search"></label>
   <div id="coop-accounts" class="mt-2 overflow-auto"></div>
  </section>
  </details>
  <section>
   <h3 class="my-2 text-base font-semibold">Consumption Preview</h3>
   <p class="my-2 text-sm" role="status">Billing Consumption: <span id="coop-billing-status"></span></p>
   <dl class="my-2 grid gap-2 text-sm sm:grid-cols-2">
    <div><dt class="font-semibold">Reporting Period</dt><dd id="coop-preview-period"></dd></div>
    <div><dt class="font-semibold">Database Connection Status</dt><dd id="coop-preview-connection"></dd></div>
    <div><dt class="font-semibold">Last Refresh</dt><dd id="coop-last-refresh"></dd></div>
    <div><dt class="font-semibold">Preview Status</dt><dd id="coop-preview-status" role="status">Pending verification</dd></div>
   </dl>
   <p id="coop-preview-policy" class="my-2 text-sm" hidden>Single-record candidate &mdash; billing correction rules pending verification.</p>
   <div class="flex flex-wrap gap-2"><button id="coop-refresh" type="button" disabled>Refresh Consumption</button><button id="coop-apply" type="button" disabled>Apply to Report</button></div>
   <div id="coop-preview" class="mt-2 overflow-auto"></div>
  </section>
  <section>
   <h3 class="my-2 text-base font-semibold">Area Totals</h3>
   <div id="coop-areas" class="overflow-auto"></div>
   <p id="coop-total"></p>
  </section>
  <section>
   <h3 class="my-2 text-base font-semibold">Refresh and Apply History</h3>
   <p id="coop-last-success"></p>
   <div id="coop-history" class="overflow-auto"></div>
  </section>
 </section>
</dialog>
