Current editing policy: see [CELL-EDITING-FIX.md](CELL-EDITING-FIX.md). Non-formula cells are editable on every worksheet, and formula editing uses confirmation for the selected cell, without an administrator password. This supersedes earlier June-only editing and global formula-unlock instructions.

# GENSTAT workbook storage implementation report

Implemented October 27, 2026 (Asia/Manila), extending the existing PHP/OOXML application.

## Source and migration

The supplied file on disk was `excel-files/incoming/GENSTAT_2026.xlsx`, not `GENSTAT_2026(2).xlsx`. It matched the described ten-sheet workbook and was validated and promoted without modifying its bytes. The requested `(2)` filename is also accepted when placed in `incoming/`. No claim is made that the unavailable `(2)` file was tested.

Active workbook: `excel-files/active/genstat_2026.xlsx`, SHA-256 `2ff0ef351c11b8ede06203b78551248d9d43f7edbb978d94cd4285f7c473e4b0`.

The legacy root `genstat.xlsx`, historical metadata backups and existing draft were retained. The original upload was retained as `excel-files/archive/source-2026-2ff0ef351c11b8ed.xlsx`. Timestamped verified copies of the legacy workbook and the workbook before promotion are in `excel-files/backups/`. The existing June draft remains byte-for-byte unchanged; coordinate mappings project it onto the new version without resetting its revision or manual entries.

```
excel-files/
  .htaccess
  active/genstat_2026.xlsx
  incoming/                       # watched for one pending 2026 .xlsx
  backups/                        # timestamped, hash-verified workbooks
  archive/                        # historical workbooks and source evidence
```

## Structural comparison and external links

The old workbook had only Jun26. The new workbook includes RecovBill26 and Jan26 through Sep26. Browser metadata keeps Jun26 primary to preserve the existing draft boundary; workbook tab order and relationships remain untouched.

June retains A1:AG99, 398 formula nodes and 16 merged ranges. Styles, row/column metadata, worksheet settings, defined names and external-link parts differ. Four cached/source cell values differ. Three June formula expressions differ:

- B22: `+[2]RecovBill26!B38` becomes `+RecovBill26!B38`.
- AC31: `[2]JuneFin!Q43` becomes `#REF!`.
- AC38: `[2]JuneFin!Q18` becomes `#REF!`.

The single remaining external workbook link points to `F:\BOOKKEEPING OFFICER\BOOKKEEPING OFFICER FILES\01.5 Collection Efficiency Computation - 2018-2026\Computation of Collection Efficiency 2026\Consolidated Sales & Collection 2026 - FINAL - May 26.xlsx`. It was retained exactly. GENSTAT does not fetch, execute or rewrite the target. Its availability was not verified. The old second link to the systems-loss workbook disappears in the supplied source itself; GENSTAT did not remove it.

The source retains invalid defined name `ok` and a global print-area name with `#REF!`, as well as the two June error formulas above. They were preserved and identified in workbook notes. Browser calculation continues to report unsupported/external formulas and cached results.

Full cell, formula, format, merged-range, row/column, validation/settings, name and package-part differences are recorded in `storage/workbook-migration-report.json`. Reproduce read-only with `php tools/compare-workbooks.php` (optional arguments: old workbook, new workbook).

## Behavior

Normal page loads and existing status polling detect pending uploads and active workbook changes automatically. Refresh uses the existing exclusive template/synchronization locks, verifies a backup, checks ZIP CRCs and XML, compares structure, reconciles approved input identity, checks all drafts, refreshes metadata and invalidates layout caches when extractor code changes. Unsafe changes retain the active workbook and drafts and produce a nonfatal warning. Multiple incoming files are deferred rather than guessed.

Save Draft and autosave retain their existing behavior. **Sync Now** first saves unsaved entries, then explicitly writes saved approved entries to the active Excel workbook. It verifies client/server workbook versions, reconciles external edits, requires the existing formula authorization for formula write-back, validates a temporary workbook, creates a verified backup, rechecks the active hash immediately before atomic replacement and updates the draft source/version. Failed writes roll back workbook, draft and cache. The browser reloads after a successful changed version. Export Excel remains available.

Apache denies direct access to workbook storage, drafts, configuration, library code, tools, tests and root workbook downloads. Workbook validation rejects unsupported extensions, symbolic-link candidates, traversal in package parts, invalid CRC/XML, oversized packages, XML entities and executable embedded/macro content. External relationships are preserved as data and never followed.

## Files created or changed

Created: `lib/workbook-storage.php`, `tools/compare-workbooks.php`, `tests/workbook-storage.php`, `tests/workbook-storage-browser.cjs`, this report; Apache `.htaccess` rules at root and in excel-files, storage, config, lib, tools and tests; workbook storage folders, active workbook, verified backups, archived source and private JSON comparison reports.

Modified: `lib/app.php`, `lib/template-sync.php`, `lib/template-refresh.php`, `lib/sync-mapping.php`, `tools/build-layout.php`, `api.php`, `index.php`, `assets/app.js`; existing verification/formula/number-format tests to compare against the active workbook. Existing test result files were regenerated.

## Validation

Passed: isolated migration and workbook-storage tests (ten sheets, June-only approvals, draft preservation, precision, untouched XML parts/cells/formulas/styles, verified backups, draft reload, formula protection, external modification conflicts, malformed upload rejection and exact data preservation after failures).

Passed: existing automatic-refresh suite and structural reconciliation suite, including changed values/formulas/geometry, circular dependency rejection, moved rows/columns, ambiguous/deleted inputs, concurrent synchronization and non-destructive recovery.

Passed: workbook export verification (398 June formula nodes preserved), formula engine/export/authorization tests, number-format tests, authorization setup/session expiry tests, dependency-reference parser tests, browser Ctrl+wheel zoom tests, and browser storage tests covering every sheet, branding, formula bar/protection, two-decimal display, headings, JavaScript errors and actual Apache HTTP 403 responses.

## Remaining compatibility boundaries

Only the established June 2026 inputs have business-approved editing rules. Other months and RecovBill are visible read-only; no new manual-input rules or administrator role were invented. Current application period handling remains 2026-06. Other reporting years are preserved but require an explicit future extension of period/input rules before activation. Ordinary compatible 2026 uploads need no refresh command.

The exact requested `GENSTAT_2026(2).xlsx` must still be validated when supplied. External-link availability and calculation in desktop Excel were not tested. Source `#REF!` formulas/names require correction by the workbook owner. Application file locks coordinate PHP writers; external Excel writers are detected through version hashes and Windows replacement errors, so close Excel while synchronizing. No backup retention/deletion policy was introduced.

