# Cell editing fix

## Root cause

Editability was tied to the previous template's seventeen June input references in `tools/build-layout.php` and `lib/app.php`. `lib/sync-mapping.php` reset other cells and worksheets to read-only, and `assets/app.js` disabled non-primary worksheet entry. Implicit empty cells were rendered without editors. The formula toolbar also used a global password-based grant rather than confirmation for one cell. Excel's default `locked` styles were not the direct cause.

## Behavior after the fix

All non-formula cells in the displayed used range now accept text, numbers and supported formulas, including implicit blanks, labels and previous manual values. The browser and server use the same current-workbook cell policy. Excel locked styles do not determine editability. Covered merge cells, array/data-table formula ranges and explicit application `readOnly` flags remain restricted; explicit rules are preserved when coordinates move.

Formula detection reads actual OOXML formula nodes and shared/array ranges, independent of cached displayed results. A double-click, editing key, formula-bar editing attempt or toolbar action displays:

- **Formula Protected**
- “This cell contains a formula. Editing it may affect other calculations. Do you wish to unlock and edit this cell?”
- **Cancel** / **Unlock & Edit**

Confirmation grants editing only for that worksheet, workbook version and cell. The original expression appears in the formula editor. Enter or leaving the editor stages the change and restores protection; Escape cancels the buffer. A dashed outline marks pending changes, and the Undo button or Ctrl+Z cancels them before saving. Save Draft validates with the existing bounded formula engine. An explicit replacement by text, number or blank is supported and is never inferred from a formula's calculated result. Confirmations are checked by the server and consumed after saving. There is no administrator/password requirement in the application editing flow.

New formulas saved in previously empty/manual cells also become protected draft formulas before Excel synchronization. Non-formula entry autosaves after a pause; pending edits to protected formulas wait for Save Draft or Sync Now. Sync retains version checks, external-edit conflict detection, verified backups and atomic replacement.

Each worksheet uses its own draft and selected-sheet calculation/export context. The existing June draft filename remains unchanged. Monthly drafts record worksheet identity; coordinate maps preserve both explicit and implicit blank entries across compatible row/column insertions and multiple template versions. Unsafe mapping still reports a conflict without discarding saved data. Legacy input references remain historical annotations rather than the authority for editing or a reason to reject an otherwise safe refresh.

The production workbook and existing draft were not rewritten during implementation. Layout, worksheet geometry, formats, hidden rows/columns, headings, dependency highlights, toolbar, branding, storage paths and zoom remain in place.

## Modified files

Application:

- `index.php`, `api.php`
- `assets/app.js`, `assets/app.css`
- `lib/app.php`, `lib/workbook-policy.php`, `lib/template-sync.php`, `lib/sync-mapping.php`
- `lib/formula-engine.php`, `lib/formula-edits.php`
- `tools/build-layout.php`

Created `lib/formula-confirmation.php` for cell-specific confirmation. The old standalone authentication utilities are no longer loaded or required by the browser application.

Tests:

- Created `tests/cell-editing-browser.cjs` and `tests/editing-policy.php` with result logs.
- Updated `tests/formulas.php`, `tests/verify.php`, `tests/workbook-storage.php`, and `tests/sync-reconciliation.php` to reflect the requested editing and confirmation policy while retaining their integrity checks.
- Regenerated relevant test result files. Updated README and the prior storage report to point to this change.

## Verification

Passed browser tests in an isolated temporary application copy:

1. Empty cells, manual values and labels are editable.
2. Workbook replacement refreshes geometry and protects a newly introduced formula despite its numeric cached result.
3. Opening/replacing the workbook preserves the exact existing draft bytes.
4. Formula attempts show the exact requested modal; Cancel leaves the formula protected and unchanged.
5. Unlock & Edit exposes the original expression for one cell. An unconfirmed second-cell save is rejected by the server.
6. Escape cancels editing; Enter marks pending changes and relocks; Undo cancels before save.
7. Formula changes persist in drafts and after reload, with protection restored. Excel remains unchanged until Sync Now.
8. Text/blank-cell autosave preserves saved formula changes.
9. Sync preserves unrelated formula expressions, worksheets, formatting and external-link parts.
10. Explicit formula-to-value replacement reloads as an editable non-formula cell.
11. Newly created draft formulas become protected before Excel synchronization.
12. Monthly drafts edit and reload independently; no browser JavaScript errors occur.
13. Production workbook and draft remain byte-for-byte intact.

Passed policy tests for inherited blank-cell formatting, merged ownership, explicit read-only rules, underlying formula detection, implicit array protection, monthly row/column migration across versions and preservation of saved draft bytes/revision.

Passed the existing automatic-refresh, structural-reconciliation, workbook-storage, formula/export, number-format and workbook integrity suites. Export verification checks all 398 June formula nodes. Browser Ctrl+wheel/toolbar zoom tests also pass.

Detailed output is in `tests/cell-editing-browser-results.txt`, `tests/editing-policy-results.txt`, and the existing test result logs.
