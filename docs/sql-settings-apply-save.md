# SQL Query Settings: unified Apply & Save

Implemented 2026-11-17 (Asia/Manila).

## Resulting workflow

Select a report row, enter SQL, Test Query, complete mappings and optional calculations, Review Proposed Changes, confirm, and **Apply & Save**. Configuration is committed automatically with a recoverable, server-validated working-report result. **Save Draft remains required to persist report values across sessions.** The source Excel workbook is never written.

The Query Identifier input, Enable Row Mapping checkbox, Approved SQL Input Row checkbox, and standalone Save Configuration button are removed. History sits beside the report row selector. The saved status is read-only: Not Configured, Configured, or Disabled. Separate editor text identifies unapplied changes and validation status. Approved row 18 has an informational note rather than a preference checkbox.

## Review and application

- Review validates the selected row's current editor configuration, not all enabled saved queries. Each row's SQL, mappings, formula and history remain independent.
- A bounded, successful SQL test must exist for this exact SQL, source template, reporting month and selected row. Mappings/formulas can then be edited and validated at Review without a separate save step.
- Test, formula preview, matching, review, history restoration and closing do not save configuration.
- Review independently checks mappings, numeric values and formula operands on the server and retains the validated proposed values. Browser-calculated results are never used.
- Apply & Save checks the proposal token/expiry, exact configuration fingerprint, selected row/version, report overlay/draft version, area metadata and target permissions. Both confirmations remain mandatory when approved Excel formulas are replaced.
- F18 remains eligible in approved Total KWH Sold row 18. Other protected formulas, unknown cells, hidden rows and structural exclusions remain protected. Signed replacements and Restore Template Formula are unchanged.
- Apply is also possible for a reviewed configuration-only change when all report values already agree. If neither configuration nor values change, it remains disabled with No applicable changes found.
- Success explicitly states that the configuration is saved and values are applied to the live report, with Save Draft still pending.

## Coordination, retries and recovery

The application stores configuration in MySQL and drafts in atomic-replacement JSON files. This implementation deliberately preserves that separation; it does **not** claim a transaction spanning MySQL, the browser and a filesystem draft.

1. The server holds the same draft lock used by Save Draft while checking the draft revision and committing an application.
2. It validates and calculates the entire replacement overlay before changing configuration/history.
3. One InnoDB transaction commits the configuration, immutable history versions and a durable operation receipt containing the exact working-report response.
4. Only after commit is that response sent to the browser and applied to the current report state. The draft file is not replaced by this operation.
5. The review token is the stable operation identifier. A duplicate request returns the same committed response/signatures without inserting another receipt, incrementing the version or duplicating history.
6. A failure before commit rolls back configuration, history and receipt. The same unexpired proposal can be retried. If a response is lost after commit, the receipt proves completion and supports replay/recovery.
7. Recover last Apply & Save is available for committed work in the same browser session and template/draft version. Recovery checks current configuration and area hashes, signed replacements and report permissions. It accepts only the original input state, exact prior output, or an unchanged saved-draft baseline with explicit recovery confirmation. Other edits are rejected rather than overwritten.
8. Changing/saving the draft or configuration can invalidate an older receipt. Generate a fresh review when recovery no longer matches. Receipts are retained as apply audit records; there is no automatic deletion.

Recoverable failures log only the validated operation identifier and exception class, never SQL text or credentials. An uncertain response is not reported as All changes saved. Session-scoped recovery does not expose results to another browser session. Save Draft continues using its existing safe temporary-file/atomic-replacement mechanism.

## Identifiers, versions and disabling

New configurations derive an internal business identifier from the source report label/context, such as `genstat_total_kwh_sold`. The internal `canonicalRowId` is saved in existing settings JSON. It excludes the worksheet month and remains stable across months; existing worksheet-specific mapping keys remain in their original storage model and uniqueness is preserved.

Existing identifiers are retained, including the two saved production identifiers; no records were renamed or automatically migrated. If a legacy SQL definition is shared and editing it would modify another row, only the edited row receives a private definition. Its canonical row identity stays stable, and historical identifiers remain in their snapshots.

Every changed, successfully applied configuration receives an immutable history snapshot using the existing history table. Legacy current versions are archived before their first change. History includes the current version and deduplicates already-archived versions for display without deleting anything. Snapshots preserve SQL, columns, mappings/SACodes, manual sources, automatic matching, formula, enabled state, revision and timestamps. Identical configuration applications do not add redundant versions; each distinct successful apply still has its operation receipt.

Restore Configuration loads unapplied settings only. Testing, review and confirmations are required again; the historical version is retained. Successful Apply & Save enables the selected configuration. Merely viewing/testing/restoring a disabled version does not enable it.

Disable configuration is a separate confirmed History-panel action. It changes only the selected saved row, retains its SQL/history and leaves report values untouched. Repeated disabling does not create redundant versions.

## Database migration

Applied additive migration `004-query-apply-operations.sql`: new InnoDB `genstat_query_apply_operations` table, with a unique operation identifier, commit-order sequence, owner/template/draft/configuration/mapping fingerprints and a durable response receipt. Existing configuration/history tables and records were not rewritten.

The settings writer received **SELECT, INSERT only on the new receipt table**. Its existing permissions remain unchanged. No reader permission, credential, billing source table, allocation table, area master/mapping or workbook changed. The exact seven-table reporting allowlist and read-only reader remain enforced.

Deployment helper: `C:\xampp\php\php.exe tools/migrate-query-apply-operations.php`. It is additive/idempotent; fresh setup also includes this migration. Internal save services are retained, but the obsolete standalone HTTP `save` action now rejects requests and directs callers to confirmed Apply & Save.

## Files

Application:

- `partials/sql-settings.php`, `assets/sql-settings.js`, generated `assets/tailwind.css`.
- `query-settings-api.php`, `lib/query-settings.php`, new `lib/query-apply-save.php`.
- New `migrations/004-query-apply-operations.sql`, new `tools/migrate-query-apply-operations.php`, updated `tools/setup-query-settings.php`.

Regression coverage:

- New `tests/query-apply-fixture.php` and `tests/query-apply-save-browser.cjs`.
- Updated `tests/sql-settings-ui.cjs`, `tests/query-calculation-browser.cjs`, `tests/reporting-allowlist-browser.cjs`, `tests/brookes-point.php`, `tests/brookes-point-browser.cjs`.
- Former standalone-save lifecycle entrypoints `tests/query-settings-browser.cjs`, `tests/query-calculation-lifecycle.cjs`, `tests/sql-formula-replacement-browser.cjs`, and `tests/brookes-point-review-browser.cjs` now delegate to the unified isolated lifecycle suite.
- This report and notices in the prior SQL settings/UI documentation.

## Validation results

Passed:

- Full real-browser integration against a disposable configuration database, temporary least-privilege writer and isolated application/draft/session storage. The production SELECT-only reader was reused without changing its grants.
- Removal of all four technical controls; History access; Apply & Save naming and accurate disabled reasons.
- Canonical identifiers across months, preservation of legacy identifiers, safe shared-definition forks and independent row configurations.
- Test/preview/review/restore without persistence; automatic matching, overrides/reset and formula calculations.
- Missing confirmations, modified configuration, changed workbook overlay, expired review, unknown cells, unauthorized rows and stale draft revisions rejected.
- A real isolated database trigger forced receipt persistence to fail after configuration/history were staged: the entire transaction rolled back. Retrying then committed exactly once.
- A real successful response was deliberately dropped before browser delivery: replay/recovery returned the exact committed signed result, preserved prior manual values and added no duplicate versions/signatures. Another session was denied recovery.
- Configuration/history persistence, identical-configuration deduplication, restore/retest, confirmed disable, view/test without re-enabling, and explicit Apply & Save re-enabling.
- F18 replacement, both confirmations, Save Draft/reload/new-session durability, explicit formula restore and row independence.
- Desktop/mobile screenshots and unchanged production configuration/history/receipt hashes, existing draft hashes and workbook SHA-256. Disposable database/account/storage were removed after each run.
- Existing read-only browser SQL/formula/matching/allowlist suites, modern UI suite, PHP query-settings, 49-assertion calculation suite, SQL replacement, Brooke's Point, base-report and base-report-view suites; SQL parser regression, PHP/JavaScript syntax and Tailwind build.
- Read-only permission audit confirmed SELECT on all seven approved tables. Both existing saved July queries executed unchanged for `202607`, each returning 15 rows with BillMonth, SACode, AreaID, AreaName, Sales_KWH, KWH_Diff, Cancelled_KWH, Cancelled_KWH_2, Total_Cancelled_KWH and TotalKWhSold.

## Limits and compatibility

No existing saved SQL, mapping, formula, history version, enabled state or report draft was automatically changed. Existing configurations without formulas/automatic matching continue loading. Legacy versions are retained when first changed. The UI intentionally replaces batch refresh of all enabled rows with explicit review/application of the selected editor row.

Report values are not durable across sessions until Save Draft. A receipt makes a committed working result recoverable under the session/template/version/state checks above; it is not a cross-storage transaction or an automatic merge with later edits. No unresolved implementation failures remain in the tested workflow. No GitHub push was performed.

Screenshots: [desktop](../tests/query-apply-save-desktop.png), [mobile](../tests/query-apply-save-mobile.png).
