# Approved SQL input row formula overwrite

Implemented 2026-11-17 (Asia/Manila). This updates the prior formula-protection policy: **Total KWH Sold, row 18** is now the approved SQL input row for reviewed Excel formula replacement, including when the configurable Report Formula is enabled.

## Behavior

- All valid area input cells on row 18 participate in mappings, including Brooke's Point F18. Approval comes from the current workbook's row key, row number, and source label, not a submitted checkbox.
- Automatic matching uses the same targets as manual validation. Existing approved aliases and manual overrides remain intact; missing and ambiguous identities still require resolution.
- The Formula Bar evaluates only SQL result operands. It does not use F18's Excel formula to calculate the replacement.
- Testing and saving configuration do not change report values. Review shows the existing formula, available calculated/displayed value, computed SQL value, difference, mapping source and replacement warning.
- Both the existing review confirmation and the separate explicit Excel-formula-replacement confirmation are required. Apply rechecks configuration, workbook/draft snapshot, mapping metadata, expiry, and approved target authorization before issuing a signed replacement.
- Missing/error formula results without a usable cache display `Unavailable`; comparison does not silently substitute zero.
- The existing signed replacement, Save Draft, history and Restore Template Formula mechanisms are reused. Draft saving retains the numeric replacement, including across reload/new session; the source formula remains available for explicit restore.
- Other rows' formulas, totals, hidden rows, unknown cells, covered merged placeholders, array/data-table protections and read-only structural restrictions remain protected. Existing ordinary SQL input rows continue operating as before.

The old row formula permission is displayed as a disabled row-approval indicator. It cannot authorize arbitrary rows or disable the approved row through a browser-submitted flag. No new database fields or migrations were needed.

## Files changed

- `lib/query-settings.php`: approved-row target selection, canonical row permission, specific mapping errors and reliable current-value/difference handling.
- `lib/sql-replacements.php`: central row-18 approval guard and restrictions during signed record issuance/validation, including actual area-column checks.
- `query-settings-api.php`: exposes approved-row metadata through the existing endpoint; existing confirmation/apply checks are reused.
- `assets/sql-settings.js`, `partials/sql-settings.php`: include approved formula targets independently of Formula Bar enablement; explain the authorization and confirmation requirements.
- `tests/query-calculation-settings.php`, `tests/sql-formula-replacement.php`: updated target expectations and added unknown/unauthorized/missing-cache cases.
- `tests/sql-formula-replacement-browser.cjs`: full confirmation/apply/draft/restore test now uses a temporary isolated PHP document root and draft storage, preserving any existing user draft.
- `tests/query-settings-browser.cjs`, `tests/query-calculation-browser.cjs`, `tests/query-calculation-lifecycle.cjs`: update prior protected-F18 expectations for the new approved-row policy.
- `docs/approved-sql-row-overwrite.md`: this report. Earlier policy documentation is historical and superseded by this row-approval policy.

## Validation

- `tests/query-calculation-settings.php`: 49 assertions passed, including all 15 row-18 targets, computed values, original formula in review, manual overrides, ambiguity, unknown cells, unauthorized rows and unavailable-cache comparisons.
- `tests/sql-formula-replacement.php`: passed signed provenance/tamper checks, row approval, recalculation, outside-row protection, merged/array/shared behavior and source workbook preservation.
- `tests/sql-formula-replacement-browser.cjs`: passed F18 automatic/manual mapping, Formula Bar enabled, test/save without applying values, existing formula in review, separate confirmation, stale overlay rejection, computed replacement, other inputs/manual values preserved, Save Draft/reload/new-session persistence, formula restore/history and stale-signature replay rejection.
- Existing query-settings PHP/browser, formula calculation browser, SQL parser and workbook calculation/view tests passed. Formula calculation lifecycle also checks row-18 computed review/application and configuration history.
- PHP and JavaScript syntax checks passed. Review screenshots were generated and inspected.

The browser persistence test starts an isolated local PHP server with temporary endpoint copies and isolated draft storage. Shared application directories are junctions; no original workbook is edited. Reserved test SQL configurations are removed afterward. Temporary storage is removed only after checking that its resolved path is the expected test directory beneath the OS temporary directory. The original workbook SHA-256 remains unchanged.

Existing saved row-18 configurations that previously omitted F18 or explicitly excluded AreaID 12 must be loaded, completed/adjusted, tested and saved again before refreshing. No existing configuration was silently migrated or rewritten. No billing source data, database permissions or schema were changed, and no GitHub push was performed. Business SQL was not reconstructed; tests use explicit fixtures.
