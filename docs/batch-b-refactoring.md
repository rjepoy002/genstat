# Batch B refactoring

Implemented only the approved Batch B scope. Existing working-tree changes were preserved; no commit, checkpoint or push was created. Annual editing, legacy CLI authorization tools, configuration history and recovery receipts remain available. Batch C was not implemented.

## Application changes

- `assets/base-report.css`: removed the duplicated SQL modal rule block; retained the compact report grid and toolbar opener styling.
- `assets/tailwind.input.css`: consolidated the effective scoped SQL modal rules, including backdrop, input defaults, hidden/empty states, disabled opacity, table layout and numeric alignment. Existing utility-based markup remains unchanged.
- `assets/tailwind.css`: regenerated with `npm.cmd run build:css`.
- `query-settings-api.php`: Test responses now include boolean `validationPassed`, calculated from the existing mapping/calculation validation errors. The existing `validation` object, targets and compatibility `testToken` response remain. Review proposals omit only redundant `config` and `mappingRevision` snapshots.
- `assets/sql-settings.js`: validation messages now use explicit `validationPassed === true`; removed the client token variable/reset assignments. Apply continues to require the separate server-validated review proposal and confirmations.

No SQL text, calculation expression, parser restriction, seven-table allowlist, execution limit, credential, production grant, migration or formula was changed.

## Proposal field tracing

| Fields | Purpose retained |
| --- | --- |
| token, expires | Session proposal identity, operation ID and expiry |
| source, revision, overlayHash | Source workbook, saved draft and current working values/formulas/signatures consistency |
| fingerprint, recordHash | Normalized configuration and exact current saved record, including its revision |
| areaHash | Area/mapping metadata consistency |
| changes, issues, configurationChanged | Exact reviewed targets, blocking validation issues and legitimate configuration-only changes |

The redundant configuration snapshot was never consumed: Apply normalizes the submitted editor configuration and compares its fingerprint. The redundant mapping revision was never consumed: the complete saved-record hash already detects revision/content changes, and persistence uses the current revision inside its transaction. The request/response mappingRevision fields elsewhere remain intact.

Old proposals containing these extra fields continue to work because Apply ignores them. An isolated regression injects obsolete extra snapshots and exercises failure rollback followed by retry/recovery. Receipt lookup, owner/session verification, source/draft/config/record/area hashes, signatures and transactional persistence are unchanged.

## Test and runner changes

- `tests/refresh.php`: consolidated fixture runtime installation, supplied current workbook-storage/monthly/confirmation dependencies, used disposable active-workbook storage and repaired stale policy/error-message assertions while still requiring save/export to reject source formulas.
- `tests/auth-setup.php`: explicitly identifies legacy CLI/status compatibility coverage. It still tests the legacy module independently; incompatible authorization implementations are never loaded together.
- `tests/ui.cjs`: retained as a compatibility entrypoint to the isolated current annual browser suite, replacing retired password-setup assumptions.
- `tests/cell-editing-browser.cjs`: explicitly opens the annual route, seeds an isolated draft, copies only non-secret input/layout configuration, and covers responsive controls, labels, zoom, active per-cell confirmation, monthly drafts, export and synchronization. Original security assertions remain.
- `tests/undo-resync-browser.cjs`: explicit annual route and credential-free disposable configuration; retains undo/redo, concurrent revision, preview/confirm/cancel, stale-token, resync backup and monthly draft assertions.
- `tests/check-annual-export.php`: read-only export verification resolving Jun26 through workbook relationships, checking authorized formula/text changes and preservation of unrelated formulas, layout, sheets, styles, external links and ZIP integrity.
- `tests/query-apply-fixture.php` and `tests/query-apply-save-browser.cjs`: verify reduced proposal fields, retained integrity fields, old extra-field compatibility and explicit Test response semantics.
- `tests/sql-settings-ui.cjs`: successful Test response deliberately supplies a null compatibility token while retaining the validation boolean; current validation feedback must still succeed.
- `tests/sql-settings-style.cjs`: desktop/tablet/mobile computed-style regression, including backdrop, focus, disabled/loading, hidden/empty and numeric/table behavior.
- `tools/run-tests.cjs`, `tests/test-runner.cjs`, `package.json`: one supported test manifest and alias deduplication. Four SQL lifecycle aliases resolve to query-apply-save-browser; ui resolves to cell-editing-browser. All existing alias filenames remain. Potentially writing formula/layout/validation-artifact tests run in disposable roots; stateful SQL/workbook suites manage their own isolated fixtures. Logs are written to a unique system-temp directory, not production storage. No new dependency.

## Documentation

README now documents the compact and annual report sources, worksheet routes, separate draft state, Test/Review/Apply & Save, automatic configuration persistence, history/recovery, approved formula replacements, annual synchronization/resync/undo/export, reporting security, local assets and supported testing. The prior README is preserved verbatim in a clearly marked historical section. Other historical inspection reports remain records of their original scope; this report records the subsequent approved work.

## Verification

- Local Tailwind build passed.
- Exact pre/post computed-style comparison passed at 1440, 820 and 390 pixels, including all inspected properties and modal states.
- Standard unit group: **17/17 passed** (SQL parser/security, allowlist/mappings/calculation, writable rows/formula protection, compact view/source formulas, legacy CLI compatibility, workbook refresh/reconciliation/monthly handling, formula engine, number formatting, export/layout verification, alias deduplication).
- Read-only browser group: **4/4 passed** (style contract, SQL calculation UI, exact reporting allowlist/July query, Brooke?s Point mapping).
- SQL Settings UI and isolated Apply & Save lifecycle passed, covering explicit status independent of token, proposal fields, required confirmations, stale/unauthorized inputs, rollback, duplicate requests, lost response, cross-session recovery rejection, save persistence, history/restore/disable and signed formula replacement/restore.
- Annual editing browser passed: responsive controls, formula confirmation/cancel/Escape/Enter, unauthorized second-cell rejection, pending undo, draft persistence/reload, text autosave, full XLSX export preservation, formula synchronization, explicit formula-to-value synchronization, saved formula protection and independent monthly drafts. No JavaScript errors.
- Undo/resync browser passed: keyboard and toolbar undo/redo, saved formula confirmations/cancel, concurrency rejection, version-bound Resync confirmation, cancel preservation, stale draft/source rejection, selected-draft clearing with backups, other-month preservation and replay rejection.
- **25/25 distinct standard suites passed**, executed as the 17-suite unit group and eight canonical browser suites. The annual compatibility entrypoint exercised its canonical suite successfully after fixture repairs.
- **67 PHP and 35 JavaScript files passed syntax checks**, zero failures. Runner syntax and alias regression were rechecked after final formatting. Git diff whitespace check passed.
- Read-only reporting access verification passed for all seven tables. Both existing saved SQL definitions (sales_kwh, adj_kwh) validated and returned 15 rows for July 202607 with all ten columns: BillMonth, SACode, AreaID, AreaName, Sales_KWH, KWH_Diff, Cancelled_KWH, Cancelled_KWH_2, Total_Cancelled_KWH, TotalKWhSold.

Baseline comparisons cover all 171 files under `excel-files`, `storage` and `config`, plus content hashes of the four production settings/history/receipt tables and reader/writer grant hashes. The final comparison matches exactly; no additions/removals were detected. Reader/writer grants and all four table hashes match the pre-refactor baseline. Stateful SQL tests create/remove only a unique disposable schema/account; billing tables are read through the existing reader. No live workbook/draft/configuration test writes are authorized.

## Retained compatibility and future work

The response testToken remains because external consumers cannot be ruled out. Legacy authorization modules/tools and historical manual test entrypoints remain. Annual editor retirement, complete legacy-file deletion, backup retention and any other Batch C decisions are deferred. No new approval is needed for the completed Batch B changes; future cleanup needs its own scope and dependency review.

## Final scope and diff summary

Twenty Batch B files were changed or added: seven application/build/documentation entry files (assets/base-report.css, assets/tailwind.input.css, assets/tailwind.css, assets/sql-settings.js, query-settings-api.php, README.md, package.json), eleven test files listed above, tools/run-tests.cjs and this report. Package dependencies/lockfile are unchanged. Generated minified CSS is rebuilt output. No complete legacy source file was deleted.

Against the captured Batch B baseline, production logic changes are limited to explicit Test status/client feedback, two omitted proposal snapshots and consolidated equivalent CSS. Test fixture routing/dependencies/assertions and execution orchestration account for the remaining code changes. Git status already contained unrelated tracked changes and substantial untracked application/data files; repository-wide diff totals include that earlier work and are not attributed to Batch B.

The initial refresh failure came from an obsolete error-message expectation; rejection is still mandatory. Export checking initially assumed sheet1 was June; it now resolves Jun26 by its relationship. One annual browser attempt timed out during formula interaction under concurrent browser load; the final suite completed with a 60-second action timeout and all security/preservation assertions intact. No application workaround was introduced. No unresolved functional regression was found. Retained legacy/external compatibility items above are deliberate deferrals.
