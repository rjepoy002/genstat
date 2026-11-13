Current recovery controls: see [UNDO-RESYNC.md](UNDO-RESYNC.md). Undo/Redo covers ordinary and formula edits and survives autosave. Resync confirms before discarding the selected draft and reloads Excel without writing draft values into it.

Current editing policy: see [CELL-EDITING-FIX.md](CELL-EDITING-FIX.md). Non-formula cells are editable on every worksheet, and formula editing uses confirmation for the selected cell, without an administrator password. This supersedes earlier June-only editing and global formula-unlock instructions.

## Workbook storage update (October 27, 2026)

See [WORKBOOK-STORAGE.md](WORKBOOK-STORAGE.md) for the current storage, automatic refresh, synchronization, migration findings and tests. The active workbook is now `excel-files/active/genstat_2026.xlsx`. Place one new workbook in `excel-files/incoming/`; ordinary compatible updates need no template-refresh command. Save Draft preserves browser work; Sync Now writes saved approved entries to Excel with a verified backup. The older sections below describe the original implementation and optional diagnostic tools.

# PALECO GenStat - Worksheet and Formula Editing

An editable rendering of the supplied original workbook. Open **http://localhost/genstat/** with XAMPP Apache running. No Composer packages, database or department roles are required. Original formula changes require the optional server-side administrator setup described below.

## XAMPP setup

1. Keep this project at `C:\xampp\htdocs\genstat`.
2. Start Apache in the XAMPP Control Panel. MySQL is not needed.
3. Visit `http://localhost/genstat/`.
4. Select the supplied reporting period, edit pale yellow cells, and click **Save Draft**. **Open Report** restores the last saved values. **Export Excel** saves any pending edits first, then downloads an XLSX made from the master template.

Requires PHP 8.1+ with DOM, JSON, session, and zlib (tested with XAMPP PHP 8.2.12). PHP ZipArchive is not enabled in this environment; the small ZIP adapter uses zlib and retains compressed bytes for untouched workbook parts. Apache must honor the included `.htaccess` files. The storage directory must be writable by Apache. Direct HTTP access to storage/config/lib/tools/tests is denied. This phase is intended for the local XAMPP environment.

## Source workbook and layout

The project initially contained only `genstat.xlsx`; no starter application existed. The master has not been modified.

- Worksheet: `Jun26`; used range: `A1:AG99` (33 columns, 99 rows).
- 1,611 explicitly stored cells, 241 cell styles, 16 merged ranges, 22 comments.
- 398 formula nodes, including shared formula nodes; 162 contain explicit formula text. Shared formula references/attributes are preserved without expansion or rewriting.
- Hidden rows: 16, 19, 34, 37, 46, 47, 56, 57, 58, 59, 60, 61. No hidden columns in the used range.
- Print area: `'Jun26'!$A$1:$R$76`; landscape, paper size code 14, print scale 50. Original margins and binary printer settings are preserved in export.
- Original zoom: 70%. Working columns S:AG are included even though they lie outside the print area.
- Two external workbook references are retained. Their target paths and original defined names (including existing `#REF!` definitions) are recorded in the layout and retained on export. The linked workbooks were not supplied.

`config/layout.json` is generated directly from the workbook. It records worksheet names/state/range, all explicit cells (including cached values, types, formulas, shared formula attributes, classifications and evidence), all row/column definitions and hidden flags, fonts/fills/borders/alignment/number formats, theme colors, merges, comments, workbook defined names, external links, and worksheet settings including print configuration. Cells not explicitly stored in the XML are implicit blanks; the renderer supplies every coordinate in the used range. Numeric literals without approved input evidence are classified as protected source values or results, rather than automatically treated as inputs. No database fields are identified or introduced in this phase.

To inspect the source, or prepare and apply a reviewed template update (read the review JSON between the two refresh commands):

```powershell
php tools/inspect.php
php tools/refresh-template.php --plan storage/refresh-review.json
php tools/refresh-template.php --apply storage/refresh-review.json
```

Review `config/inputs.php` against the replacement source before deployment. The app detects source-template and input-definition changes. Compatible content-only template changes are checked in memory without rewriting layout definitions; structural changes and conflicting overlays require a reviewed CLI migration. The legacy build-layout.php command no longer writes directly; use the reviewed refresh workflow below.

## Input policy

The source has no explicit input schema, data validation list or unlocked input map. The editable allowlist is therefore deliberately limited to cells with source comments supporting their source-data role:

| Cells | Source evidence |
| --- | --- |
| B11, C11, D11, G11, H11 | Purchased energy comments: source values, primary metering, previous report or retain |
| L11:P11 | Comments: NPC billing only |
| B39:H39 | Comments: obtain TX/SS losses from the named source person |

These 17 cells include populated values and blank C39/E39. Evidence is recorded per cell in the configuration. All accept finite decimal numbers (including negatives and scientific notation) or blank. Approved source-empty inputs C39 and E39 additionally accept supported formula expressions. Both browser and server validate values. The numeric-value endpoint rejects formula strings, unknown cells and protected labels. Formula expressions use a separate validated overlay; only approved empty inputs or authorized original formula cells may use it. The pale yellow override is only in the browser; Excel retains the original cell fills.

Other constants, historical totals, duplicate pasted results, signatures and labels remain protected. Existing formulas never become numeric input fields merely because a comment mentions an input; editing their expressions requires authorized formula-bar access. The allowlist is based on workbook evidence; business owners should review any expansion rather than assuming all nonformula cells are manual inputs.

Only **June 2026** is offered because that is the reporting period in the supplied workbook. Other months are not fabricated from June data. A reviewed template/period mapping is needed before enabling further months.

## Drafts and export

Drafts are stored in `storage/draft-2026-06.json` as input overrides, template hash, revision, and UTC save time. Unchanged inputs inherit master values; explicitly cleared inputs remain blank. Browser timestamps display in the user's local timezone. Reload/navigation warns when edits are unsaved. Draft writes use a lock, temporary file, replacement, session CSRF token, and optimistic revision check to prevent a stale window silently overwriting a newer draft.

Export reads the saved draft and patches only allowlisted numeric cell values in a fresh in-memory copy of the original ZIP package. Formula nodes, shared formula attributes, merges, formatting/number formats, external links/caches, comments/VML drawings, calculation chain, worksheet dimensions, hidden rows and print settings remain intact. Untouched ZIP parts retain their original compressed payloads. The workbook calculation properties request a full recalculation in Excel. The master workbook is never opened for writing.

## Browser fidelity and limitations

The browser preserves worksheet coordinates, merged regions, visible/hidden row state, source row heights, column widths, cell styles and number-format display for the formats used by this workbook. Text comments are available in cell tooltips. Controls are outside the sheet. Both axes scroll; zoom is adjustable.

Supported formulas now recalculate on the PHP server after open/save using a bounded parser/interpreter. This is not claimed equivalent to the full Excel engine. External or unsupported source formulas retain flagged cached values; Excel and linked files may be needed for complete refresh. See the current formula-editing section below.

Column-width conversion uses the original Arial 12 normal font with an approximate 9-pixel maximum digit width at 96 DPI. Browser font availability, font substitution, text metrics, overflow, line height, accounting spacing and border rasterization can differ from desktop Excel. Number-format rendering handles the source's integer, decimal, percentage and accounting precision/zero display; it does not emulate Excel's full accounting padding engine. Hidden rows remain hidden, with their complete content in the machine definition and export. Native Excel comments/author formatting, VML note shapes, selection behavior, page breaks and printer pagination are not recreated as browser UI. These features are retained in XLSX export. Browser printing is not a substitute for printing the exported workbook in Excel.

No pixel comparison with desktop Excel or Excel recalculation/repair-prompt check was possible here. Structure, formulas, ZIP validity and browser behavior were tested; final visual and recalculation acceptance in desktop Excel remains a business review step.

## Validation

Run the source/export verification:

```powershell
Get-ChildItem -Recurse -Filter *.php | ForEach-Object { php -l $_.FullName }
node --check assets/app.js
php tests/verify.php
php tests/refresh.php
```

`tests/browser.cjs` uses Playwright installed outside the project at `$env:TEMP\genstat-browser` and the installed Microsoft Edge. Start Apache first. The test refuses to run if a real draft already exists; it removes its own test draft after finishing. To install the optional test dependency and run:

```powershell
npm.cmd install --prefix "$env:TEMP\genstat-browser" playwright --no-audit --no-fund
node tests/browser.cjs
```

On 2026-10-26 all PHP and JS syntax checks passed, the fresh layout matched the source extraction, all 398 formula nodes and all unchanged cells matched the master after export, every ZIP part had valid size/CRC and XML parsed, all unaffected parts matched exactly, worksheet metadata and master hash were unchanged. Independent .NET ZIP reading also passed for all 19 exported parts.

Headless Edge passed: worksheet and merge counts, source hidden rows, full used range, pale yellow inputs, both scroll axes, protected cells, numeric validation, unsaved navigation warning, JSON save/reopen, CSRF enforcement, stale-revision rejection, XLSX download, Apache direct-access denial and zoom. No JavaScript runtime errors were observed. Test artifacts are in `tests/` and are not served by Apache.

## Files

- `index.php`: original worksheet rendering and external application controls.
- `assets/app.css`, `assets/app.js`: worksheet styling, editing, validation, dirty state, draft/export controls and zoom.
- `api.php`: open/save/export endpoints.
- `lib/xlsx.php`: ZIP and OOXML helpers; `lib/app.php`: validation, persistence and template export; `lib/render.php`: style/format conversion.
- `config/layout.json`: generated workbook definition; `config/inputs.php`: evidence-backed input allowlist.
- `tools/build-layout.php`, `tools/inspect.php`: extraction and source inspection.
- `storage/`: temporary JSON drafts; `.htaccess` files protect server-only paths.
- `tests/verify.php`, `tests/browser.cjs`: source/export and browser checks.
- `.gitignore`, `README.md`: generated-artifact exclusions and operating documentation.
- `genstat.xlsx`: unchanged original master.

## Browser formula protection and dual input workflow

**Show Formula Protection** is enabled by default and remembered in browser local storage. When enabled, selecting a formula cell displays a small lock and a read-only tooltip/status message. Keyboard focus also selects formula cells. Turning it off hides these indicators only. This setting never grants editing rights. Original formulas remain read-only unless the separate server-authorized Formula Editing mode is active; edits then use the formula bar. This setting never writes to the workbook or changes Excel protection settings.

Formula detection uses the actual OOXML `<f>` elements, including empty shared-formula followers, ordinary formulas and cross-sheet/external references. Declared shared/array/data-table result ranges are also protected. The current source contains 398 formula nodes; one additional blank cell (E40) falls in a declared shared-formula range and is conservatively protected. Static labels, signatures, structure and unapproved blanks remain protected. The existing 17 approved numeric inputs, including blank C39/E39, remain editable. Only an approved merged range's top-left anchor can be editable; covered coordinates cannot be written. Explicitly approved implicit blanks within the used range are supported, including correctly ordered cell creation on export.

The server checks the current input allowlist **and the actual source worksheet's formula/range/merge policy** during save and export. Even a mistakenly permissive generated editable flag cannot authorize a formula write. All writes require session CSRF, source-template and layout version headers (`X-CSRF-Token`, `X-Template-Hash`, `X-Layout-Hash`). The layout header also prevents an old browser page from saving against changed input definitions when the workbook bytes did not change. Export still retains the original formula XML, workbook format and print settings. No workbook protection elements or passwords are added.

### Web entry

Use the worksheet, Save Draft, Open Report and Export Excel as before. Inputs are numeric or blank. The browser also retains loaded overrides for inputs not rendered because their source rows/columns are hidden, so editing visible cells does not discard unseen saved values. Supported dependent formulas recalculate on the server after save. External/unsupported results retain flagged caches.

### Local administrator Excel refresh

The refresh tool is CLI-only and has no HTTP endpoint. Apache denies its directory. Do not edit Excel, input definitions, layout JSON or draft files while a refresh is running.

1. Keep a separate copy of the current Excel file **before** editing it. Edit/save `genstat.xlsx` in Excel and close Excel. To approve an additional numeric input, edit `config/inputs.php` with explicit evidence. Formulas always override approvals.
2. Run `php tools/refresh-template.php --plan storage/refresh-review.json` from the project directory.
3. Read `storage/refresh-review.json`. It shows source and layout hashes, formula text/attribute/type changes, input-definition changes, nonformula default value changes, worksheet structure changes, each draft's preserved overrides, and blocking conflicts. Planning writes only this report; it does not replace layouts or drafts.
4. After reviewing the concrete changes, run `php tools/refresh-template.php --apply storage/refresh-review.json`.
5. Reload browser pages and reopen reports. Existing page versions are rejected after refresh rather than silently applying stale cell mappings or defaults.

Structural or input-definition changes pause web access until a reviewed migration. Compatible value/formula-only changes use current source content without rewriting layout.json. Conflicting saved overrides are preserved and reported rather than overwritten.

Apply reacquires an exclusive template lock and recomputes the report. The source, input definitions, active layout and all drafts must still match the reviewed fingerprints. A stale, edited or replayed review is rejected. Web requests hold a shared lock while reading/saving/exporting so they cannot race the CLI transaction.

Before any active file replacement, apply creates and verifies `storage/backups/<UTC timestamp>-<random suffix>/` containing the prior layout, observed input definitions, every active draft, review report and the newly observed workbook. This backup is protected by storage's Apache access rule. The observed workbook is a backup of the candidate source; an older workbook that was already replaced cannot be reconstructed by the tool. Keep the pre-edit copy from step 1.

Compatible drafts retain every saved override verbatim, including explicit blanks. Their template hash and revision are updated; their original save time is retained. Cells without a saved override adopt reviewed new Excel defaults. The tool reports and refuses the entire apply if a draft input becomes a formula, is removed/revoked/merged away, changes input type or merge ownership, has changed row/column label context, or contains unsupported data. A cell changed in both Excel and the saved draft is a conflict unless the two resulting strings already agree. Worksheet renames and multi-sheet workbooks are blocked because this phase supports the original single-sheet report. Additional months still require a reporting-period mapping rather than reusing June silently.

There is no force flag and no automatic dropping of conflicting fields. For a value conflict, explicitly reconcile the candidate workbook against the report (for example, restore the old Excel baseline when retaining a web override), then generate and review a new plan. If a former input must become a formula or be removed, first copy its complete draft to a protected archival directory, then explicitly move the incompatible draft out of active `storage/draft-*.json` files before replanning. Archived data stays available for manual reconciliation; it is not silently discarded or migrated into formula cells. The active report will start from the reviewed source if its old draft was deliberately archived.

Apply uses temporary-file replacements and rolls back applied files if a write fails. An in-progress marker blocks the app after a process interruption. If `storage/refresh-in-progress.json` remains after a crash, stop Apache, inspect its recorded backup, restore the prior layout and all draft files from that backup, and verify the restored hashes before removing the marker. Also restore the matching workbook/input definitions if rolling back to web use, or replan against the candidate after recovery. Keep the backup and marker until recovery is verified; do not bypass the marker to resume editing inconsistent data.

### Additional validation and changed files

`tests/refresh.php` uses temporary fixture copies and leaves the live workbook/drafts untouched. It covers direct-source-change detection, compatible synchronization and reopen, exact backups, preservation of overrides/cleared inputs, changed defaults versus web edits, input-to-formula conversion, revoked approvals, stale/replayed plans, input-definition changes after review, merged anchors, implicit blank inputs, live-source save/export defense and interrupted-refresh detection.

`tests/browser.cjs` additionally verifies ordinary/shared/external-reference formula locks, typing/Delete/paste prevention, the display-only toggle and its persistence, blank/populated editors, rejected formula save requests, stale template/layout headers and all prior browser functionality. Results are recorded in `tests/refresh-results.txt`, `tests/browser-results.txt` and `tests/verification.txt`. The export regression check still compares all 398 formula nodes and unchanged workbook metadata with the source. Tests passed on 2026-10-26. Desktop Excel rendering and recalculation remain unverified.

New implementation files: `lib/workbook-policy.php`, `lib/files.php`, `lib/template-refresh.php`, `tools/refresh-template.php`, and `tests/refresh.php`.

Modified: `index.php`, `assets/app.css`, `assets/app.js`, `api.php`, `lib/app.php`, `tools/build-layout.php`, generated `config/layout.json`, `tests/browser.cjs`, test result artifacts and this README. `config/inputs.php` and `genstat.xlsx` were not changed by this work. No authentication or MySQL was introduced.

## Excel number-format display

Numeric display now resolves each cell's actual `numFmtId` through the workbook's custom format definitions first, then the relevant built-in Excel format codes. PHP compiles the format into a display specification used by both server-rendered cells and JavaScript input previews. Custom format IDs are not assigned hardcoded decimal precision. The existing layout extraction was already retaining the correct codes, so neither the template nor layout definition needed regeneration for this fix.

Grouping, fixed/optional decimals, percentages, negative sections (including accounting parentheses), and zero sections (including accounting dashes) follow the source format. General uses up to 15 significant display digits without thousands separators, avoiding raw binary-serialization artifacts. Quoted/escaped literals are preserved. Excel's underscore and repeat-fill directives control pixel spacing in desktop Excel; they do not alter numeric text here. Exact accounting padding and General's column-width-dependent switch/overflow behavior remain subject to browser font/layout differences. This renderer supports the number formats used by the supplied GenStat workbook, rather than Excel's entire format language.

Editable cells show the formatted preview while unfocused and separate clean numeric editing text on focus. Untouched source values use the format precision without grouping; explicit user entries retain their entered precision. Blur restores the preview. Focus/blur never replaces the separately stored raw value. Drafts/export continue to use raw validated strings, with no grouping separators; invalid or grouped numeric entry is rejected. Formula results use the same display formatter; original expressions remain locked by default.

Checks: `php tests/number-format.php` covers source formats, zeros, negatives, percentages, General, custom-ID resolution, precision and numeric XLSX export/style preservation. `node tests/browser.cjs` additionally checks clean-edit/formatted focus transitions, accounting previews, separator rejection, and precise save/reopen. Source/export and refresh regressions remain in `tests/verify.php` and `tests/refresh.php`. Results are recorded in `tests/number-format-results.txt` and the existing test logs. The master workbook's SHA-256 remains `5060879f6b18ca46e3329e97078300843ca8b462be2178521a47bb6ce1f6e5a1`.

## Editable-cell focus correction (Jun26!B11)

Investigation of `genstat.xlsx` found B11 (Main / KWH Purchased) stores `26992328.739999998` with style 47, built-in number format 4: `#,##0.00`. Its formula cell B12 uses style 50 with the same number format. The generated layout agrees with the workbook. No General-format exception or cell-specific override was required.

The previous PHP formatter and JavaScript preview already showed B11 as `26,992,328.74` when unfocused. A browser click hid the preview and exposed the same raw string in the input. That reuse of storage text as editing text caused the long value on focus; the previous focus test had explicitly expected that behavior.

The browser now tracks raw numeric storage and editing text separately per input. Preview text is derived independently from the raw value. An untouched source input uses clean editing text according to its format (B11: `26992328.74`); a user's changed valid entry stays exactly as entered, including intentional extra decimals, through save/reopen. General uses its existing significant-digit display rule rather than an imposed two-decimal rule. Percentage inputs use an unscaled numeric ratio for editing and percentage formatting for their preview. Focus/blur does not mark a cell changed, round its stored value or create an override. Invalid editing text is kept separate from the last valid raw value and blocks saving.

`lib/render.php` supplies clean initial editing text; `index.php` retains the raw source in separate original-value metadata; `assets/app.js` keeps distinct raw/edit state and saves only raw values. The normal preview still uses the original workbook format. Neither number formats nor formulas in the original workbook are modified.

Run `node tests/editable-focus.cjs` with Apache running. It refuses to overwrite an existing draft and removes its own test draft afterward. The real Edge browser test clicks B11, checks the normal and focused appearance, saves/exports without editing to prove the exact original raw value is retained, then changes B11 to `26992328.75`, clicks outside, saves, reloads and downloads another workbook. `tests/check-focus-export.php` checks both downloaded files for numeric type, raw value, original `#,##0.00` style/format, unchanged styles XML and all 398 formula nodes. See `tests/editable-focus-results.txt`. The existing browser and number-format tests were updated for the clean-edit contract; source/export and refresh regression tests continue to pass.

## Current formula editing, calculation and authorization

This section supersedes the earlier Phase 1 descriptions of cache-only calculations and permanently locked formulas. The existing PHP/XAMPP architecture, DOM/zlib XLSX adapter, absolute-position worksheet renderer, format-code display specifications and JSON draft storage remain in use. There is no PhpSpreadsheet installation or previous calculation engine. No framework, MySQL, paid service or full user/department authentication system was introduced.

### Local use

1. Start XAMPP Apache and open **http://localhost/genstat/**. The original `Jun26` sheet remains in its original layout.
2. Select a cell. The formula bar above the sheet shows its address and underlying value or actual formula. Shared followers show their expanded expression, including correct absolute/relative references. Static labels and reserved cells are read-only.
3. Numeric entry continues as before. To create a formula without administrator authorization, select approved source-empty input **C39** or **E39**, enter an expression starting with `=`, press Enter, and Save Draft. These are the existing approved input fields; no other blanks were broadly opened.
4. Enter in the formula bar commits a pending edit; Escape cancels its uncommitted text. Save Draft validates and persists edits, recalculates supported dependent formulas, and updates their display. Invalid syntax or circular dependencies leave the previous saved revision intact. Numeric formula errors such as division by zero display as spreadsheet error values.
5. Open Report/reload restores saved expressions. Export Excel downloads a copy containing the actual formula XML, rather than flattened values.

Example formulas: `=SUM(B11:D11)`, `=C10+D10`, `=IF(B11>0,B11,0)`, `=ROUND(D11/3,2)`, or `='Jun26'!D11`. Quoted cross-sheet references are supported only when the named worksheet actually exists. `Sheet 2` is not present in the supplied workbook; the app rejects that reference rather than inventing a sheet. The current renderer/period mapping still serves the original single-sheet June report; multi-sheet structural publication remains a reviewed implementation change.

### Authorizing original formula edits

No administrator credential is installed by default. Until configured, **Unlock Formula Editing** explains the required local setup and original formulas remain locked.

From a local project terminal:

```powershell
php tools/formula-admin.php setup
```

Enter an editor identity, a password of at least 12 characters, and confirmation. This simple PHP CLI reads visible terminal input; use a private local terminal. It stores only a password hash and the configured identity in Apache-protected `storage/formula-auth.json`. Re-running setup backs up the previous configuration and invalidates prior grants. This is a shared administrator credential with an audit identity, not individual user accounts or department roles.

Click **Unlock Formula Editing**, accept the risk confirmation, and enter the administrator password in the password dialog. A server-side PHP session grants **10 minutes** of original-formula editing; the interface shows **Formula Editing ACTIVE** with an orange indicator. Select an original ordinary/shared formula, edit it in the formula bar and press Enter, or press F2/`=` on an authorized selected cell to start in the bar. Original formula cells require expressions beginning with `=`; use `=5` if an authorized constant expression is intended. Array/data-table formulas and reserved range cells without actual formulas remain unsupported for modification.

**Lock Formula Editing** revokes the grant immediately. Expiry, session termination or credential rotation also returns to locked mode. Pending edits remain visible, but saving protected changes after locking/expiry requires authorization again. Each save compares protected formula changes with the saved draft and checks the active server grant. Unchanged previously authorized overlays can be retained while ordinary users save permitted data, and saved formulas can still be exported after a grant ends. No client flag, disabled-input change or protection-indicator setting can grant permission. The server rate-limits unsuccessful unlock attempts across sessions. CSRF and source/layout version checks remain mandatory for mutations; cookies are HttpOnly and SameSite Strict. The application remains intended for local XAMPP use; network deployment would require appropriate transport and access controls.

### Calculation and formats

`lib/formula-engine.php` tokenizes and interprets a bounded expression tree. It never passes formulas to PHP/JavaScript evaluation or shell commands and never fetches external references. Supported functions are **SUM, IF, ROUND, MIN, MAX, AVERAGE, ABS, COUNT, AND, OR, NOT, IFERROR**, with arithmetic, comparisons, concatenation, percentages, rectangular ranges, existing worksheet references and mixed absolute/relative references. Shared formulas are expanded for evaluation and the formula bar. Input length (2048 characters), token count (512), parser nesting (64), dependency depth (256), range size, graph edges and evaluation work are bounded. Cycles, including dependencies in an unselected IF branch, are rejected before persistence.

The PHP engine uses binary double precision for calculations and does not claim full Excel equivalence, coercion semantics or support for the full function language. Numeric input strings keep their original precision in drafts. Display rounding never changes stored user input. Formula results and clean numeric editing text use each original format code. Grouped/fixed/optional decimals, accounting, percentages and quoted currency literals remain supported. Common built-in currency formats and 1900-system date/time formats (`m/d/yy`, `d-mmm-yy`, `h:mm AM/PM`, `h:mm:ss`, `[h]:mm:ss`, etc.) are supported in PHP and previews. Date/time editing remains an unformatted numeric Excel serial. Full conditional formatting, arbitrary localized number-format languages, 1904 dates and pixel-identical accounting/date layout are outside this renderer's scope.

The supplied workbook's four external-reference formulas retain their original cached values, visibly marked with dotted underlines and a global calculation notice. Dependent calculations can therefore depend on old external values. Unsupported original expressions likewise retain flagged source caches; unsupported *new* expressions are rejected. Native Excel, linked workbooks and full recalculation are required for complete Excel compatibility. Named ranges, dynamic arrays, data tables, macros/UDFs, DDE, HYPERLINK/WEBSERVICE and external data retrieval are not executable features of this interpreter.

### Persistence, audit and export

Drafts now optionally include a separate `formulas` map. Existing numeric-only drafts remain readable. Original source expressions never change on disk. Changed source-cell snapshots detect conflicts against subsequent Excel updates. Every formula change appends worksheet, address, original formula, previous formula, new formula, UTC timestamp, revision and editor identity (`web-entry` when no administrator identity is active) to `formulaAudit` in the same atomic draft transaction. Audit history is server-generated, not accepted from the browser. Revision checks prevent silent concurrent overwrites. The existing file lock, atomic replacement and template/refresh transaction locks remain in use.

Exports patch only saved overlays into a fresh master-package copy. When a shared member is modified, that affected shared group is materialized into equivalent ordinary formulas so siblings keep their correct references. Other formula expressions remain intact. Formula-overlay exports include supported recalculated caches; unavailable external/unsupported caches are retained. The obsolete optional calculation chain and its package relationships are removed for formula edits, and Excel is asked to rebuild/recalculate. Numeric-only exports retain the previous package-preserving path and may require Excel to refresh formula caches. Sheet names/order, merged cells, widths/heights, styles/number formats, comments, external links, printer settings and all other untouched parts remain preserved. The adapter preserves opaque workbook features rather than implementing their browser UI. The master XLSX is never opened for writing.

### Template content versus structure

The previous generic `Template changed` error resulted from comparing the complete XLSX SHA-256, so ordinary value/cache changes looked like layout changes. A structural fingerprint now compares worksheet identity/range, cell positions/styles, row and column definitions, merged ranges, names, theme/styles and print settings, while ignoring cell values, expression text and selection/view changes.

When only content changes, current source cells/formulas are inspected in memory, original cell definitions remain on disk, and compatible web overlays remain intact. There is no automatic rewrite of `config/layout.json` or drafts. An old browser page must reload because source-version headers still protect saves against stale content. If the same source coordinate and a saved override both changed, the draft is preserved and a specific conflict is reported. Source-formula cache refreshes alone do not overwrite authorized expression overlays.

Actual structural, formatting or input-definition changes still require `tools/refresh-template.php --plan ...`, review, and `--apply ...`. Formula text and audit history are included in migration checks and verified draft backups. Conflicting formulas block application. Source edits made in Excel remain the responsibility of the local administrator; keep a pre-edit workbook copy. The existing recovery marker and rollback procedure still apply.

### Tests and implementation files

Run the normal PHP/JS syntax checks and:

```powershell
php tests/number-format.php
php tests/formulas.php
php tests/verify.php
php tests/refresh.php
node tests/browser.cjs
node tests/editable-focus.cjs
node tests/formula-browser.cjs
```

Browser tests require Apache, the existing temporary Playwright installation and Microsoft Edge. They refuse to replace active drafts. The formula browser test additionally refuses to replace an existing administrator configuration; it creates a temporary random test credential, tests real HTTP authorization/expiry, then removes it and its own draft. It never installs a production password. All tests use the original workbook without writing to it or isolated temporary workbook copies.

New files: `lib/formula-engine.php`, `lib/formula-edits.php`, `lib/formula-auth.php`, `lib/template-sync.php`, `tools/formula-admin.php`, `tests/formulas.php`, `tests/formula-browser.cjs`, `tests/check-formula-export.php`.

Modified: `lib/app.php`, `api.php`, `index.php`, `assets/app.js`, `assets/app.css`, `lib/render.php`, `lib/template-refresh.php`, `tests/refresh.php`, `tests/number-format.php`, README and test result artifacts. The original workbook, manual input allowlist and generated layout definition are unchanged. Results are in `tests/formula-results.txt`, `tests/formula-browser-results.txt` and the existing regression logs. Desktop Excel recalculation/repair-prompt testing has not been performed; package/XML integrity and formula semantics were verified by automated tests.

## Interface modernization and unlock setup repair

The PALECO navy/green application header now groups branding, reporting period and primary actions. The responsive toolbar retains Zoom and Show Formula Protection. The formula bar stays immediately above the independently scrolling original worksheet and shows the selected reference, raw value/formula and protection badge. Manual inputs remain pale yellow. Workbook notes expand from a compact notification strip; the four external-reference warnings and their RecovBill26 formulas remain available there. Validation and calculation errors remain visible in the notification strip even when notes are collapsed.

The reported setup failure had three causes: no `storage/formula-auth.json` had been initialized, the browser retained its initial configuration status after local setup, and password failures appeared behind the modal. Missing credentials are intentionally not bypassed. Unlock now refreshes server authorization status, provides a setup dialog with a copyable command and Check setup action, and displays password failures inside the password dialog. Invalid/unreadable configuration is distinguished from missing setup. The new CLI status operation reports configuration health without disclosing credentials.

Run once from a private local PowerShell terminal:

```powershell
Set-Location 'C:\xampp\htdocs\genstat'
& 'C:\xampp\php\php.exe' tools\formula-admin.php status
& 'C:\xampp\php\php.exe' tools\formula-admin.php setup
```

Choose your own editor identity and password (12�256 characters), then confirm it. Terminal password input is visible. Only the password hash is stored. In the browser choose **Check setup** (or click **Unlock Formula Editing** again), accept the confirmation, and enter that password. No page reload is needed. The toolbar shows **Formula Editing ACTIVE** while authorized; **Lock Formula Editing** or the existing 10-minute expiry restores protection. Server authorization, rate limiting, CSRF, validation and audit remain mandatory. No production credential was installed by the implementation or tests.

The current checkout contains only the original June 2026 / Jun26 period mapping. This change preserves it and its year-specific external links; it does not introduce additional period-generation behavior. Original master, layout/input definitions, calculation engine, draft conventions and export adapter are unchanged.

Additional checks:

```powershell
& 'C:\xampp\php\php.exe' tests\auth-setup.php
node tests/ui.cjs
```

The isolated PHP test covers missing, invalid, ready and expired authorization states. The Edge integration test opens June 2026, edits/saves B11 with precise raw storage and two-decimal display, checks locked Q11, performs real CLI setup using a temporary random credential, unlocks without reload, checks visible password errors, edits/saves Q11, re-locks, and rejects an unauthorized HTTP formula change. Its downloaded XLSX is checked by `tests/check-ui-export.php` for numeric precision, the authorized formula, all 397 other formula nodes, styles, merges, print settings and external links. Responsive checks cover 1440�1000, 768�1024, 390�844 and 360�640, including both-axis worksheet scrolling and stationary controls. Screenshots: `tests/ui-desktop.png`, `tests/ui-mobile.png`; results: `tests/ui-results.txt`. Browser tests refuse to replace existing administrator configurations or drafts and remove their own temporary data.

Files changed for this UI repair: `index.php`, `assets/app.css`, `assets/app.js`, `lib/formula-auth.php`, `tools/formula-admin.php`, README. Added tests: `tests/auth-setup.php`, `tests/ui.cjs`, `tests/check-ui-export.php`, plus result/screenshot/export artifacts. Existing regression tests are retained. Full desktop Excel recalculation remains unverified; the existing bounded engine and browser rendering limitations described above still apply.

## Smart Excel template auto-refresh

Ordinary Excel saves no longer require the refresh CLI. Reloading the page detects source changes; an open visible page also checks every 10 seconds. A separate update notice offers **Reload** and never replaces unsaved browser edits. Reload explicitly confirms before discarding unsaved edits. Incompatible updates display an administrator warning and keep the last verified worksheet visible; server save/open/export operations are blocked until the source is compatible or reviewed. No draft is deleted, rewritten, incremented, or remapped by automatic refresh.

`lib/template-sync.php` caches verified extracted metadata in Apache-protected `storage/template-auto.json`. The original `genstat.xlsx` remains the only workbook source. Modification time, creation/change time, size and approval-file digest gate processing, with a SHA-256 recheck at least every 10 seconds to catch same-timestamp replacements. For value/cache-only saves, a normalized package signature reuses existing geometry/styles/formula metadata and updates values from worksheet XML and shared strings without rebuilding the layout. Other changes use the existing extractor and refresh conflict checks. The baseline `config/layout.json` stays unchanged. Merge ordering and selection/view changes do not imply coordinate movement.

Changes are classified as values, formatting, formulas and structure (categories can overlap). Supported changed formulas are checked for reference validity, bounded dependency graphs and cycles, including unselected conditional branches. Existing unsupported/external source formulas keep the existing flagged-cache behavior. Newly changed unsupported/external formulas require administrator review. Formula authorization is unchanged; source formulas remain browser-locked by default.

Automatic structural publication is deliberately conservative: compatible column widths, row heights, formatting, print settings and trailing range/cell additions can publish while existing worksheet identity, merge geometry, approved inputs and cell context remain intact. Sheet renames/count changes, changed merge geometry, removed cells, input ownership changes or ambiguous context shifts require review. A source change conflicting with a saved web value or formula also requires review. The existing CLI plan/apply workflow remains available for these cases:

```powershell
& 'C:\xampp\php\php.exe' tools\refresh-template.php --plan storage/refresh-review.json
# Review the plan and resolve conflicts before applying:
& 'C:\xampp\php\php.exe' tools\refresh-template.php --apply storage/refresh-review.json
```

Automatic updates use the existing exclusive template transaction lock, atomic metadata replacement, full ZIP CRC/length and XML validation, source-hash checks around publication, and verified draft backups. Backups under `storage/backups/auto-*` contain prior extracted metadata, exact unchanged drafts, and the newly observed workbook. They preserve existing backups and never write to the master workbook. A prior workbook's binary bytes cannot be recovered retrospectively if Excel has already overwritten it; keep Excel/version-history backups for that purpose. Invalid/incomplete packages and temporary read failures preserve the prior verified metadata and retry after the short check interval. There is no background service; requests trigger checks. Existing interrupted CLI refresh recovery safeguards still apply.

Run the existing tests plus:

```powershell
& 'C:\xampp\php\php.exe' tests\auto-refresh.php
node tests/auto-refresh-browser.cjs
```

The isolated PHP fixtures cover cache reuse, value refresh without baseline rebuild, changed formula protection, hidden-branch cycle rejection, column geometry and formatting updates, incompatible merges, Excel/web conflicts, incomplete saves, exact draft preservation and backups. Edge browser checks cover 10-second polling, unsaved-edit retention, Reload cancellation and administrator warnings. Results are in `tests/auto-refresh-results.txt` and `tests/auto-refresh-browser-results.txt`. Existing source/export, migration, numeric formatting, formula authorization, editable-focus and responsive UI suites were also executed. Browser notification responses are simulated in the polling test; workbook changes are exercised with actual isolated XLSX fixtures in PHP. No tests edit the master workbook.

Changed implementation files: `lib/template-sync.php`, `lib/template-refresh.php`, `lib/app.php`, `api.php`, `index.php`, `assets/app.js`, `assets/app.css`. Updated `tests/refresh.php` for automatic compatible trailing-range expansion and its new helper dependency. Added the two auto-refresh suites and updated this README. Desktop Excel recalculation compatibility remains subject to the calculation-engine limitations documented above.

## Automatic synchronization and B7 correction (current behavior)

This section supersedes the earlier automatic-refresh restrictions and administrator-review messages. Ordinary workbook saves, labels, headings, newly populated cells, formatting and supported formula changes synchronize on page load or **Sync Now**. SHA-256 is checked on every request that loads the workbook; unchanged content reuses verified parsed metadata. Open pages poll every 10 seconds and offer **Reload** when their view is older. Sync Now bypasses the short failed-attempt retry cache. Synchronization never writes to the source workbook or saved draft files.

The B7 error came from a blanket check in `auto_template_compatible`: any changed nonempty protected text during a structural refresh was treated as a moved logical field. B7 is currently blank, unmerged, nonformula and not an approved input in both the original baseline and source. Protected cells can have harmless Excel label changes. The blanket rule has been replaced by field reconciliation in `lib/sync-mapping.php`. Pre-implementation findings are recorded in `docs-sync-analysis.md`.

Approved inputs retain their comment identities and are reconciled using unique unchanged row/column labels and surrounding context. Inserted rows and columns can move those inputs safely. Deleted, duplicate or ambiguous identities, covered merge anchors and conflicting Excel/web values produce a **Conflict** identifying the worksheet and cell. Correct the workbook and select **Sync Now**; approval and CLI refresh are not prerequisites. Unreadable/incomplete packages produce **Error** and retain the last verified layout. The synchronization strip shows one current state, workbook filename and last successful synchronization time; operational save/export messages remain separate. Formula validation is bounded and cannot establish full Excel equivalence.

Draft reconciliation is a projection when opening/exporting: historical coordinate maps translate a saved field to its verified current coordinate. Draft bytes, revision, saved timestamp and audit history remain untouched until the user explicitly saves again through the existing endpoint. A normal save writes the current coordinates using the existing revision/authorization rules. Backups contain the exact previous layout and drafts, plus the observed source workbook; they are never replaced. A moved saved user formula is conservatively blocked because moving its owning cell alone cannot safely rewrite its expression. This is an actionable conflict, not an approval workflow.

New worksheets are accepted and accessible through the **Worksheet** selector. Added dependency sheets are read-only in the browser; approved report entry and controlled formula unlocking remain on the existing primary reporting sheet. Cross-sheet source formulas and all workbook parts are retained in export. Sheet-specific comment relationships are read separately. Existing worksheet identities remain stable even when Excel reorders tabs. Removing/renaming an existing sheet requires restoring a recognizable identity before sync can succeed.

The actual checkout has one source `genstat.xlsx` and the existing June 2026 report mapping. There is no yearly-file registry or other yearly workbook here. Synchronization never searches another year's workbook or accepts an arbitrary browser-supplied file path; requests for other periods remain rejected. No new period data or permissions were invented. Existing RecovBill external links retain their original caches and limitations. A previously interrupted CLI transaction still requires restoration of its recorded backup before writes resume; the optional CLI tools remain available for recovery.

Validation executed for this change includes the existing PHP format, formula, source/export, refresh, authorization and Edge entry/focus/formula/UI suites. Added `tests/sync-reconciliation.php` exercises B7 edits, label changes, row/column insertion, draft projection, new sheets, cross-sheet formulas, deleted/ambiguous inputs, unavailable workbooks, correction/retry, real concurrent PHP processes, application read/export and rejection of another reporting year. `tests/sync-workbook-browser.cjs` serves a temporary isolated workbook through PHP and checks the moved input's two-decimal preview, worksheet switching, protected cross-sheet formulas and unchanged draft bytes. `tests/auto-refresh-browser.cjs` checks polling, Sync Now, Reload cancellation, one conflict notification, and unsaved browser preservation. Fixture tests do not change production workbook data.

Local XAMPP checks:

1. Start Apache and open `http://localhost/genstat/`.
2. Save a harmless B7/heading/value change in your own Excel template, then reload the page or choose **Sync Now** followed by **Reload**. Confirm the updated text and synchronization timestamp.
3. Leave a browser entry unsaved, save another harmless Excel change and choose Sync Now. The entry stays in place; Reload asks before discarding it.
4. Test row/column moves and collisions using disposable workbook copies, or run the isolated suites below. If a conflict appears, correct the workbook and retry Sync Now.

```powershell
Set-Location 'C:\xampp\htdocs\genstat'
& 'C:\xampp\php\php.exe' tests\auto-refresh.php
& 'C:\xampp\php\php.exe' tests\sync-reconciliation.php
node tests/auto-refresh-browser.cjs
node tests/sync-workbook-browser.cjs
```

Changed implementation files: `lib/template-sync.php`, `lib/app.php`, `tools/build-layout.php`, `api.php`, `index.php`, `assets/app.js`, `assets/app.css`. Added `lib/sync-mapping.php`, investigation notes and the reconciliation/workbook-browser suites; updated auto-refresh, source-layout and refresh tests for derived coordinate maps and helper dependencies. Results are saved under `tests/*-results.txt`. Existing formula administrator setup is still required solely to authorize source-formula editing; it is unrelated to synchronization and has not been bypassed. Desktop Excel recalculation was not tested.

## Permanent formula indicators

The formula-protection visibility checkbox and its local-storage toggle have been removed. Formula cells always show an `fx` marker and descriptive tooltip; a selected formula shows a lock while protected and a pencil during authorized editing. Old saved visibility preferences have no effect. The toolbar always includes Worksheet, Zoom, Unlock/Lock Formula Editing, and the formula editing status, including on mobile. Existing authorization, server enforcement, keyboard/paste blocking, workbook layout, numeric display and export behavior are unchanged.

Modified: `index.php`, `assets/app.js`, `assets/app.css`, browser/formula regression assertions and this README. Executed checks: PHP/JavaScript syntax, browser entry/protection, authorized formula editing and expiry, responsive UI, editable focus/precision, number formatting and workbook/export integrity. Results remain in the corresponding `tests/*-results.txt` files.

## Compact two-row header

The current interface uses a 96px desktop header: compact PALECO GenStat branding and icon-only Open, Save, Export, Unlock/Lock, and Zoom controls above a consolidated synchronization/status bar. Buttons have accessible labels, native titles and custom tooltips for mouse hover/keyboard focus. Zoom opens a compact selector, closes on selection/outside click/Escape, and retains the existing scale handler. Lock icons, labels and tooltips follow the authorized session state.

The second row retains synchronization state, workbook filename, last successful sync time, visible unsaved/save/export/error messages, icon-only Sync Now, the update Reload action, and an expandable warning badge with all existing details. Passive source-load text is no longer a separate visible row. Errors can expand the status area rather than being truncated. The Reporting Period dropdown is replaced by the same hidden period value used in requests; the backend period rules are unchanged. Worksheet tabs below the header use the same sheet query/loading path and confirm before discarding browser edits. Redundant worksheet headings/dropdowns and the duplicate footer tab are removed. The formula bar and original worksheet renderer are retained.

Changed application files: `index.php`, `assets/app.js`, `assets/app.css`. No synchronization engine, workbook data, input rules or PHP endpoints changed. Updated browser tests target the icon controls, zoom popup and worksheet tabs. Executed checks include PHP/JavaScript syntax, normal browser entry/open/save/export, authorized formula editing/expiry, 90�105px desktop header measurement, keyboard tooltip visibility, desktop/tablet/mobile bounds, sync notifications/recovery, tab navigation, numeric precision and XLSX formula/format integrity. Results remain in `tests/*-results.txt`; screenshots are `tests/ui-desktop.png` and `tests/ui-mobile.png`.

## Worksheet tabs restored to the bottom

The existing `.worksheet-tabs` navigation now sits immediately after the scrollable worksheet and before the selection footer. The compact two-row header and formula bar are retained. The same links, active-sheet styling and unsaved-change handlers remain in use. Tabs do not shrink, scroll horizontally when needed, and stay visible independently of worksheet scrolling. No workbook processing or synchronization logic changed.

Validated with PHP syntax checking, the responsive UI suite, and the isolated workbook browser suite covering bottom placement on desktop/mobile, visibility during worksheet scrolling, many-tab horizontal scrolling/keyboard access, active sheet navigation, unsaved-edit confirmation, synchronization recovery and protected cross-sheet formulas.

## Official logo asset and unified footer

Place the supplied PALECO image at `assets/images/paleco-logo.png`. The directory has been created; the header is ready to display that file automatically at 38px (36px on mobile), with `object-fit: contain` and the existing PALECO GenStat text. The original icon remains a fallback while the user supplies the file; no official logo has been generated or substituted. Actual logo appearance remains unverified until the image is provided.

The bottom navigation and selection information share one 35px row. Existing tabs and active-sheet styling remain, only the tab section scrolls horizontally, the selected coordinate appears beside it, and the preservation message stays on the right. Detailed cell protection information remains in the selected reference's tooltip and the existing formula bar. No workbook processing or switching handlers changed. Verified with PHP/JavaScript syntax checks, responsive UI tests, and real isolated browser checks for the unified footer, selected coordinate, tab scrolling, unsaved-change handling, synchronization recovery and formula protection.

## Ctrl + Mouse Wheel worksheet zoom

Ctrl + Wheel over worksheet cells changes zoom by 10 percentage points (up increases, down decreases), clamped to 50�200%. The existing transform and worksheet-size wrapper are updated through one shared zoom function used by the toolbar. The toolbar now includes the full range while retaining its previous 125% option. The current percentage is stored in sessionStorage, preserving zoom during worksheet switching/reloads within the same browser tab. An unavailable storage API does not prevent zooming.

Only cell targets inside the visible grid are intercepted, using a viewport wheel listener registered with `passive: false`. Headers, controls, empty area outside the sheet, ordinary wheel scrolling and open dialogs are ignored. Browser zoom defaults are prevented only for handled grid events, including at the limits. Updates are batched with requestAnimationFrame; scroll offsets are adjusted to keep the same logical worksheet point beneath the cursor, subject to scroll limits at sheet edges. The listener is removed on pagehide, pending work is flushed, and it is restored for back-forward cache navigation.

Modified `assets/app.js` and the toolbar options in `index.php`; no workbook processing, formulas, synchronization or export logic changed. Added `tests/zoom-browser.cjs` and a tab-persistence assertion to the isolated workbook browser test. Real Edge checks cover trusted Ctrl+Wheel direction/anchoring, event cancellation boundaries, dialogs, rapid bursts, bounds, toolbar synchronization, session persistence and listener lifecycle. Existing browser/UI and isolated workbook regression suites were also executed. See `tests/zoom-browser-results.txt` and the corresponding regression logs.

## Adjacent synchronization and draft timestamps

The status metadata now displays `genstat.xlsx � Last successful sync: [timestamp] | Draft saved � [timestamp]`. The draft timestamp comes from the existing save/open payload's savedAt value; synchronization polling updates only the sync segment. Until a draft exists, the save segment and separator stay hidden. The old right-side operation status element was relocated into the synchronization summary, with save confirmation available to assistive technology and errors/unsaved messages remaining visible. Small, subtle metadata stays on one line on desktop and wraps on narrow screens.

Only frontend markup, styles and display bindings changed; backend logic is unchanged. The isolated workbook Edge test passed real draft-save timestamp updates, reopen display, sync retention, desktop adjacency and mobile wrapping. PHP/JavaScript syntax checks passed. The production UI suite was skipped because its guard detected existing draft/administrator data; those files were not modified.

## Formula reference highlighting

The formula bar now keeps only fx, the flexible formula/value input, protection status and a small cross-sheet/inspection indicator; the redundant selected coordinate remains available in the bottom footer. Selecting a formula uses its source expression (expanded shared formulas included), with saved formula overlays taking precedence, rather than interpreting the calculated display.

`assets/formula-references.js` provides a bounded, display-only A1 tokenizer. It consumes escaped double-quoted strings, quoted worksheet names, identifiers, numeric literals, structured-reference brackets and external workbook qualifiers before extracting cell/range tokens. This avoids finding fake A1 references inside strings, names, table columns or workbook names. It recognizes single, absolute/mixed, rectangular, same-sheet and cross-sheet references independent of the function name, including VLOOKUP/INDEX/MATCH expressions that the existing calculation engine may not evaluate. Inspection does not claim formula validity or extend calculation support. Malformed strings/ranges/parentheses show a small unavailable indicator without changing the formula.

`assets/reference-highlights.js` creates one pointer-transparent colored overlay per distinct visible local reference, clips large ranges to the rendered row/column geometry, caches inspected expressions, and shares the existing worksheet transform. Selection updates only the overlays; it does not repaint/rebuild cell nodes, alter cell style attributes or store colors in drafts/exports. Active formula selection remains above the overlays. Other-sheet/external references are listed in a subtle tooltip indicator; selecting them never switches worksheets. Automatic cross-sheet range navigation, named-range resolution, structured-table range resolution, full-row/column references, and dynamic INDIRECT/OFFSET result resolution are not implemented. Direct A1 references within those function arguments can still be identified. Three-dimensional references are indicated rather than painted as local cells.

Modified `index.php`, `assets/app.js`, `assets/app.css` and the isolated browser fixture/test. Added the two display scripts and `tests/formula-references.cjs`. No calculation, synchronization, authorization, draft-storage or export logic changed. Checks passed: tokenizer cases for quoted strings/names/external books/nested functions/large ranges/invalid expressions; isolated Edge selection tests for locked source formulas, blank cells, multiple colors, clearing, cross-sheet indicators, unchanged cell styles and zoom alignment; existing formatting, formula and workbook/export-integrity PHP suites; PHP/JavaScript syntax. Visual-only formulas in the browser fixture are supplied by intercepted read responses, never written into the source workbook. Original cross-sheet expressions are also checked against real fixture metadata. Results: `tests/formula-references-results.txt`, `tests/sync-workbook-browser-results.txt` and existing regression logs; screenshot: `tests/formula-highlights.png`.
