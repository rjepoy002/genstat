<dialog id="recovered-sales" class="fixed inset-0 m-auto max-h-[92dvh] w-[min(980px,94vw)] overflow-hidden rounded-xl border border-slate-200 bg-white p-0 text-slate-900 shadow-xl backdrop:bg-black/60 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100" aria-labelledby="recovered-sales-title">
 <section class="flex max-h-[90dvh] flex-col">
  <div class="flex shrink-0 items-start justify-between gap-4 border-b border-slate-200 px-5 py-4 dark:border-slate-600">
   <div>
    <h2 id="recovered-sales-title" class="m-0 text-lg font-semibold" tabindex="-1">Recovered Sales</h2>
    <p class="my-1 text-sm" id="recovered-sales-period"></p>
   </div>
   <button type="button" class="report-icon inline-flex h-9 w-9 items-center justify-center rounded-md p-0! hover:bg-slate-100! dark:hover:bg-slate-700! text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-blue-600 disabled:opacity-50" aria-label="Close Recovered Sales" data-close-recovered>
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>
   </button>
  </div>
  <div class="min-h-0 overflow-auto px-5 py-4">
   <p id="recovered-sales-status" role="status" tabindex="-1" class="my-1 text-sm"></p>
   <fieldset id="recovered-sales-fields" class="m-0 min-w-0 border-0 p-0" hidden>
    <legend class="sr-only">Recovered sales breakdown</legend>
    <dl class="my-2 grid grid-cols-1 gap-2 sm:grid-cols-3">
     <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-600"><dt>Total KWH Used</dt><dd class="m-0 text-lg font-semibold tabular-nums" data-recov-total>0.00</dd></div>
     <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-600"><dt>Total KWH Paid</dt><dd class="m-0 text-lg font-semibold tabular-nums" data-recov-total>0.00</dd></div>
     <div class="rounded-lg border border-slate-200 p-3 dark:border-slate-600"><dt>Total Remaining KWH</dt><dd class="m-0 text-lg font-semibold tabular-nums" data-recov-total>0.00</dd></div>
    </dl>
    <p class="my-2 text-sm">GENSTAT Summary uses its existing workbook formulas.</p>
    <div class="mt-4 overflow-x-auto" tabindex="0" role="region" aria-label="Recovered sales entries">
     <table class="w-full! min-w-[740px] table-fixed! border-collapse! bg-transparent! text-left text-sm!">
      <colgroup><col class="w-[23%]"><col class="w-1/5"><col class="w-1/5"><col class="w-1/5"><col class="w-[17%]"></colgroup>
      <thead><tr class="border-b border-slate-200 dark:border-slate-600">
       <th scope="col" class="p-2! align-top! bg-transparent! dark:border-slate-600!">Area</th><th scope="col" class="text-right p-2! align-top! bg-transparent! dark:border-slate-600!">KWH Used</th><th scope="col" class="text-right p-2! align-top! bg-transparent! dark:border-slate-600!">KWH Paid</th>
       <th scope="col" class="text-right p-2! align-top! bg-transparent! dark:border-slate-600!">Remaining KWH</th><th scope="col" class="p-2! align-top! bg-transparent! dark:border-slate-600!">Remarks</th>
      </tr></thead>
      <tbody id="recovered-sales-rows"></tbody>
     </table>
    </div>
    <p id="recovered-sales-empty" class="my-3">No recovered sales entries for this month.</p>
    <button type="button" id="recovered-sales-add" class="mt-3 h-9 rounded-md border border-slate-300 px-4! hover:bg-slate-100! dark:border-slate-600 dark:hover:bg-slate-700! text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-blue-600 disabled:opacity-50">Add Row</button>
   </fieldset>
  </div>
  <div class="flex shrink-0 justify-end gap-2 border-t border-slate-200 px-5 py-3 dark:border-slate-600">
   <button type="button" data-close-recovered class="h-9 rounded-md border border-slate-300 px-4! hover:bg-slate-100! dark:border-slate-600 dark:hover:bg-slate-700! text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-blue-600 disabled:opacity-50">Cancel</button>
   <button type="button" id="recovered-sales-save" class="h-9 rounded-md border-transparent! bg-blue-700! px-4! text-white! enabled:hover:bg-blue-800! text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-blue-600 disabled:opacity-50" disabled>Save Changes</button>
  </div>
 </section>
</dialog>
<template id="recovered-sales-row">
 <tr class="border-b border-slate-200 align-top dark:border-slate-600">
  <td class="overflow-visible! p-2! align-top! bg-transparent! hover:z-10 focus-within:z-10 dark:border-slate-600!">
   <div class="flex items-center gap-2">
    <select data-field="area" class="h-9 min-w-0 flex-1 rounded-md border border-slate-300 bg-transparent px-2 dark:border-slate-600 dark:bg-slate-800"></select>
    <button type="button" data-delete-recovered class="report-icon relative inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md p-0! text-red-700! disabled:opacity-50 transition-colors hover:bg-red-50! focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-red-500 after:top-auto! after:bottom-[calc(100%+4px)] dark:text-red-300! dark:hover:bg-slate-700!" aria-label="Delete Entry" data-tooltip="Delete Entry">
     <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m-6 5v6m4-6v6"/></svg>
    </button>
   </div>
  </td>
  <td class="p-2! align-top! bg-transparent! dark:border-slate-600!"><input type="text" inputmode="decimal" data-field="used" class="h-8 w-full rounded-md border border-slate-300 bg-transparent px-2 text-right text-slate-900 tabular-nums dark:border-slate-600 dark:text-slate-100"><span data-error="used" class="block text-xs text-red-700 dark:text-red-300"></span></td>
  <td class="p-2! align-top! bg-transparent! dark:border-slate-600!"><input type="text" inputmode="decimal" data-field="paid" class="h-8 w-full rounded-md border border-slate-300 bg-transparent px-2 text-right text-slate-900 tabular-nums dark:border-slate-600 dark:text-slate-100"><span data-error="paid" class="block text-xs text-red-700 dark:text-red-300"></span></td>
  <td class="p-2! align-top! bg-transparent! dark:border-slate-600!"><output class="flex h-8 items-center justify-end px-2 tabular-nums" data-remaining></output></td>
  <td class="p-2! align-top! bg-transparent! dark:border-slate-600!"><input type="text" maxlength="500" data-field="remarks" class="h-8 w-full rounded-md border border-slate-300 bg-transparent px-2 dark:border-slate-600"></td>
 </tr>
</template>
