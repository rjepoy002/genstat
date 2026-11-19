<section id="settings-appearance" class="p-4 sm:p-5" hidden aria-labelledby="appearance-title">
 <form id="report-appearance-form" class="sql-card space-y-4">
  <h3 id="appearance-title" class="m-0 text-lg font-semibold">Table Appearance</h3>
  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
   <label class="settings-label">Show Row &amp; Column Headings<select class="settings-input" name="showHeadings"><option value="on">On</option><option value="off">Off</option></select></label>
   <label class="settings-label">Theme<select class="settings-input" name="theme"><option value="system">System Default</option><option value="light">Light Mode</option><option value="dark">Dark Mode</option></select></label>
   <label class="settings-label">Row height<select class="settings-input" name="rowHeight"><option value="28">Compact (28px)</option><option value="36">Comfortable (36px)</option><option value="44">Spacious (44px)</option></select></label>
   <label class="settings-label">Font size<select class="settings-input" name="fontSize"><option value="11">11px</option><option value="12">12px</option><option value="13">13px</option><option value="14">14px</option></select></label>
   <label class="settings-label">Column spacing<select class="settings-input" name="columnSpacing"><option value="compact">Compact</option><option value="comfortable">Comfortable</option></select></label>
   <label class="settings-label">Selection border thickness<select class="settings-input" name="selectionBorderWidth"><option value="2">2px</option><option value="3">3px</option></select></label>
   <label class="settings-label">Selection border color<input class="settings-input" name="selectionBorderColor" type="color"></label>
  </div>
  <div id="appearance-preview" class="appearance-preview overflow-auto rounded-lg" aria-label="Appearance preview">
   <table class="w-full">
    <thead><tr data-excel-heading><th></th><th scope="col">A</th><th scope="col">B</th><th scope="col">C</th></tr><tr><th data-excel-heading scope="row">8</th><th scope="col">Particulars</th><th scope="col">Main</th><th scope="col">Aborlan</th></tr></thead>
    <tbody><tr data-row-selected><th data-excel-heading scope="row">18</th><td class="particulars" style="background-color:#00B0F0">Total KWH Sold</td><td data-sql-applied="true"><span class="value">12,345.00</span></td><td>1,234.00</td></tr></tbody>
   </table>
  </div>
  <div class="flex flex-wrap gap-2">
   <button type="submit" class="sql-primary">Apply Changes</button>
   <button id="appearance-reset" type="button">Reset to Defaults</button>
   <button id="appearance-cancel" type="button">Cancel</button>
  </div>
  <p id="appearance-status" class="sql-help" role="status"></p>
 </form>
</section>
