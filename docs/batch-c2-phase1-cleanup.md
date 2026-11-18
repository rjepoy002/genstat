# Batch C2 Phase 1 — legacy SQL fixture retirement

Date: 2026-11-17 (Asia/Manila).

## A. Summary

Retired only tests/query-settings-fixture.php and tests/brookes-point-review-fixture.php. Current SQL integration testing uses the isolated Apply & Save fixture/suite. No application behavior, SQL calculations, security restriction, workbook, production configuration or historical artifact was intentionally changed. No Phase 2 work, Git checkpoint, commit or push was performed.

Deletion proceeded after repository dependency checks, replacement coverage comparison and the operator's explicit confirmation: **“Confirmed: neither fixture is used externally or manually.”** This confirmation resolves the external/manual-use condition that local reference scans alone could not establish.

## B. Dependency Verification

Repository-wide scans covered PHP, JavaScript/CJS, package/test-runner configuration, hidden workflow/script paths, PowerShell/batch/setup/maintenance source and documentation. No active executable caller was found. No locally visible scheduled task action matched either fixture name. No first-party GitHub workflow invoking either file was found. External/manual non-use is supported by the operator confirmation, not inferred from missing repository references.

| Reference found before removal | Classification / handling |
| --- | --- |
| tests/brookes-point-review-fixture.php comment naming query-settings-fixture.php check | Obsolete sequencing comment inside the retired fixture; no executable caller. Removed with its file. |
| docs/sql-query-settings.md, original created-files list | Historical implementation reference in developer documentation. Preserved as a creation record and annotated with current isolated replacement command. |
| docs/brookes-point-mapping.md, created-files list | Historical implementation record; unchanged. |
| docs/cleanup-inspection.md, classification/proposed retirement lists | Historical inspection evidence; unchanged. |
| docs/batch-c-inspection.md, fixture inventory/C2 recommendation | Historical inspection evidence; unchanged. |
| README.md and this report, added retirement notice | Current explanatory references, not commands to run deleted files. |

Neither filename was in the standard test manifest or executable compatibility wrappers. No runner change was required. Remaining mentions are intentional historical/retirement documentation, not dangling executable imports or direct execution instructions.

## C. Replacement Coverage

The deleted scripts were setup/cleanup helpers, not independent assertion suites. query-settings-fixture.php checked an empty configured namespace and removed reserved definitions/mappings/history. brookes-point-review-fixture.php seeded one row-18 configuration using qs_save. Neither supplied isolated database/session ownership. The active suite replaces these mechanics with a uniquely named disposable schema/account, isolated sessions/storage, production-state hashing and validated fixture cleanup.

| Relevant behavior | Retained replacement evidence |
| --- | --- |
| Query configuration and row mapping persistence | tests/query-apply-fixture.php seeds a disabled legacy configuration in its disposable database; tests/query-apply-save-browser.cjs checks saved record identity, row independence and persistence. |
| SQL Test and Review | Browser suite tests result/mapping/formula preview, explicit validation status, proposal fields, expiry, changed configuration/values and confirmations; tests/query-calculation-settings.php supplies decimal/mapping edge coverage. |
| Apply & Save and configuration history | Browser suite asserts successful persistence, revision/history counts, no history for identical configurations and selected-row updates. |
| Restore and Disable Configuration | Browser suite restores a version only through fresh Test/Review/Apply, confirms disable, verifies retained values/history and explicit re-enable on Apply. |
| Brooke's Point mapping | tests/brookes-point.php verifies alias/AreaID matching, duplicate handling and alternate month/row/column behavior. Browser suite verifies F18 mapping to AreaID 12. |
| Approved F18 replacement and outside-row protection | tests/sql-formula-replacement.php and browser suite verify approved formula replacement, separate confirmation, signed persistence/template restore and rejection of unknown/unauthorized targets. |
| Rollback | Browser suite injects receipt insertion failure in the disposable database and asserts configuration/history/receipt rollback. |
| Duplicate requests and lost response | Browser suite aborts the response after commit, replays the same operation without duplicate signatures/history and recovers exact committed values. |
| Cross-session authorization | Browser suite rejects recovery from another session, tests persisted signed replacements in a fresh session and stale-draft replay rejection. |
| SQL security restrictions | tests/query-parser.cjs, tests/query-settings.php and tests/reporting-allowlist-browser.cjs cover SELECT/CTE/bound month, exact allowlist and unsafe statement/function/comment/hint/multiple-statement rejection. |
| Safe cleanup | query-apply-fixture validates temporary root/schema/account names and removes only its unique database/account; the browser suite removes its private storage/sessions/root and checks live source/draft hashes. |

No unique relevant assertion required migration. The old reserved-key DELETE cleanup and empty-production-namespace prerequisite are obsolete unsafe mechanics, not assertions to reproduce against production. Existing active tests were not edited, renamed, removed or weakened.

## D. Files Removed

Exactly two source files:

- tests/query-settings-fixture.php
- tests/brookes-point-review-fixture.php

No other source file was deleted. In particular tests/query-apply-fixture.php, tests/query-apply-save-browser.cjs, tests/brookes-point.php, tests/sql-formula-replacement.php, tests/query-calculation-settings.php, tests/query-parser.cjs and tests/reporting-allowlist-browser.cjs remain intact.

## E. Documentation Updates

- README.md: current testing section identifies the retired fixtures and supported command: `node tools/run-tests.cjs tests/query-apply-save-browser.cjs`.
- docs/sql-query-settings.md: annotated its historical created-files entry without rewriting the original creation claim; added the current replacement helper/suite and retirement-report link.
- docs/batch-c2-phase1-cleanup.md: this completion report.

Earlier inspection/implementation reports and all historical test artifacts remain unchanged. No active instruction was found recommending execution of the deleted fixtures after these updates.

## F. Regression Results

Final results: **25/25 canonical supported suites passed** across the full run and corrected lifecycle rerun. PHP syntax checks passed for **65 files**; JavaScript syntax checks passed for **35 files**, with zero failures. The unchanged runner manifest lists 25 canonical suites; its alias/deduplication test passed. Final executable-reference scans found no callers/imports of the retired files, and Git diff whitespace checks passed.

The initial full run was **24/25**: I omitted migrations from the verification copy, so lifecycle setup failed. A retry exposed the additional omitted partials directory. After copying both existing directories into the disposable verification copy and restoring the test copy from the unchanged source, the lifecycle suite passed **1/1**. These were verification-copy preparation errors; no application/test assertions or production code were changed to obtain a pass. Failed fixture attempts also removed their disposable schema/account.

Passed coverage includes parser/security, exact reporting allowlist, Brooke's Point, approved formula replacement and outside-row protection, SQL calculations/mappings, Apply & Save/history/restore/disable/rollback/duplicate/lost-response/cross-session recovery, compact layout/formulas, annual editing/export/sync/monthly drafts, undo and Resync. See section C for the test-to-behavior mapping.

The supported runner was executed from a disposable verification copy so named PNG/export outputs could not overwrite historical artifacts. It had private copies of workbook/storage/configuration files and a dependency junction used only for reads. The annual endpoint copies used a disposable session directory. Lifecycle tests created/removed only their uniquely named temporary settings database/account; reporting reads used the existing read-only reader. Some supported browser checks inspected the live compact page without saving/applying configuration or report values.

## G. Data Integrity

Pre-change baselines cover **368 existing project files** and content hashes for **all 12 production database tables**, including sales_sync_status. Final table hashes match exactly. Temporary lifecycle schemas/accounts were absent both before and after testing; no genstat_apply_test_* schema or genstat_apply_* user remains.

File comparison found exactly two source deletions, two documentation edits (README.md and docs/sql-query-settings.md), and this new report. All other inventoried files are byte-for-byte unchanged, including retained active tests, historical reports/test artifacts, workbooks, backups, configuration, drafts, locks and replacement key. This preserves unrelated working-tree changes.

Disposable lifecycle/annual/workbook roots cleaned themselves up. The outer verification copy, its private sessions/configuration/workbook copies/generated outputs and the four task-specific temporary result-log directories were then removed after validating their absolute paths under the system temporary directory. The dependency junction was detached before recursive cleanup; production node_modules was preserved. No new source/dependency installation or live database/workbook/configuration write was performed by these tests.

## H. Remaining Legacy Components

Intentionally preserved: legacy authorization library/CLI/test, all annual editor modules/routes/assets, compatibility browser entrypoints, historical test outputs/screenshots/exports, backup/reference/active/incoming/archive workbooks, drafts/locks/recovery state, all production tables and grants, SQL history/receipts and sales_sync_status. Any later retirement requires its own approved scope. No Batch C2 Phase 2 or Batch C3 work was implemented.
