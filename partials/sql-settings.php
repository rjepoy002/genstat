<dialog id="sql-settings-dialog" class="sql-modal rounded-2xl border-0 bg-slate-50 p-0 text-slate-800 dark:text-slate-50" aria-labelledby="sql-settings-title">
 <div class="sticky top-0 z-10 bg-white dark:bg-slate-800">
 <div class="sql-dialog-header flex items-center justify-between gap-4 border-b border-slate-200 bg-white px-5 py-4 dark:border-slate-600 dark:bg-slate-800">
  <div><p class="m-0 mb-1 text-xs font-semibold uppercase tracking-widest text-teal-700">GENSTAT</p><h2 id="sql-settings-title" class="m-0 text-xl font-semibold tracking-tight text-slate-900 dark:text-slate-50">Settings</h2><p id="sql-context" class="m-0 mt-1 text-xs text-slate-500"></p></div>
  <button id="sql-settings-close" type="button" class="close-settings" aria-label="Close Settings" title="Close">&#10005;</button>
 </div>
 <nav class="flex flex-wrap gap-2 border-b border-slate-200 px-4 py-2 dark:border-slate-600" aria-label="Settings sections">
  <button id="settings-reporting-tab" type="button" aria-controls="settings-reporting" aria-pressed="true">Reporting Configuration</button>
  <button id="settings-appearance-tab" type="button" aria-controls="settings-appearance" aria-pressed="false">Appearance</button>
  <button id="coop-open" type="button">COOP Consumption</button>
 </nav>
 </div>
 <div id="settings-reporting" class="space-y-4 p-4 sm:p-5">
  <h3 class="m-0 text-lg font-semibold">SQL Query Settings</h3>
  <p id="sql-message" class="m-0 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs" role="status" aria-live="polite">Loading settings...</p>
  <div id="sql-recovery-panel" class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-xs" hidden><p class="m-0 mb-2">A previous Apply &amp; Save result can be recovered without creating another configuration version. Recovered report values still require Save Draft.</p><button id="sql-recover" type="button">Recover last Apply &amp; Save</button></div>
  <fieldset id="sql-fields" class="m-0 min-w-0 space-y-4 border-0 p-0">
   <section class="sql-card space-y-3" aria-label="Report configuration">
    <div class="flex flex-wrap items-center justify-between gap-2"><h3 class="sql-card-title">Report configuration</h3><span id="sql-row-status" class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">Not Configured</span></div>
    <div class="flex flex-wrap items-end gap-3"><label class="settings-label min-w-0 flex-1">GENSTAT Report Row<select id="sql-row" class="settings-input" aria-label="GENSTAT report row"></select></label><button id="sql-history" type="button" aria-controls="sql-history-panel" aria-expanded="false">History</button></div>
    <p id="sql-editor-state" class="sql-help" role="status">No unapplied changes.</p><p id="sql-approved-note" class="sql-help" hidden>Approved row: Apply &amp; Save can replace Excel formulas in this approved row.</p>
    <div id="sql-history-panel" class="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3" hidden><label class="settings-label">Configuration versions<select id="sql-history-select" class="settings-input"></select></label><div class="flex flex-wrap gap-2"><button id="sql-history-load" type="button">Restore Configuration</button><button id="sql-disable-config" type="button">Disable configuration</button></div><p class="sql-help">Restoring loads unapplied settings only. Test and Apply &amp; Save to use them. Disabling keeps the SQL and history.</p></div>
   </section>
   <section class="sql-card space-y-3" aria-labelledby="sql-editor-title">
    <div class="flex flex-wrap items-center justify-between gap-2"><h3 id="sql-editor-title" class="sql-card-title">SQL Query Editor</h3><span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-medium text-teal-800">Read-only execution</span></div>
    <label class="settings-label">Approved MySQL query<textarea id="sql-editor" class="settings-input sql-editor" rows="8" spellcheck="false" placeholder="WITH params AS (SELECT :ReportMonth AS ReportMonth)&#10;SELECT ..."></textarea></label>
    <p class="sql-help">Use :ReportMonth for the selected reporting month. Comments, side effects and unapproved functions or tables are rejected.</p>
    <p id="sql-month-warning" class="sql-help" role="status" hidden>SQL contains a value that looks like a fixed reporting month. Verify the query follows the selected month. SQL text is preserved as entered.</p>
  <div class="sql-actions flex flex-wrap items-center gap-2 rounded-xl border border-slate-200 bg-white p-3"><button id="sql-test" type="button" class="sql-primary">Test Query</button><span id="sql-duration" class="ml-auto text-xs text-slate-500"></span><p class="sql-help w-full">Testing does not save configuration. Configuration is saved automatically when tested values are successfully applied.</p></div>
   </section>
   <section class="sql-card space-y-3" aria-labelledby="sql-columns-title">
    <h3 id="sql-columns-title" class="sql-card-title">SQL Result Column Settings</h3>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
     <label class="settings-label">Area matching column<input id="sql-area-column" class="settings-input" value="AreaID"></label>
     <label class="settings-label">Value Column<input id="sql-value-column" class="settings-input" list="sql-result-columns" placeholder="Select or enter a returned column" aria-describedby="sql-fallback-help"><datalist id="sql-result-columns"><option value="Sales_KWH"><option value="KWH_Diff"><option value="TotalKWhSold"></datalist><span id="sql-fallback-help" class="sql-help mt-1 block">Required SQL result column. Its values are used when the custom formula is disabled.</span></label>
     <label class="settings-label">SACode column (optional)<input id="sql-sacode-column" class="settings-input" placeholder="SACode, if needed"></label>
    </div>
   </section>
   <section id="sql-calculation-section" class="sql-card space-y-3" aria-labelledby="sql-formula-title">
    <div class="flex flex-wrap items-center justify-between gap-2"><div><h3 id="sql-formula-title" class="sql-card-title">GENSTAT Report Formula</h3><p id="sql-formula-row" class="sql-help mt-1"></p></div><label class="flex items-center gap-2 text-xs font-medium"><input id="sql-formula-enabled" type="checkbox">Enable Custom Formula</label></div>
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3"><label class="settings-label">Formula preset<select id="sql-formula-preset" class="settings-input"><option value="">Custom Formula</option><option value="TotalKWhSold">Use SQL Total</option><option value="Sales_KWH + KWH_Diff">Sales + Adjustments</option><option value="Sales_KWH + KWH_Diff + Total_Cancelled_KWH">Sales + Adjustments + Cancelled</option></select></label><label class="settings-label lg:col-span-2">Calculation Formula<input id="sql-calculation" class="settings-input font-mono" spellcheck="false" placeholder="Sales_KWH + KWH_Diff"></label></div>
    <div><p class="sql-help mb-2">Available numeric SQL columns - click to insert at the cursor.</p><div id="sql-column-tokens" class="flex flex-wrap gap-2"></div></div>
    <details class="text-xs text-slate-500"><summary class="cursor-pointer">Formula syntax and precision</summary><p class="mb-0 leading-relaxed">Supports + - * /, parentheses and decimal constants. Use [column name] for unusual names; escape ] as ]]. NULL operands are rejected. Division uses 24 decimal places; results round to 12 decimal places and display with the report's cell format.</p></details>
    <div class="flex flex-wrap items-center gap-3 rounded-lg border border-slate-100 bg-slate-50 p-3"><button id="sql-preview-formula" type="button">Preview Formula</button><p id="sql-formula-message" class="m-0 text-xs" role="status" aria-live="polite">Test a query to preview calculated values.</p></div>
   </section>
   <details class="sql-card" open><summary class="sql-card-title cursor-pointer">Verified Area &rarr; Excel Input Mappings</summary>
    <p class="sql-help mt-2 mb-3">Match verified AreaIDs to workbook inputs. Manual choices take priority. Apply &amp; Save replaces values only in eligible targets for the selected row.</p>
    <div class="grid grid-cols-1 gap-3 md:grid-cols-2"><label class="settings-label">Auto-match Excel areas using SQL column<select id="sql-auto-column" class="settings-input"><option value="">Test query to choose a column</option></select></label><label class="settings-label">Area matching method<select id="sql-match-method" class="settings-input"><option value="normalized">Normalized names and approved aliases</option><option value="exact">Exact names and approved aliases</option><option value="manual">Manual only</option></select></label></div>
    <p id="sql-mapping-message" class="my-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600" role="status">Test a query to see matching results.</p>
    <div id="sql-bindings" class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3"></div>
    <label class="settings-label mt-4">Explicitly excluded result areas<input id="sql-excluded" class="settings-input" placeholder="Comma-separated AreaID, or SACode|AreaID"><span class="sql-help mt-1 block">Only areas intentionally outside this report row.</span></label>
   </details>
  </fieldset>


  <section class="sql-card space-y-3" aria-label="Query preview"><div class="flex flex-wrap items-center justify-between gap-2"><h3 class="sql-card-title">SQL Result Preview</h3><span class="text-xs text-slate-500">Read-only results and computed values</span></div><ul id="sql-diagnostics" class="sql-diagnostics m-0 pl-5 text-xs" aria-live="polite"></ul><div id="sql-preview" class="settings-preview"><p class="sql-empty">Test your approved query to see its results. No workbook values will change.</p></div>
   <div class="sql-apply-actions flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3"><button id="sql-apply" type="button" class="sql-primary" aria-describedby="sql-apply-reason" disabled>Apply &amp; Save</button><p id="sql-apply-reason" class="m-0 text-xs text-slate-600" role="status" aria-live="polite">Test the SQL query first.</p></div>
  </section>
 </div>
 <?php require __DIR__.'/report-appearance.php';?>
</dialog>

<dialog id="sql-apply-loading" class="report-loading-card rounded-2xl text-center shadow-2xl" aria-labelledby="sql-loading-title">
 <div class="flex flex-col items-center" role="status" aria-live="polite">
  <div class="report-loading-spinner animate-spin" aria-hidden="true"></div>
  <h2 id="sql-loading-title" class="text-lg font-semibold">Applying SQL settings</h2>
  <p id="sql-loading-period"></p>
 </div>
</dialog>
