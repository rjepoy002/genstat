<dialog id="settings-pin-dialog" class="settings-security-dialog rounded-2xl border-0 bg-white p-6 text-slate-800 dark:bg-gs-surface dark:text-slate-50" aria-labelledby="settings-pin-title">
 <button type="button" class="settings-pin-close absolute right-3 top-3" data-pin-close aria-label="Close PIN dialog">×</button>
 <svg class="block mx-auto mb-3 text-teal-700 dark:text-teal-400" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/></svg>
 <h2 id="settings-pin-title" class="m-0 text-center text-xl font-semibold">Settings Locked</h2>
 <p class="text-center text-sm">Enter your 6-digit security PIN to access settings.</p>
 <form id="settings-pin-form" autocomplete="off">
  <div id="settings-pin-inputs" class="grid grid-cols-6 gap-2" role="group" aria-label="Security PIN">
   <?php for($digit=1;$digit<=6;$digit++): ?>
   <input type="password" inputmode="numeric" maxlength="1" aria-label="PIN digit <?= $digit ?>" autocomplete="off"<?= $digit===1?' autofocus':'' ?>>
   <?php endfor; ?>
  </div>
  <p id="settings-pin-message" class="min-h-5 text-sm text-red-700 dark:text-red-300" role="status" aria-live="polite"></p>
  <div class="flex justify-end gap-2">
   <button type="button" data-pin-close>Cancel</button>
   <button id="settings-pin-unlock" class="settings-pin-primary" type="submit" disabled>Unlock Settings</button>
  </div>
 </form>
</dialog>
<dialog id="settings-pin-change-dialog" class="settings-security-dialog rounded-2xl border-0 bg-white p-6 text-slate-800 dark:bg-gs-surface dark:text-slate-50" aria-labelledby="settings-pin-change-title">
 <h2 id="settings-pin-change-title" class="m-0 mb-4 text-xl font-semibold">Change PIN</h2>
 <form id="settings-pin-change-form" class="space-y-3" autocomplete="off">
  <label class="block">Current PIN<input name="pin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="off" required autofocus></label>
  <label class="block">New PIN<input name="newPin" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password" required></label>
  <label class="block">Confirm New PIN<input name="confirmation" type="password" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="new-password" required></label>
  <p id="settings-pin-change-message" class="text-sm text-red-700 dark:text-red-300" role="status" aria-live="polite"></p>
  <div class="flex justify-end gap-2">
   <button id="settings-pin-change-cancel" type="button">Cancel</button>
   <button class="settings-pin-primary" type="submit">Save Changes</button>
  </div>
 </form>
</dialog>
