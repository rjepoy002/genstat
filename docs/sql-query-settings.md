# GENSTAT SQL Query Settings

> Current workflow: standalone configuration saving has been replaced by selected-row **Apply & Save**. See [automatic saving, history and recovery](sql-settings-apply-save.md). Earlier workflow descriptions below are historical.
Follow-up: [Row-level SQL formula replacement](row-sql-formula-replacement.md) adds a default-off per-row opt-in, explicit replacement confirmation and persistent numeric overlays. Earlier non-formula-only statements below describe the original/default behavior.

Implemented 13 November 2026, Asia/Manila. Open the main report at `http://localhost/genstat/` and click the top-right gear, **SQL Query Settings**. No administrator authentication was added.

## Inspection findings and scope

GENSTAT uses PHP 8.2.12, vanilla JavaScript, the custom OOXML layout reader, the existing PHP formula engine, JSON draft overlays and an annual workbook editor/exporter. Node **24.20.0**, npm **11.19.0** and Composer **2.10.3** were available; no Composer dependency or frontend framework was added. The current DB is MariaDB **10.4.32**.

The focused base source contains only `Jul26` (`:ReportMonth = 202607`). No month or worksheet was fabricated. The annual editor's worksheet links, existing `?sheet=...` routes, draft model, export, synchronization and formula confirmation code remain unchanged. The requested Workbook Editor link was removed from the focused report's header; the old route remains available directly for backward compatibility. The selected worksheet is displayed in an accessible navigation landmark.

Live metadata inspection confirmed the existing eight source/configuration tables, including `area_master` and `area_mapping`, with no equivalent query-definition/mapping/history schema. The approved **genstat_kwh SQL was not found**. It was not reconstructed or replaced. The query editor starts empty, and no production query configurations were installed. Runtime tests used explicitly named temporary fixture configurations, which were removed afterward.

## User workflow

1. Open Settings. This reads configuration and fixed area metadata only; saved report queries do not run.
2. Select a visible report row. Options are derived from writable non-formula area cells in the template. Formula-only rows, headings, blank separators and the five hidden adjusted rows are excluded. Mixed rows expose only eligible cells; for example, F18 remains protected. Labels include the worksheet and original A-cell reference to distinguish repeated labels. Stable keys use `genstat-base/Jul26/A11` rather than a label alone, and source hashes/labels protect against template drift.
3. Paste the approved SQL and choose the matching/result columns. `Sales_KWH`, `KWH_Diff` and `TotalKWhSold` are suggested, not fabricated calculations. Other returned columns are supported.
4. Choose explicit master AreaIDs for eligible Excel inputs. Optional SACode matching uses `SACode|AreaID` and checks membership against the existing source/reporting/adjustment area references. Original `area_mapping` records are read, never changed. Combined labels such as Araceli/Dumaran require deliberate reviewed output-grain choices; names or result order are not used to infer mappings. Areas outside this row must be explicitly excluded; they are listed in preview diagnostics.
5. **Test Query** validates and executes one bounded preview with server-bound `:ReportMonth`. It displays returned columns, duration, duplicate/unmapped/missing area errors and invalid numeric values. Testing changes neither configuration nor report values. Every target must have a valid result; duplicate rows are never silently summed or discarded. Explicitly excluded areas are reported.
6. **Save Configuration** requires a successful test of the exact configuration, worksheet/source and bindings within five minutes. Editing SQL, columns, bindings or enabled status requires a new test. Saving does not execute the report SQL or refresh inputs. Existing mappings use optimistic revisions. Timestamps are UTC.
7. Reuse a query identifier to load its SQL and matching columns for another row/result column. One definition can serve multiple rows. Changing the SQL/matching columns of a shared definition requires a new identifier so other rows cannot silently change. Each row has one mapping and query identifiers are unique.
8. **Configuration history** loads a prior snapshot into the editor; it does not apply or execute it. Test and Save Configuration to commit a reviewed rollback. Shared-definition protections still apply.
9. **Review proposed changes** explicitly executes enabled saved queries for the current template/month, once per distinct definition. It displays old/new values and flags existing manual/saved overrides. Errors block all application. Testing, saving and refreshing are separate actions.
10. Check the review confirmation, then **Apply reviewed values**. A short-lived server proposal binds the source, current live overrides, configuration snapshots and saved-draft revision. Changed/stale proposals or missing confirmation are rejected. Only eligible non-formula cells are merged into the existing live report draft. Formulas and values outside the proposal are preserved. **Save Draft** persists the reviewed report through the existing save workflow; applying alone does not write Excel or persist a draft.

The native modal has a dark backdrop, internal scrolling, focus containment, focus restoration, accessible controls and Escape-to-close. Busy operations prevent closing; unsaved configuration edits require confirmation before closing/changing rows. On failure, report values remain intact.

## Query safety and limits

The pinned **node-sql-parser 5.3.8** MySQL grammar produces an AST. A quote-aware lexical pass locates `:ReportMonth` parameters and rejects comments/executable hints, backslash escape ambiguities, variables and multiple statements. AST traversal rejects non-SELECT statements, unapproved tables/databases, unsupported functions, file access and locking constructs. A conservative built-in function allowlist supports aggregates, rounding/truncation, conditional/null handling, date/string functions and common window functions. This is intentionally a supported subset; unsupported syntax fails closed. SQL is passed to Node on stdin with an argument-array process launch, never interpolated into shell commands.

Validated SQL is executed as a derived SELECT through mysqli prepared statements; every `:ReportMonth` occurrence becomes a bound string parameter, never interpolated data. Month values are derived from the source worksheet: Jan26 → 202601, Jul26 → 202607, Nov26 → 202611. A browser-supplied month cannot override the server selection.

Final protection is a dedicated **genstat_report_reader** account with SELECT grants on only:

- `area_master`
- `area_mapping`
- `overallsalessummary`
- `billadjustment`
- `kwh_diff_allocation`

Cancelled-bill tables, system schemas and unrelated tables are not granted. Reader grants are checked at runtime; root, wider privileges, roles/global reads or unsafe grants fail closed. Queries run inside read-only transactions with MariaDB's three-second statement timeout. The executor returns at most 500 rows, rejects incomplete/truncated results for save/apply, allows at most 40 uniquely named columns, and bounds result bytes during unbuffered fetching (4,096 bytes per value, 1MB total). Numeric values are not silently truncated or rounded to display strings. Parser execution also has a five-second deadline and 128MB Node heap cap. Parser/runtime absence disables execution.

This implementation requires the verified MariaDB timeout mechanism. A different DB server fails closed pending a reviewed equivalent timeout implementation. HTTP operations require POST, the report session CSRF token and matching source hash. Responses suppress DB credentials and internal DB error messages. No stored query runs on report load, modal opening or configuration save.

## Database migration and local setup

Applied the additive migration `migrations/001-query-settings.sql` in local `genstat_db`:

| New table | Purpose |
| --- | --- |
| `genstat_query_definitions` | Unique reusable identifier, exact SQL, area/SACode column names, revision/timestamps |
| `genstat_row_query_mappings` | Unique template row, source identity, definition FK, result column, explicit area bindings/exclusions, enabled flag, revision/timestamps |
| `genstat_query_history` | Previous complete mapping/SQL snapshots for reviewed rollback |

These are InnoDB tables. Configuration saves use a transaction, locking, revision checks and history preservation. **Existing source/area/allocation records were not changed.** The separate **genstat_settings_writer** account can select/insert/update only these three new tables. It is never used to execute pasted SQL.

Local setup is already complete on this workstation. Random account passwords were generated and written to **ignored** `config/query-settings.local.php`; they were not printed or committed. Apache denies `.local.php`, SQL/JSON/lock/CJS downloads. Do not commit that local configuration.

For another installation, first inspect existing tables/accounts, then run from the project directory:

```powershell
php tools/inspect-query-settings.php
php tools/setup-query-settings.php
```

The setup CLI uses the existing administrative connection only to create the additive schema and constrained accounts. It refuses existing table/account collisions instead of replacing them. If local configuration already exists, setup exits without changes. DDL is additive and can partially commit if interrupted; inspect partial setup rather than rerunning/destructively resetting tables. Adapt the ignored connection settings and Node executable path for another machine. No SQL account password is an application administrator login; authentication is deferred as requested.

Manual business setup still required: supply the approved `genstat_kwh` SQL, select its result columns, review explicit area-to-template bindings and exclusions, test it, and save its mappings. Real production totals cannot be certified until that approved query is supplied. Do not substitute the test fixture query for it.

## Local Tailwind build

Tailwind CSS **4.3.0** and its CLI are pinned in `package.json`/`package-lock.json`. Node 24 satisfies the installed compiler's Node ≥20 requirement. Assets use local theme/utilities without Tailwind preflight, preserving the existing spreadsheet rules. No CDN, React, Vue or Laravel is used.

```powershell
npm.cmd ci --ignore-scripts
npm.cmd run build:css
```

`npm.cmd` avoids the local PowerShell restriction on npm.ps1. The input is `assets/tailwind.input.css`; output is `assets/tailwind.css` (6.6KB minified in this build), already compiled locally and available offline. Keep that compiled asset with deployments. SQL parsing additionally requires the installed production Node dependency; use `npm.cmd ci --omit=dev --ignore-scripts` when deploying prebuilt CSS. Installing needs package access once; running this configured XAMPP app does not require internet. No Composer install is necessary.

Official references: [Tailwind CLI installation](https://tailwindcss.com/docs/installation/tailwind-cli), [node-sql-parser AST/API documentation](https://github.com/taozhi8833998/node-sql-parser).

## Tests performed

- `npm.cmd run build:css`: local compilation passed. Dependency installation reported zero known npm audit vulnerabilities.
- `node tests/query-parser.cjs`: accepts bound CTE/aggregate expressions and safe literals; rejects modifying SQL, multiple statements, unsafe functions/files/variables/locks, hidden unapproved table references and cancelled-bill tables.
- `php tests/query-settings.php`: live bound CTE preview, worksheet-month mapping, area/order independence, mixed-row restrictions, duplicate/unmapped/missing/null detection, truncation protection, actual 500-row limit and three-second timeout configuration. The reader's attempted no-op UPDATE was denied by DB permissions; no source record was modified.
- `node tests/query-settings-browser.cjs`: full local-only lifecycle, modal/unsaved-warning behavior, eligible rows, empty initial SQL, invalid-query protection, exact-config retest, successful preview, separate save, shared query/result columns, history rollback, explicit refresh, one execution per shared definition, CSRF/confirmation/stale-preview rejection, protected F18, settings reload and mobile layout. No external requests or JS errors. Test configurations/history were cleaned up; no report draft was saved by this test.
- Existing `node tests/base-report-browser.cjs`: input editing, recalculation, selection, formula confirmation/protection, draft save/reload, stale draft revisions and mobile scrolling passed. Temporary test drafts were removed.
- Existing `node tests/base-report-compact.cjs`: all requested desktop sizes plus mobile passed; five hidden rows, readable numbers, compact fit and sticky alignment remain intact.
- Existing `php tests/base-report.php` and `php tests/base-report-view.php`: source checksum, all 402 worksheet formulas, 312 report formulas/cache matches, 96 classifications and hidden dependency chains remain unchanged.
- Existing editing-policy, formula-reference and edit-history tests passed. Syntax and Git whitespace checks passed.

Screenshots of desktop/mobile settings were visually reviewed. New lifecycle tests intentionally use reserved fixture identifiers and refuse to run against nonempty query-configuration tables. Their cleanup is limited to their own reserved records. Legacy annual publish/sync/resync was not exercised against the live workbook because it writes annual state; its code was not changed.

## Export status and remaining limits

The annual editor's existing Excel exporter is preserved. **The focused GENSTAT_BASE report has no export action yet**, so no speculative replacement was built. Next-phase integration must export the clean base XLSX plus its **saved/reviewed** JSON input/formula overlays, validate targets against `base_report_layout`, preserve OOXML styles/merges/sheet structure/formula nodes and request recalculation on open. It must never refresh SQL during export or overwrite the reference template. The annual exporter currently selects a different source/draft namespace and must not be pointed at these values without that explicit adapter.

The current base source supplies only July 2026; the general worksheet-to-month binder is tested for January and November but no worksheets are synthesized. Previously documented external recovered-sales caches and off-table source `#REF!` issues remain unchanged. Formula-free source totals retain their original Excel behavior; no new formulas were invented to change that behavior. Approved-query numerical reconciliation remains pending the actual approved SQL.

## Files in this update

Modified: `base-report.php`, `assets/base-report.css`, `assets/base-report.js`, `.gitignore`, `.htaccess`, and the documentation status links in `README.md`/`docs/base-report-implementation.md`.

Created:

- `partials/sql-settings.php`, `assets/sql-settings.js` — accessible settings and reviewed refresh UI.
- `assets/tailwind.input.css`, `assets/tailwind.css`, `package.json`, `package-lock.json` — reproducible offline styles and pinned dependencies.
- `query-settings-api.php`, `lib/query-settings.php`, `tools/validate-report-sql.cjs` — protected API, area/result/configuration services and parser adapter.
- `migrations/001-query-settings.sql`, `tools/setup-query-settings.php`, `tools/inspect-query-settings.php` — additive schema/setup and read-only inspection.
- Historical creation record: `tests/query-parser.cjs`, `tests/query-settings.php`, `tests/query-settings-browser.cjs`, `tests/query-settings-fixture.php` — original validation and integration coverage. The historical fixture was subsequently retired in Batch C2 Phase 1; current integration coverage uses `tests/query-apply-fixture.php` through `node tools/run-tests.cjs tests/query-apply-save-browser.cjs`. The browser compatibility entrypoint remains supported. See [Phase 1 retirement](batch-c2-phase1-cleanup.md).
- `docs/sql-query-settings.md` — this report.
- Ignored local setup only: `config/query-settings.local.php` and `node_modules/`.

No approved calculation was rewritten, no source workbook was overwritten, and no Git branch changes, commits or pushes were made.
