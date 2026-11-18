# GENSTAT — Batch C inspection

Inspection date: 2026-11-17, Asia/Manila. **Inspection only. No cleanup implementation was performed.**

## A. Executive Summary

Preserve both report implementations. The compact Jul26 report does not replace annual worksheet navigation, XLSX export, synchronization, confirmation-based Resync or application Undo/Redo. SQL population/history/recovery belong to the compact report and do not automatically populate annual drafts.

The legacy password library is outside the current web authorization path, but its CLI and compatibility test are actual dependencies. Conditional group retirement requires confirmation that manual/external consumers no longer need it. No obsolete password-state files were found in project storage.

The two old SQL fixtures are superseded by the isolated Apply & Save suite. They target the configured database and must not be executed as harmless inspection commands. Several older browser suites have stale route/password assumptions, but others contain unique regression coverage requiring preservation or migration.

The database contains 12 base tables. sales_sync_status has 96 rows and resembles an upstream verification ledger. No local application/saved-query reference, foreign-key dependency or visible database routine using it was found. External import ownership remains unresolved: **do not retire this table on current evidence.**

No complete application source file qualifies for unconditional SAFE TO REMOVE status. The safest next implementation scope is conditional retirement of the two superseded SQL fixtures after checking manual callers. Legacy CLI retirement can follow separately if operators no longer require it. Annual retirement needs feature migration; backup/data pruning needs an explicit retention policy.

### Method and limits

Inspected first-party routes/includes, handlers, assets, CLI tools, test bodies/manifests, documentation references, storage filenames, workbook sheet metadata and read-only database metadata. SQL transactions were rolled back. Reader access probes used SELECT with LIMIT 0; no billing values were returned. Credentials, password hashes, authentication tokens, key contents and saved inputs are omitted.

No application route was opened and no tests, setup, migrations, exports, synchronization, Resync or Apply operations were run. Batch B test results are historical evidence, not newly executed results. A content-hash inventory of 367 existing project files, excluding dependencies/Git internals, was retained in memory for final comparison. Only this report is authorized to change.

Visible Windows scheduled tasks had zero matches for GENSTAT/sales-sync/XAMPP identifiers in task metadata/actions. A bounded text search of local XAMPP htdocs found only GENSTAT documentation references to sales_sync_status; dependencies/vendor directories, data exports and SQL dumps were excluded. This does not cover remote hosts, upstream SQL Agent jobs, services, interactive importers or tasks hidden from the current account. Reachability establishes supported functionality, not actual user traffic; usage telemetry was unavailable.

References: [original inspection](cleanup-inspection.md), [Batch A](batch-a-cleanup.md), [Batch B](batch-b-refactoring.md), [Apply & Save](sql-settings-apply-save.md). Earlier approval/status wording in historical reports describes those earlier snapshots.

## B. Legacy Feature Inventory

| Exact files/components | Classification | Current dependency evidence |
| --- | --- | --- |
| index.php annual renderer; api.php; lib/app.php | PRESERVE | Explicit sheet/legacy routes and worksheet links. API open/save/export/auth/unlock/lock/synchronize/template-status/sync/resync-preview/resync actions remain implemented. |
| assets/app.js; assets/app.css; assets/edit-history.js; assets/formula-references.js; assets/reference-highlights.js | PRESERVE | Annual HTML imports each; current editing/history/reference/highlight/sync handlers depend on them. |
| base-report.php; lib/base-report.php; lib/base-report-view.php; assets/base-report.js; assets/base-report.css | PRESERVE | Active default compact report with independent source/draft/session namespaces. |
| partials/sql-settings.php; assets/sql-settings.js; query-settings-api.php; lib/query-settings.php; lib/query-apply-save.php; lib/sql-replacements.php; lib/report-areas.php; lib/report-expression.php | PRESERVE | Active SQL Test, mappings, decimal calculations, review, transactional Apply & Save, signed replacements, configuration history/recovery. |
| lib/xlsx.php; lib/workbook-policy.php; lib/render.php; lib/formula-engine.php; lib/files.php; tools/build-layout.php | PRESERVE | Shared extraction/calculation/render/policy/file services. tools/build-layout.php is imported by production libraries. |
| lib/workbook-storage.php; lib/template-sync.php; lib/template-refresh.php; lib/sync-mapping.php; lib/workbook-resync.php; lib/monthly-worksheets.php; lib/formula-edits.php; lib/formula-confirmation.php | PRESERVE | Source selection, refresh/reconciliation, draft projection, formula edits, missing-month checks, synchronization and recovery. |
| lib/formula-auth.php; tools/formula-admin.php; tests/auth-setup.php | REVIEW REQUIRED | Legacy CLI compatibility group, analyzed in D. |
| assets/tailwind.input.css; assets/tailwind.css; package.json; package-lock.json | PRESERVE | Local stylesheet build, served generated CSS and SQL parser dependency. |
| assets/images/paleco-logo.png; assets/images/README.txt | PRESERVE logo; REVIEW REQUIRED guidance | Annual header checks/renders logo. README is installation guidance with historical wording. Compact header omission does not make the logo unused. |

No entire unused runtime JavaScript module or obsolete CSS file was established. Batch A removed retired rules; Batch B consolidated modal styling. Remaining annual CSS is referenced. node-sql-parser and Tailwind/CLI packages all have active purposes; no unused package was identified.

## C. Annual Editor Dependency Analysis

Verification below is static tracing of route/function/handler behavior, not a fresh browser execution.

| Feature | Compact Report | Annual Editor | Can Retire? |
| --- | --- | --- | --- |
| Monthly worksheet navigation | Fixed reference Jul26; worksheet label | Worksheet tabs, Jan–Sep26 and RecovBill26 | No; migrate navigation, period binding and independent drafts. |
| Formula calculation | FormulaEngine via base_report_calculate and SQL replacement working layout | Same engine with annual cross-sheet/external dependencies | No; shared engine already exists but contexts differ. |
| Cell editing | Report-range non-formula inputs, confirmed formula overrides and approved SQL replacement rows | Full used-range labels/blanks/values and supported formula editing | No; scopes differ. |
| Save Draft | Explicit source-hash JSON save and revision check | Autosave/Save Draft/open, period/sheet-specific JSON and versions | No; namespaces/formats differ. |
| Undo/Redo | No application history stack/buttons equivalent to annual; native input undo is not parity | GenstatEditHistory keyboard/toolbar history, confirmed formula undo | No; migration required. |
| Excel export | No XLSX export button/route | api.php export -> export_workbook | No; package/precision/formula preservation needed. |
| Workbook synchronization | No writeback into annual source | synchronize_draft, storage_refresh and auto_template_layout | No; preserve mapping/conflicts/locks/backups. |
| Resync confirmation | No equivalent operation | resync_preview/resync_confirm, version-bound token, backups and selected-draft reset | No; migrate safeguards. |
| Formula protection | baseUnlocked plus signed SQL replacement provenance | Per-cell confirmation, live source policy and array/read-only protection | No; preserve both contexts. |
| SQL-driven row population | Test/Review/Apply & Save, history and recovery | No SQL Settings import/automatic compact-to-annual transfer | Not a basis for annual retirement; integration needs planning. |

Future retirement requires an authoritative workbook/report contract, reversible period/sheet/hash/draft migration, complete OOXML export parity, application undo/autosave/open/confirmation/highlighting parity, and distinct sync/Resync workflows with verified backups. Preserve source-cell evidence, formula audit, coordinate maps, external links and unselected-month drafts. Compact row keys/signatures cannot simply be reused for annual rows. Inventory external API/CLI callers and stage route deprecation only after parity tests pass.

Current limits matter: lib/app.php period() accepts January–September 2026, while SQL month parsing supports worksheet month names more broadly. lib/monthly-worksheets.php explicitly reports that complete MySQL-to-worksheet generation is not implemented. Available billing records do not establish that missing annual sheets can be safely regenerated.

## D. Legacy Authorization Analysis

**Annual web:** lib/app.php imports lib/formula-confirmation.php. api.php unlock calls confirm_formula_cell(); lib/formula-edits.php calls require_formula_cell_confirmation(). Grants are scoped by workbook hash, worksheet and cell. Read-only/array/data-table restrictions remain active.

**Compact web:** base-report.php has its own per-cell baseUnlocked confirmation and CSRF/source checks. Approved SQL formula overwrite requires server review, both confirmations where applicable, signed provenance and restore authorization. It does not depend on legacy password setup or annual confirmation helpers.

**Legacy group:** lib/formula-auth.php retains formula_authority_state(), formula_authority(), legacy formula_auth_status() and FormulaPermissionException. tools/formula-admin.php imports it and supports setup/status. tests/auth-setup.php independently covers missing/invalid/ready/expiry compatibility. formula_authority() has no current first-party caller; the group is the meaningful retirement boundary. The old password-unlock functions were removed in Batch A; CLI setup writes configuration/backups but does not enable current web formula editing.

Both legacy and active annual modules define formula_auth_status and FormulaPermissionException. **Do not merge/load them together.** Removing legacy code must not remove current formula confirmation, source protection or SQL signature checks.

Filename-only recursive storage inventory found no formula-auth.json, formula-auth backups, formula-auth-attempts state or refresh-in-progress marker. No secret state contents were inspected. Other installations/session stores were not inventoried.

**Decision: REVIEW REQUIRED; conditional group retirement is feasible.** Obtain operator/external caller confirmation, then update/remove the legacy library, CLI, auth-setup test, runner entry and current documentation together. Retain historical README records marked historical. Preserve tests/cell-editing-browser.cjs, tests/undo-resync-browser.cjs and compact/SQL authorization coverage. Static imports indicate no web dependency on the legacy group; external dependencies remain unknown.

## E. Test Fixture and Artifact Analysis

### Active canonical suites and compatibility commands

tools/run-tests.cjs defines 17 unit and eight browser suites. package.json selects unit/browser/all. Aliases already deduplicate standard execution:

| Exact entrypoints | Canonical replacement / decision |
| --- | --- |
| tests/query-settings-browser.cjs; tests/query-calculation-lifecycle.cjs; tests/sql-formula-replacement-browser.cjs; tests/brookes-point-review-browser.cjs | tests/query-apply-save-browser.cjs. PRESERVE compatibility names until external commands are inventoried. |
| tests/ui.cjs | tests/cell-editing-browser.cjs. PRESERVE; already updated to current per-cell confirmation. |
| tests/query-apply-fixture.php | PRESERVE active helper called by query-apply-save-browser. Creates/removes a unique disposable schema/account and isolated sessions; not run here. |
| tests/query-settings-fixture.php | REVIEW REQUIRED retirement candidate: no current executable first-party caller; historical references remain. Cleanup deletes reserved settings/mappings/history in the configured database. Empty-namespace check is not a sandbox. |
| tests/brookes-point-review-fixture.php | REVIEW REQUIRED with preceding fixture: no current executable caller; qs_save writes reserved configuration into configured database. |

Replacement for the two old SQL fixtures: tests/query-apply-fixture.php + tests/query-apply-save-browser.cjs. Targeted retained coverage: tests/brookes-point.php, tests/sql-formula-replacement.php, tests/query-calculation-settings.php, tests/query-parser.cjs and tests/reporting-allowlist-browser.cjs. Approved F18 replacement is now intentional; an old assertion that it must always remain protected is not current policy.

### Nonstandard suites and coverage gaps

| Exact tests/helpers | Classification and replacement/gap |
| --- | --- |
| tests/formula-browser.cjs; tests/check-formula-export.php; tests/check-ui-export.php | REVIEW REQUIRED: obsolete password/default-route assumptions. Current replacement: tests/cell-editing-browser.cjs + tests/check-annual-export.php + tests/formulas.php. Compare specialized assertions before retiring; check-ui-export has no current executable caller. |
| tests/browser.cjs; tests/editable-focus.cjs; tests/check-focus-export.php | REVIEW REQUIRED: live default route now opens compact report. Current editing/export coverage: cell-editing-browser/check-annual-export plus tests/editing-policy.php, tests/number-format.php and tests/verify.php. Apache denial and exact untouched raw-value/focus assertions need explicit migration before retirement. |
| tests/auto-refresh-browser.cjs; tests/workbook-storage-browser.cjs | REFACTOR: stale annual default-route assumptions/live-state operations. tests/auto-refresh.php, tests/monthly-worksheets.php, tests/refresh.php and annual cell-editing/undo-resync cover core mechanisms. HTTP-denial/branding checks and storage-specific assertions are additional coverage. |
| tests/sync-workbook-browser.cjs | REFACTOR: isolated fixture but bare URL assumes old annual default. tests/sync-reconciliation.php plus cell-editing-browser/undo-resync replace core behaviors; detailed relocated-cell browser assertions need migration. |
| tests/headers-browser.cjs | PRESERVE / REFACTOR inclusion: isolated annual route, detailed sticky/header/worksheet geometry assertions. Copies more configuration than required. Not wholly replaced by general responsive checks. |
| tests/zoom-browser.cjs | REFACTOR: bare live URL assumes annual zoom UI. cell-editing-browser dropdown coverage does not replace all wheel/pointer-anchor assertions. Port unique checks into isolation. |
| tests/edit-history.cjs; tests/formula-references.cjs | PRESERVE: direct pure-module bounded-history/parser regressions, not in standard manifest; browser coverage is not complete replacement. |
| tests/workbook-storage.php | PRESERVE: isolated storage/regeneration safeguards; not in standard manifest. Consider inclusion after fixture review. |
| tests/base-report-browser.cjs | REFACTOR: compact test actually saves and conditionally removes live draft. Isolate before runner inclusion. tests/base-report.php, tests/base-report-view.php and query-apply-save-browser cover model/lifecycle, not every rendering assertion. |
| tests/base-report-compact.cjs | PRESERVE / REFACTOR inclusion: hidden rows, clipping, viewport fit and sticky behavior across sizes; not duplicate SQL modal testing. |

Nonstandard does not mean unused. README historical sections still name old scripts/results; no first-party GitHub workflow file was found, but external/manual commands are unknown. No test was deleted or executed.

### Generated artifacts

The exact inventory appended below is **REVIEW REQUIRED for retention**. Logs/screenshots/XLSX exports are not runtime imports but may be referenced historical evidence. Current artifacts can generally be regenerated by matching suites; historical pixels/business-state exports may not be byte-reproducible. Repair stale generators before claiming reproducibility. .gitignore exclusion is not deletion permission.

The standard runner writes fresh logs into unique system-temp directories, not the old named test-result files. Current browser suites still write named screenshots. docs/base-report-validation.json and docs/base-report-database-inputs.csv are also generated evidence: REVIEW REQUIRED pending retention/reproduction decisions.

## F. Database Object Analysis

Read-only metadata found 12 base tables in genstat_db; no local views, triggers, routines or events. Searches of globally visible definitions found no view/routine/trigger/event mentioning sales_sync_status. event_scheduler is OFF. Metadata visibility is bounded by account/server scope.

### sales_sync_status structure and purpose

| Column | Type | Constraint |
| --- | --- | --- |
| SourceCode | varchar(3) | NOT NULL; primary key position 1 |
| TableName | varchar(64) | NOT NULL; primary key position 2 |
| BillMonth | char(6) | NOT NULL; primary key position 3 |
| SACode | char(2) | NOT NULL |
| RowCount | bigint(20) | NOT NULL |
| Fingerprint | char(64) | NOT NULL |
| VerifiedAt | datetime(6) | NOT NULL |

InnoDB; no explicit column defaults; only the composite primary index observed. Exact count: **96 rows**. VerifiedAt spans **2026-10-26 14:30:42.464699** through **2026-10-28 09:00:47.808236** as stored; database timezone was not established. information_schema UPDATE_TIME is NULL and cannot establish inactivity. A single snapshot does not establish update cadence.

**Purpose inferred from structure:** source/table/month synchronization verification ledger, using row counts, fingerprints and verification timestamps. No fingerprint/business contents were needed for this inference.

No incoming/outgoing foreign key involving this table was found in visible metadata. No application/configuration or saved SQL definition references were found. No matching visible Windows task was found. These observations do not rule out external importers, remote jobs or interactive tools. Confirm the owner, importer source/configuration, all relevant scheduler hosts and at least one normal import/reporting cycle before recommending retirement. Do not change logging/server settings during inspection.

**Classification: REVIEW REQUIRED. No database cleanup recommended on current evidence.** No DROP/ALTER/TRUNCATE/DML was executed or proposed as an approved next step.

### Protected objects and verified permissions

- PRESERVE all seven reporting tables: overallsalessummary, billadjustment, billhistorycancelled, billhistorycancelled_2, area_mapping, area_master and kwh_diff_allocation. Configured allowlist matches exactly.
- Reader grants: USAGE plus table-specific SELECT on exactly those seven; all seven LIMIT 0 access probes succeeded. No reader DML/DDL or broad database SELECT observed. sales_sync_status remains outside the reader allowlist.
- PRESERVE genstat_query_definitions, genstat_row_query_mappings, genstat_query_history and genstat_query_apply_operations: exact counts are two each. Versions/receipts are active durability state.
- Writer grants: SELECT/INSERT/UPDATE on definitions/mappings/history; SELECT/INSERT on apply_operations. Grants were inspected with credential clauses omitted, not changed.
- Existing area_mapping/area_master, kwh_diff_allocation/area_master and row-mapping/query-definition foreign keys remain protected dependencies.

## G. Storage and Backup Analysis

| Exact path/group | Dependency and observed state | Classification |
| --- | --- | --- |
| excel-files/reference/GENSTAT_BASE.xlsx | Compact Jul26 source; hashes bind SQL rows/drafts/signatures/proposals | PRESERVE |
| excel-files/active/genstat_2026.xlsx | Annual source; 194,606 bytes; RecovBill26 and Jan–Sep26 | PRESERVE |
| genstat.xlsx | Original June-only workbook; workbook_path fallback, initialization and deterministic test baseline; differs from active workbook | PRESERVE |
| excel-files/incoming/GENSTAT_2026.xlsx | Pending 178,543-byte workbook; layout/storage_refresh can validate/defer/promote it | PRESERVE pending owner workflow |
| excel-files/archive/source-2026-2ff0ef351c11b8ed.xlsx | 198,369-byte archived input provenance | PRESERVE pending retention/recovery decision |
| excel-files/backups/ | 88 files, 16,649,353 bytes; workbook recovery snapshots | PRESERVE pending retention policy |
| storage/backups/ | 57 files in 15 directories, 174,241,352 bytes; auto/refresh/resync layouts, observed workbooks and drafts | PRESERVE pending retention policy |
| storage/base-report-752c1ad06bd770ea749ef7925a49adbc725ee81b3a7dd6342f5b1585253c3dce.json and .lock | Current compact source-hash draft/lock namespace | PRESERVE |
| storage/draft-2026-06.json and .lock | Primary annual draft | PRESERVE |
| storage/draft-2026-09--5f4c79203a18759a.json and .lock | Annual selected-sheet draft; hash suffix separates worksheets | PRESERVE |
| storage/template-auto.json | 9,326,950-byte annual layout/cache; normalized Jun26, RecovBill26, Jan26, Feb26, Mar26, Apr26, May26, Jul26, Aug26, Sep26 | PRESERVE; recovery/reconciliation dependency |
| config/layout.json; config/inputs.php | June-only original baseline/input evidence, hashed for reconciliation/security | PRESERVE |
| config/monthly-source.php; config/query-settings.local.php | Source/database configuration; secret contents omitted | PRESERVE |
| storage/sql-replacement.key | Signature and recovery-owner binding; deletion/rotation invalidates signed state | PRESERVE — secret |
| storage/workbook-sync.json | Synchronization checkpoint/timestamp used by Resync | PRESERVE |
| storage/monthly-review.json; storage/refresh-review.json | Missing-month/template review evidence; CLI can consume review plans | REVIEW REQUIRED |
| storage/workbook-comparison.json; storage/workbook-migration-report.json | Generated analysis evidence; no fixed-path runtime consumer found; manual/recovery value unresolved | REVIEW REQUIRED |
| storage/sync.lock; storage/template.lock; draft lock files | Active concurrency paths; zero length is normal, unlinking an in-use lock can break exclusion | PRESERVE |
| storage/refresh-in-progress.json | Absent; code blocks operations when an interrupted transaction marker exists | PRESERVE recovery contract; never blanket-delete markers |

Raw active workbook order starts with RecovBill26; normalized cache order starts with Jun26. Do not infer draft identity from raw sheet position. SQL configurations/history/receipts live in MySQL, separate from both JSON draft namespaces. No backup was declared disposable solely from age/size/duplicate appearance. Snapshots can contain sensitive configuration or signed state; do not publish their contents.

## H. Safe Removal Candidates

**No unconditional complete-file candidate was established.** Runtime non-use does not resolve operator/audit retention. The strongest conditional source candidates are the two superseded SQL fixtures; legacy authorization needs a coordinated group change. Generated artifacts are functionally unnecessary to serve the app but remain REVIEW REQUIRED until retention is settled.

| Additional exact files | Classification / reason |
| --- | --- |
| tools/setup-query-settings.php; tools/migrate-row-formula-replacement.php; tools/migrate-query-calculation-settings.php; tools/migrate-query-apply-operations.php; migrations/001-query-settings.sql; migrations/002-row-formula-replacement.sql; migrations/003-query-calculation-settings.sql; migrations/004-query-apply-operations.sql | PRESERVE installation/upgrade capability, not obsolete because unused per request. |
| tools/refresh-template.php; tools/compare-workbooks.php | PRESERVE manual refresh/comparison workflow; plan/report modes can write files. |
| tools/inspect.php; tools/inspect-base.php; tools/inspect-query-settings.php; tools/inspect-brookes-point.php; tools/inspect-monthly-data.php; tools/verify-reporting-access.php | REVIEW REQUIRED before retirement; manual diagnostics/setup verification. inspect-base writes a temp layout; inspect names do not guarantee absence of side effects. |
| tools/validate-report-sql.cjs | PRESERVE security-critical AST subprocess. |
| README.md; CELL-EDITING-FIX.md; WORKBOOK-STORAGE.md; UNDO-RESYNC.md; docs-sync-analysis.md | PRESERVE operational/development records; consolidate with historical notices/stable links if separately approved. |
| docs/cleanup-inspection.md; docs/batch-a-cleanup.md; docs/batch-b-refactoring.md; docs/genstat-full-system-inspection.md | PRESERVE inspection/implementation evidence. |
| docs/sql-query-settings.md; docs/sql-settings-apply-save.md; docs/row-sql-formula-replacement.md; docs/approved-sql-row-overwrite.md; docs/brookes-point-mapping.md; docs/query-calculation-settings.md; docs/sql-settings-ui-modernization.md | REFACTOR cross-links/current-vs-historical guidance; retain mapping/calculation/authorization decisions. |
| docs/base-report-implementation.md; docs/base-report-compact-ui.md; docs/cancelled-bill-history-inspection.md; docs/cancelled-reporting-allowlist-fix.md | PRESERVE design/validation/source history. |
| Root and lib/tools/config/tests/storage/excel-files .htaccess files | PRESERVE access restrictions. |

## I. High-Risk Components and Security/Compatibility Review

| Safeguard | Evidence and removal impact |
| --- | --- |
| SQL SELECT/CTE/function/comment/hint/single-statement restrictions | tools/validate-report-sql.cjs and lib/query-settings.php; preserve parser/refusal paths/dependency. |
| Exact allowlist/read-only reader | Sanitized live config/grants and seven access probes above; no unrestricted replacement access. |
| CSRF/source/layout/draft versions | api.php, base-report.php, query-settings-api.php; keep distinct source/session namespaces. |
| Formula authorization | Annual confirmation/live policy, compact baseUnlocked and approved-row SQL signatures; legacy retirement must not remove these. |
| Review integrity | Token/expiry/source/overlay/revision/fingerprint/recordHash/areaHash/errors and both confirmations remain server-side checks. |
| Apply/history/rollback/recovery | Transactional qs_save/archive/receipt, rollback and session-owner/hash consistency; preserve four tables and services. |
| Workbook/draft recovery | storage_refresh, locks, sync_reconcile, refresh marker, verified snapshots, Resync token and other-month isolation. |

External route contracts affected by removal: index.php default and sheet/legacy paths, base-report.php GET/POST, api.php actions and query-settings-api.php actions. Direct helper/config/test/storage access is denied by project Apache .htaccess configuration; this is static evidence, not a newly run HTTP test. Other hosts may not honor those rules.

CLI/manual contracts include formula-admin setup/status, refresh-template plan/apply, compare-workbooks, diagnostics/setup/migrations, build:css and historical test aliases. Missing repository references do not disprove external consumers. Existing security findings in other reports were not changed or remediated here.

### Consolidation opportunities — proposals only

| Opportunity | Complexity / Risk | Dependencies and benefit | Required tests |
| --- | --- | --- | --- |
| Include unique pure/isolated suites in standard manifest | Low–Medium / Low–Medium | Review fixture routing/config copies; improves coverage without aliases rerunning | edit-history, formula-references, workbook-storage, headers-browser, base-report-compact, runner deduplication |
| Share draft/version/locking primitives | Medium / High | Adapters for distinct formats/signatures/audits; reduce repeated concurrency logic | concurrent saves, wrong source/layout/month, rollback, interrupted refresh, other-month preservation |
| Share confirmation UI with context adapters | Medium / High | Keep cell/source/sheet scopes and separate SQL confirmations; reduce dialog duplication | cancel/Escape, second-cell denial, arrays/read-only, replacement/restore |
| Extract pure XLSX export service for compact reuse | High / High | Workbook selection/OOXML/raw precision/source differences; enables parity | all package parts, formulas/styles/links, multi-sheet drafts and desktop Excel QA |
| Share read-only formatting/presentation helpers | Medium / Medium | Preserve compact table vs annual geometry; less duplication | compact clipping/hidden rows, annual headers/zoom/highlighting, keyboard/mobile |
| Consolidate configuration docs/diagnostics | Low–Medium / Low | Keep secret config and operator contracts distinct from versioned SQL settings | setup review, secret redaction, history/restore/disable |
| Remove package dependency | Not justified | No unused package found | Security/build suites before any future substitute |

## J. Proposed Cleanup Batches

### Batch C1 — Verified Safe Removals

No executable/stored-data deletion is ready unconditionally. After a separate retention decision, select exact regenerable logs/screenshots/exports from the inventory below, preserving historical evidence and generators. Confirm no documentation/download links rely on selected artifacts. Do not include sources, backups, drafts, keys, SQL history or receipts. Retention review is required before these become verified-safe removals.

### Batch C2 — Conditional Legacy Retirement

1. First candidate group: tests/query-settings-fixture.php and tests/brookes-point-review-fixture.php. Confirm manual/external callers retired; preserve isolated replacements and update references.
2. Separately consider lib/formula-auth.php, tools/formula-admin.php, tests/auth-setup.php and runner/current-documentation dependencies after operator sign-off. Preserve web confirmation.
3. Retire old browser/helper files only after migrating every unique assertion into isolated coverage. Retain alias names where external commands depend on them.
4. sales_sync_status remains excluded until upstream ownership/jobs are established. A later database retirement needs its own DBA-owned recoverable plan.

### Batch C3 — Feature Migration and Consolidation

Plan annual parity/shared export/draft/confirmation services and route deprecation separately. Start with missing unique coverage and explicit source/draft contracts. Do not remove annual functionality before workflows/integrations migrate. Backup retention/pruning requires a separate policy and restore verification.

## K. Verification Plan

Before future approved implementation, capture source/state/database/grant baselines, inventory external CLI/task/API consumers and settle artifact retention. Disposable fixtures must not have writable links into live state or unnecessary production credentials.

1. PHP/JavaScript syntax, include/selector/reference/docs-link scans, local CSS build and runner alias checks.
2. Supported unit/browser suites with disposable state. They perform stateful operations, so none were executed during this inspection.
3. SQL security: SELECT/CTE/approved functions, comment/hint/multiple-statement rejection, exact allowlist/reader and unapproved denial, explicit validation independent of compatibility token, matching ambiguities/missing/nonfinite values.
4. Lifecycle: both confirmations/F18, outside-row protection, stale config/source/area/draft/expiry rejection, rollback, duplicate/lost response/cross-session recovery, history/restore/disable, config-only changes and signed replacement/restore persistence.
5. Annual: precise formulas/shared/external dependencies, full-range editing, confirmation/history, independent drafts, export package preservation, sync conflicts/backups, Resync cancel/stale/replay/rollback and interrupted recovery.
6. UI: desktop/tablet/mobile, sticky headers, wheel/dropdown zoom, hidden rows/clipping, keyboard/highlights, loading/disabled reasons/focus. Port unique old assertions explicitly.
7. Recheck live hashes/counts/grants after disposable tests; verify temporary fixture/schema/account cleanup. Never run the old live-store cleanup fixtures or live-workbook tests for this purpose.

Historical Batch B evidence records 25 canonical suite passes and syntax/state checks. No new runtime test result is claimed here.

## L. Final Recommendation

The safest next source cleanup is a narrow C2 retirement of the two historical SQL fixtures, after owner confirmation of manual/external dependencies. Legacy password CLI retirement can be a second conditional group. Preserve both reports, active authorization, source workbooks/drafts/backups, SQL history/receipts and sales_sync_status. Annual retirement is a feature-migration project.

Only this inspection report was created. No application source/state, database record/schema/grant, authorization behavior, formula, dependency or Git checkpoint/commit/push was changed. Final SHA-256 comparison found all 367 pre-existing inventoried files unchanged, with docs/batch-c-inspection.md as the only addition. The test runner was invoked only with --list; no suite was executed. Read-only database transactions/probes made no data or schema changes. Fifty-seven historical/generated test artifacts are inventoried below.

### Exact historical/generated test artifact inventory

All entries below: REVIEW REQUIRED for retention; no deletion performed.

- tests/auth-setup-results.txt
- tests/auto-refresh-browser-results.txt
- tests/auto-refresh-results.txt
- tests/base-report-compact-1280.png
- tests/base-report-compact-1366.png
- tests/base-report-compact-1600.png
- tests/base-report-compact-1920.png
- tests/base-report-compact-390.png
- tests/base-report-desktop.png
- tests/base-report-mobile.png
- tests/brookes-point-approved-preview.png
- tests/brookes-point-protected-preview.png
- tests/browser-results.txt
- tests/cell-editing-browser-results.txt
- tests/edit-history-results.txt
- tests/editable-focus-results.txt
- tests/editing-policy-results.txt
- tests/exported.xlsx
- tests/focus-edited.xlsx
- tests/focus-untouched.xlsx
- tests/formula-browser-results.txt
- tests/formula-browser.xlsx
- tests/formula-highlights.png
- tests/formula-references-results.txt
- tests/formula-results.txt
- tests/formulas-results.txt
- tests/headers-browser-results.txt
- tests/number-format-results.txt
- tests/query-apply-save-desktop.png
- tests/query-apply-save-mobile.png
- tests/query-calculation-desktop.png
- tests/query-calculation-formula.png
- tests/query-calculation-mobile.png
- tests/query-settings-desktop.png
- tests/query-settings-mobile.png
- tests/refresh-results.txt
- tests/sql-formula-replacement-applied.png
- tests/sql-formula-replacement-review.png
- tests/sql-settings-modern-desktop.png
- tests/sql-settings-modern-formula.png
- tests/sql-settings-modern-mobile-formula.png
- tests/sql-settings-modern-mobile-review.png
- tests/sql-settings-modern-mobile.png
- tests/sql-settings-modern-review.png
- tests/sync-reconciliation-results.txt
- tests/sync-workbook-browser-results.txt
- tests/ui-desktop.png
- tests/ui-export.xlsx
- tests/ui-mobile.png
- tests/ui-results.txt
- tests/undo-resync-browser-results.txt
- tests/verification.txt
- tests/verify-results.txt
- tests/workbook-storage-browser-results.txt
- tests/workbook-storage-results.txt
- tests/worksheet.png
- tests/zoom-browser-results.txt
