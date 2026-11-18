# Dynamic reporting months

## Month discovery and selection

The compact GENSTAT report lists distinct `overallsalessummary.BillMonth` values through the existing read-only reporting account. It removes malformed values, rejects invalid calendar months, and sorts valid `YYYYMM` values newest first. Labels display the month and year, such as August 2026. Month discovery still reads `overallsalessummary` after the reporting allowlist expanded to eight tables with `latebills`.

Open the compact report or use an explicit link such as `index.php?month=202607`. A valid explicit selection takes precedence, then the last selection in the same browser session, then the newest available month. An unavailable explicit month produces an error. The toolbar lists available years newest first and the selected year's months in calendar order. When changing years, it keeps the same month if available or selects the newest available month in that year. A period change with unsaved edits asks whether to discard them. Cancel preserves the report and its edits.

All periods use the `Jul26` worksheet for layout, formulas, and formatting. A direct page open loads the selected period's saved draft and computes Total KWH Sold from its enabled configuration. A Year or Month change also previews other enabled shared SQL configurations. Draft inputs override those other previews. Numeric July template inputs and cached formula results are cleared from the working model for other months. Dependent formulas calculate from the current period's inputs.

The period switch uses one request and updates the heading, table, selectors and URL together. A fixed overlay darkens the viewport and blocks editing, SQL Settings, and export while the report loads. The spinner respects reduced-motion settings. On failure the previous report stays visible and the selectors revert to its period. **Retry** requests the failed period again.

## Shared SQL configuration

Definitions, row mappings, histories, and calculated expressions remain shared across months. Their existing row keys identify rows in the reference sheet. **Disable Configuration** and changes to a shared expression or mapping therefore apply to that configuration in every month. Configurations, expressions, and SQL text were preserved.

Test Query binds the selected month through the existing prepared `:ReportMonth` parameter and validates the results and mappings. Apply & Save requires a successful test for the same period and settings, then executes and validates the query again before committing. It refreshes the current report using the committed response. The operation receipt stays bound to its month, session, template and draft version. Use **Save Draft** to persist the pending report values.

Selecting a month leaves SQL text intact. The editor flags values that look like a fixed reporting month. Automatic preview stops if a saved query contains a value that looks like a fixed month. Queries that return a `BillMonth` or `ReportMonth` different from the selection cannot be tested successfully or applied. The validator requires the supported placeholder.

Preview values for other rows stay separate from the editable draft. For the enabled **Total KWH Sold** configuration, the query's mapped values take priority over source formulas, cached numbers, and old draft overlays on both initial load and period changes. F18 and the other mapped area cells are read-only in the compact report. A failed or incomplete result shows the row as unavailable. Apply & Save can replace formulas in an explicitly approved SQL row.

## Draft isolation and July compatibility

Drafts use `storage/base-report-{templateHash}-{YYYYMM}.json` with a per-month lock and revision. The server validates availability, authorization, and template compatibility before handling a request. Month-scoped CSRF tokens, formula confirmations and SQL replacement provenance prevent month changes from carrying authorization to a different draft. Saves use the existing atomic file writer.

The July report reads a pre-existing hash-only draft when July has no month-specific file. Its revisions, saved values, formula overrides, and signed replacement records load as July data. The first confirmed Save Draft writes the July-specific file at the next revision. The original file remains intact. Future reads prefer the July-specific file. Earlier signed replacements and recovery receipts without a month are accepted only in July and remain subject to their original authorization checks.

## Errors and operational limits

The report and settings API return a controlled error when the reporting database cannot supply month availability. An empty list produces an unavailable message. Invalid months, stale drafts, and test tokens or signatures from a different month are rejected.

The selector uses the current database contents. The most recent successful test in a session supplies the active token. If testing in multiple tabs supersedes older work, run Test Query again in the intended tab.

**Export Excel** downloads the currently displayed compact period. Mapped Total KWH Sold cells export as numbers, including F18, or as `#N/A` when unavailable. The export retains the template tab name, selected-period heading and filename, hidden rows, and unrelated formulas. Non-July exports use cached report calculations and open in manual calculation mode. The annual export and synchronization paths continue to operate on the annual workbook.

## Verification

Regression commands run from an isolated verification copy:

```powershell
node tools/run-tests.cjs --list
node tools/run-tests.cjs tests/query-settings.php tests/sql-formula-replacement.php tests/base-report-browser.cjs
node tools/run-tests.cjs tests/query-apply-save-browser.cjs
```

The month tests cover sorting and deduplication, invalid and empty results, explicit and remembered selection, cancellation of a dirty month switch, legacy July fallback, independent revisions and values, prepared month binding, shared settings, fixed-month warnings, and protection against cross-month draft, test token, receipt and signature use. The browser fixture writes only to disposable drafts and SQL settings tables. Reporting month discovery reads the configured reporting tables.

All **31 supported suites passed** across the lifecycle run and the run of the other 30 suites. The annual editor, Excel export, synchronization, Resync, and responsive layouts passed in the isolated environments. PHP syntax checks passed for 65 files and JavaScript syntax checks for 35 files. The lifecycle fixture verified that production SQL settings, history, and receipts were unchanged. Hash comparisons matched pre-test copies of 12 protected workbook, draft, credential, and signing-key files.

## File inventory

- Compact page and assets · `base-report.php`, `assets/base-report.css`, `assets/base-report.js`, `assets/sql-settings.js`, `partials/sql-settings.php`
- Month, draft, and authorization code · `lib/base-report-view.php`, `lib/base-report.php`, `lib/query-apply-save.php`, `lib/query-settings.php`, `lib/sql-replacements.php`, `query-settings-api.php`, `tools/validate-report-sql.cjs`
- Regression coverage and fixture setup · `tests/base-report-browser.cjs`, `tests/query-apply-fixture.php`, `tests/query-apply-save-browser.cjs`, `tests/query-settings.php`, `tests/sql-formula-replacement.php`, `tools/run-tests.cjs`
- New documentation · `docs/dynamic-reporting-months.md`

### Year and month refresh enhancement

- Compact UI and period endpoint · `base-report.php`, `assets/base-report.js`, `assets/base-report.css`, `assets/tailwind.css`
- Automatic preview and period-local calculation · `lib/query-settings.php`, `lib/base-report.php`
- Compact export and shared OOXML helpers · new `lib/base-report-export.php`, `lib/xlsx.php`, `lib/app.php`, `lib/formula-edits.php`
- Isolated browser and workbook verification · `tests/query-apply-save-browser.cjs`, `tests/query-apply-fixture.php`

The initial enhancement's browser fixture adds future years only within its disposable copy, so the year picker can be tested without inserting reporting records. It verifies centering and interaction blocking, one request on year change, retained state and Retry after failure, and an XLSX package with the selected period and hidden rows. The Total KWH Sold fix extends that fixture to check every mapped cell, stale drafts, unavailable results, dependent formulas, and numeric XLSX output.

All 31 supported suites passed for the initial enhancement. The annual editor regression passed with the shared OOXML helpers.

For the Total KWH Sold fix, live read-only checks mapped all 15 area cells for both July and August 2026. F18 displayed 3,780,899.00 and 3,863,653.00 respectively. The isolated lifecycle checked an old B18 manual value and an old F18 formula override, verified that unavailable SQL yields `#N/A`, and reopened the export to check all 15 numeric area values and the retained Q18 formula. All 31 supported suites passed, and the production settings and protected workbook and draft hashes matched their pre-test state.
