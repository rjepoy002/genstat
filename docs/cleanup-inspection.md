# GENSTAT cleanup inspection - approval required

Inspection date: 2026-11-17 (Asia/Manila).

**No cleanup has been implemented.** This report is the only application-workspace artifact created during this inspection. No source code, configuration, database object/record, workbook, draft, key, backup or saved SQL version was removed or changed. No commit or push was performed.

## Scope and method

Inspected the first-party PHP entrypoints and library/CLI dependency graph, JavaScript imports and event handlers, CSS/Tailwind sources, SQL settings/history/recovery, annual worksheet/export/synchronization paths, configuration and storage filenames, test fixtures, documentation references, package dependencies and database object metadata. Named library functions were checked against all first-party PHP/JS/CJS references, including tests and tools; selectors were checked against runtime PHP/JavaScript. Third-party dependency contents and Git internals were excluded from removal decisions.

Database inspection used read-only transactions and rollback. Saved SQL was parsed for table dependencies without executing its business calculations. There are no first-party CI workflow definitions in this checkout; outside callers, administrator CLI habits, external import/scheduler jobs and retention requirements cannot be inferred from repository references alone.

## Important dependency findings

There are two supported report paths, not two interchangeable implementations:

- Default `index.php` -> `base-report.php` -> `assets/base-report.js`, local Tailwind, `assets/base-report.css`, and the single `partials/sql-settings.php` / `assets/sql-settings.js` / `query-settings-api.php` module. This focused report uses `excel-files/reference/GENSTAT_BASE.xlsx`, the Jul26 layout and its own draft namespace.
- `index.php?sheet=...` and `index.php?legacy=1` -> annual renderer, `assets/app.js`, `assets/app.css`, formula-reference/highlight/edit-history modules, `api.php`, and `lib/app.php`. This path still supplies annual worksheet navigation, Excel export, workbook synchronization/resync, autosave and undo/redo. It uses `excel-files/active/genstat_2026.xlsx` and separate annual drafts.

The annual cache lists Jun26, RecovBill26, Jan26, Feb26, Mar26, Apr26, May26, Jul26, Aug26 and Sep26. The original `config/layout.json` only lists Jun26; that baseline does not prove the other worksheets or annual machinery are unused. Current code limits reporting periods to January-September 2026; arbitrary additional years are not established as supported.

Deleting the annual renderer/API/assets now would remove required capabilities. The focused base report does not currently provide their monthly navigation, XLSX export or full sync/undo feature set. Any future retirement requires migrating and proving those features first.

## Classification and proposed actions

| Component | Classification | Evidence and proposed action |
| --- | --- | --- |
| Default base report, compact table and formulas | **PRESERVE** | Active default entrypoint and focused SQL report. Preserve source-derived layout, formula engine and non-formula input discovery. |
| Annual renderer in `index.php`, including explicit sheet/legacy routes | **PRESERVE** | Sheet tabs link to `?sheet=...`; required annual features remain here. Route naming is not evidence of obsolescence. |
| `api.php` and `lib/app.php` | **PRESERVE** | `assets/app.js` calls open/save/export/auth/unlock/lock/template-status/sync/synchronize/resync-preview/resync. Export and synchronization still depend on this endpoint. |
| Annual `assets/app.js`, `assets/app.css`, reference/highlight/history modules | **PRESERVE** | Explicitly imported by the annual renderer and actively used for editing, formula references and undo/redo. |
| SQL History UI and history/recovery services | **PRESERVE** | History opens versions and supplies Restore Configuration and confirmed Disable configuration. It is not unused. Apply receipts support retry/recovery. |
| `qs_save()`, `qs_archive_version()`, `qs_fingerprint()` and internal identifiers/enabled state | **PRESERVE** | These are active backend services/state even though their former visible controls were removed. Apply & Save depends on them. |
| Separate Save Configuration button, Query Identifier field, technical authorization checkboxes | **PRESERVE current absence** | Already removed from current HTML/JS. No second implementation remains to delete. The explicit HTTP `save` rejection is a compatibility/safety guard, not a working old save route. |
| Duplicate SQL settings dialogs | **PRESERVE single module** | Exactly one `sql-settings-dialog` definition. Annual formula-unlock and resync dialogs are distinct operations, not duplicates. |
| `ui_icon()` entries `shield`, `info`, `sheet`, `copy` in `index.php:15` | **SAFE TO REMOVE** | All current icon calls use literal names; no call site uses these four dictionary entries. Keep the function and all used entries. |
| Retired setup/month-switcher selectors in `assets/app.css` | **SAFE TO REMOVE** | `#formula-setup-command`, `.setup-content`, `.setup-note`, `.setup-content > #formula-copy-setup`, and `.report-switcher` selector family have zero references in runtime PHP/JS. Remove their exact obsolete rules only. Preserve shared dialog/button/accessibility styles and the hidden `#period` field. |
| `require_formula_authorization()` and `unlock_formulas()` in `lib/formula-auth.php:21,25` | **SAFE TO REMOVE** within inspected project | Neither function has a caller anywhere in first-party code/tests/tools. The retained CLI dispatches only setup/status; active web authorization uses `formula-confirmation.php`. Removing these two functions does not imply permission to delete the file or other auth helpers. External consumers remain outside inspection scope. |
| Write-only `$_SESSION['queryTests'][$token]` assignment in `query-settings-api.php:34` | **SAFE TO REMOVE** | Search found no reader after standalone save was retired. Preserve the response `testToken` until its UI status use is separately refactored. Do not remove actual `queryResult`, proposal fingerprints, confirmations or receipts. |
| SQL `testToken` generation/client status plumbing | **REFACTOR** | The client still reads token presence to describe validation. Replace that presentation dependency with an explicit server validation result, while preserving exact SQL/result and review validation. It is not an unused client variable as currently written. |
| Full configuration and mapping revision copied into the session proposal | **REFACTOR** | Review stores `config` and `mappingRevision`; current Apply uses the independently normalized body, fingerprint and saved-record hash instead. Verify/remove redundant snapshot fields separately without weakening these checks. |
| SQL modal rules in `assets/base-report.css` and settings-scoped rules in `assets/tailwind.input.css` | **REFACTOR** | Overlapping old/new definitions exist, but the old block still supplies opener styling, backdrop, disabled-fieldset opacity, hidden/empty handling and defaults. Consolidate property-by-property; do not delete the whole block. |
| Generic input/change listeners in SQL settings | **PRESERVE / targeted REFACTOR only** | Different DOM events are not duplicate listeners merely because they call `changed()`. Text, select and checkbox behavior must remain covered if deduplicated. Both confirmation listeners are required. |
| Repeated API writer-load/draft validation patterns | **REFACTOR** | Could be factored into helpers after regression coverage; preserve transaction ownership, lock lifetime, CSRF, response recovery and rollback ordering. Not a first cleanup deletion batch. |
| `lib/formula-auth.php`, `tools/formula-admin.php`, `tests/auth-setup.php`, old password-oriented `tests/ui.cjs` | **REVIEW REQUIRED** for whole-file retirement | Not the active web authorization path, but still linked by CLI, tests and README. `formula-auth.php` and `formula-confirmation.php` define overlapping names/classes and must not be loaded together. Retire the obsolete flow as a reviewed group; do not merge files blindly. |
| Stale `formula-auth.php` copy manifests in `tests/refresh.php` | **REFACTOR** | The live `lib/app.php` now requires `formula-confirmation.php`; several old fixture copy lists still name the password module instead. Repair the fixture dependencies rather than deleting refresh coverage. This was found statically, not by running the mutating fixture. |
| `tests/query-settings-fixture.php`, `tests/brookes-point-review-fixture.php` | **REVIEW REQUIRED** for retirement | No current browser caller remains; their old live-store/protected-F18 workflows were superseded by isolated Apply & Save tests. Historical docs/comments still refer to them. Confirm no manual use, then retire as a group. Do not confuse them with the active isolated `query-apply-fixture.php`. |
| Compatibility browser entrypoints delegating to `query-apply-save-browser.cjs` | **REFACTOR / PRESERVE names initially** | Intentionally executable aliases, not duplicate application code. A future test runner should run the common suite once; external automation may still use old filenames. |
| Inspection/setup/migration scripts | **REVIEW REQUIRED** before removal | Manual CLI use cannot be disproved by lack of imports. Setup/migrations remain useful for new installations. `tools/build-layout.php` is imported by production libraries and is **PRESERVE**, despite its directory name. |
| Old `tests/*-results.txt`, screenshots, exported fixtures and historical docs | **REVIEW REQUIRED** | Generated evidence is not runtime code, but audit/retention value is unknown. Inventory/archive before deletion; correct stale instructions separately. Preserve workbook reference/backups/drafts. |
| `node-sql-parser`, Tailwind packages, lockfile | **PRESERVE** | AST validation loads node-sql-parser; local CSS build imports Tailwind and scans PHP/JS. No package dependency was proven unused. |

## Files proposed for deletion

**No complete application source file currently qualifies for unconditional deletion.** The verified-safe batch consists of entries, selectors, two uncalled functions and one unused session assignment inside otherwise referenced files.

Whole-file retirement candidates, subject to separate owner approval and dependency updates:

1. `lib/formula-auth.php` and `tools/formula-admin.php`, together with the obsolete auth tests/instructions that depend on them.
2. `tests/query-settings-fixture.php` and `tests/brookes-point-review-fixture.php`, once any manual callers are confirmed retired.
3. Selected historical result/screenshot/export artifacts only after an explicit retention/archive decision.

Do not delete `index.php`, `api.php`, annual assets, history UI/services, any workbook, `config/layout.json`, `config/inputs.php`, migration files, source database configuration or the SQL replacement key.

## Database review - no changes authorized

The inspected schema contains 12 base tables. No views, triggers, stored routines or database events were found. At inspection time:

| Object | Observed state | Decision |
| --- | --- | --- |
| Seven approved reporting tables | Both saved SQL definitions reference all seven | **PRESERVE**: overallsalessummary, billadjustment, billhistorycancelled, billhistorycancelled_2, area_mapping, area_master, kwh_diff_allocation. |
| `genstat_query_definitions` | 2 rows | **PRESERVE**, active SQL definitions. |
| `genstat_row_query_mappings` | 2 rows | **PRESERVE**, row configuration and mapping state. |
| `genstat_query_history` | 2 rows | **PRESERVE**, active archived versions. |
| `genstat_query_apply_operations` | 2 rows | **PRESERVE**, committed result/recovery/audit receipts. Row age alone is not proof of disposability. |
| `sales_sync_status` | No first-party runtime/config/saved-query reference found | **REVIEW REQUIRED**. An upstream import job or external application may own it. Confirm external dependencies and ownership; no DROP/TRUNCATE or other modification is recommended yet. |

Saved `sales_kwh` and `adj_kwh` SQL parsed to the seven approved tables. No approved SQL, allocation rule, database grant, credential or schema was changed. Obsolete password-auth JSON files, if found later, would be filesystem state, not unused database tables; confirm retention before touching them.

## Documentation and navigation

The README contains historical default-off row permissions, password administration, June-only/no-MySQL claims and older routes. Current behavior is described by `docs/sql-settings-apply-save.md`; update the README to distinguish current focused reporting from the still-supported annual path. Preserve historical evidence where needed rather than treating it as current instructions.

No visible Workbook Editor navigation link was found on the focused report. Annual worksheet links are required monthly navigation and must not be removed under that label.

## Verification in this inspection

- Syntax-only checks: **66 PHP and 32 JavaScript files passed; zero failures**.
- Repository-wide reference scans, named-function caller scan, import/route/action tracing, runtime selector searches, package/build inspection and single-modal checks completed.
- Read-only database metadata/counts and saved-query AST dependency checks completed; transactions rolled back.
- No browser interaction, report recalculation/export, sync, resync, Apply & Save, migration or stateful regression suite was run in this approval-gated inspection.

Live annual page loading is deliberately avoided here: `index.php?sheet=...` calls `layout(true)` -> `storage_refresh()` and can promote/stage an incoming workbook or initialize storage. Some tests also write files or use disposable database DDL. `tests/base-report.php` writes its validation JSON into docs. These are not assumed read-only merely because they are named inspect/test/open.

## Proposed approval batches

**Batch A - verified-safe first-party removals only:**

- Four unused icon dictionary entries.
- Exact retired password-setup/report-switcher CSS rule families listed above.
- The two uncalled legacy password-unlock functions; keep the remaining CLI/status module and all active confirmation security.
- The write-only `queryTests` session assignment; keep the current UI response token and all actual validation/review caches.

No source files, routes, CLI tools, tests, dependencies, database objects, history, receipts, workbooks or saved state would be deleted in Batch A.

**Batch B - separately approve scoped refactoring:** validation-status plumbing, redundant proposal fields, SQL modal style consolidation, stale fixture/README repair, and test-runner alias deduplication. Preserve behavior and test before expanding scope.

**Batch C - owner review only:** annual editor retirement, whole legacy-auth/tool/fixture deletion, `sales_sync_status`, generated artifact retention and backup/state removal. No implementation is proposed until dependencies outside this repository and feature parity are resolved.

After approved edits, run syntax and static selector/reference checks, read-only report/formula/SQL suites and the isolated Apply & Save suite. Use disposable workbook/draft fixtures to verify monthly navigation, export value/formula/format preservation, synchronization, undo and resync. Verify the exact seven-table allowlist, row-18 F18 authorization, both confirmations, source workbook checksums and saved configuration/draft hashes. Do not test sync/export against live user workbooks just to validate cosmetic cleanup.

**Awaiting approval. No removals or refactoring have been applied.**
