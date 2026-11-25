# Restore Report Formulas and SQL Values

## Previous behavior

Restore Report Formulas rebuilt the formula overlays and SQL result mappings in the working report. The calculation layer then applied the separate SQL manual-override store above the rebuilt results, so affected cells kept their MAN values. Restore also left draft persistence to the separate Save Draft action.

## Updated workflow

Restore uses the selected reporting month and the existing read-only SQL executor, result validator, and cell mappings. Saved custom SQL calculations run through the same settings workflow as before. The existing COOP integration fetches fresh billing totals for configured sources and validates that all applicable totals are complete. Ordinary edits outside the refreshed SQL targets and restored formulas remain in the draft. Recovered Sales continues to calculate from its separate records.

The server constructs the complete result, removes overrides for validated SQL targets, records their audit events, restores the template formulas, and calculates the dependent cells. It publishes the new draft revision and its SQL override snapshot together in one atomic file replacement. The snapshot supersedes the old override generation, and subsequent edits continue through the existing sidecar. The revision protects the restored draft against stale saves. The report derives dependent values from the saved inputs and formulas on reload and during Excel export.

Successful restoration invalidates SQL cell undo entries for the refreshed cells, including stale tokens from other sessions. Unrelated SQL undo entries remain available. Undo Restore can recover earlier formula edits while retaining the newly saved SQL values and keeping the discarded overrides removed.

## Failure handling

The restore operation is all-or-nothing. The first failed row or COOP source stops the operation before publication. An empty query result, missing area mapping, invalid value, connection error, SQL execution error, or incomplete COOP billing can block restoration. A validated numeric zero is a successful value. Cached source data remains available to the normal reporting workflow and is excluded from restoration. A read-only restore also rolls back any provisional canonical account resolution performed by the COOP integration.

The draft and override removal share a single publication point. A failed validation or draft write leaves the previous draft and effective overrides in place. A configured source must be available for a successful restore. A report with no configured COOP connection skips the COOP refresh and retains any COOP manual overrides.

## Modified files

- `base-report.php` and `assets/base-report.js` route the restore, display confirmation and loading states, update the saved revision, and refresh Undo availability.
- `lib/base-report-restore.php`, `lib/base-report.php`, and `lib/base-report-sql-manual.php` coordinate the draft commit, calculation, audit events, and generation-scoped override state.
- `lib/query-settings.php`, `lib/coop-summary.php`, and `lib/coop-registered-accounts.php` provide strict fresh-source validation through the existing integrations.
- `tests/base-report-browser.cjs`, `tests/query-apply-save-browser.cjs`, and `tests/coop-phase1-browser.cjs` exercise the report, SQL, and COOP restoration workflows.

## Verification

- The Summary browser test checks confirmation and cancellation, formula recalculation, automatic draft saving, reload, and Undo Restore. The base report and SQL formula-replacement PHP suites also passed.
- The SQL Apply & Save browser test checks multiple overrides, a valid zero, preservation of unrelated inputs, independent monthly drafts, stale SQL undo rejection, and restored Excel values with dependent formulas intact. The injected SQL and draft-write failures kept both rollback inputs intact.
- The COOP browser test restores a manual override from a fresh mocked HTTPS billing result and verifies that the SQL value and label survive reload. It also exercises the local MariaDB COOP workflow.

The tests used local fixtures and a mocked hosted COOP transport. The change requires no database migration.
