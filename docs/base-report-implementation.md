# GENSTAT base reporting table

Presentation follow-up: [Compact table and hidden rows](base-report-compact-ui.md) supersedes the original source-width/style presentation described below. Formula, value and classification preservation remain unchanged.

SQL settings follow-up: [SQL Query Settings](sql-query-settings.md) documents the later, explicit introduction of read-only query previews, configuration tables and reviewed input application. Earlier statements below about absence of database integration describe the original implementation phase.

Implemented 13 November 2026 (Asia/Manila). Open `http://localhost/genstat/` for the focused report. `?legacy=1` retains the existing annual workbook editor; existing worksheet URLs and API workflows are unchanged.

## Source and architecture

The authoritative workbook was located at `C:\Users\r.g.padrones.paleco\Documents\ICT\Projects\PROJECT_genstat\GENSTAT_BASE.xlsx`. Its byte-identical clean copy is `excel-files/reference/GENSTAT_BASE.xlsx`, SHA-256 `752c1ad06bd770ea749ef7925a49adbc725ee81b3a7dd6342f5b1585253c3dce`. Neither copy is an editing target. The annual active workbook, incoming files and existing monthly drafts are not used or changed by the focused report.

Reuses the existing OOXML extraction (`build_layout`, ZIP/XML helpers), shared-formula expansion, `FormulaEngine`, style/color and number-format rendering, merge ownership and atomic JSON file writing. No library was installed. The focused report uses a native HTML table to provide merged two-row sticky headers and a sticky Particulars column without scaling or duplicating frozen cell contents. Original annual rendering/editing/synchronization remains available through the existing editor link.

The source has one worksheet, `Jul26`, with used range `A1:AG99`. The displayed range is **Jul26!A8:S47**, identified from `Particulars`, the comparison header `Last Month` and final `Rate of System Loss -ADJ` label, rather than treating the used range as the report. Both header rows, every separator and repeated/adjusted loss row are retained. Original area columns B:P, totals Q:R and previous-month comparison S are retained in order. T:AG checks/support and later summary sections are excluded from display. All source cells and all 402 expanded formulas remain in the calculation layout.

## Cell policy and counts

- 312 formulas in the displayed range, with original raw expressions, attributes and shared-formula definitions unchanged.
- 96 future database-input cells.
- 122 editable non-formula cells total: 96 database inputs and 26 other numeric constants.
- Labels, headers, separator blanks, covered merge placeholders and formula cells are protected. Empty area input slots in designated database rows remain editable.

Classification combines actual source blue fills with data-entry position and absence of formulas. Source cyan labels designate six explicit input rows: NPC/PPGI/DELTA P/DMCI, Total KWH Sold, Disconnected Consumers, Bill Adjustment, Coop Consumption and TX/SS Losses. Only their non-formula **area** cells B:P qualify through row designation; Q:S totals/historical comparisons are excluded from that inference. Individually blue non-formula data cells also qualify, including seven teal cells in row 19. Thus F18 remains a formula, and numeric totals such as Q25 are manual rather than database fields. This is application metadata only, with no database field mapping.

Each display coordinate, including implicit blanks, has worksheet/reference/row/column, original value/formula, effective shared formula, style index, number format, classification, editability and database reservation metadata. Source styles resolve to full fonts/fills/borders/alignment in the server layout. Original cells are not recolored for classification.

Edits recalculate through the existing engine using raw values; formatted strings are only for display. Formula edits require explicit per-cell confirmation and server session grants, then use the formula bar. The focused report stores overlays separately at `storage/base-report-<source-hash>.json`, with CSRF/source-hash validation, a revision check, file lock and atomic write. Save Draft never writes Excel. The report does not add Excel synchronization/export features; the existing annual editor keeps its existing features.

## Validation and limitations

`php tests/base-report.php` passes source-driven boundaries; preservation of every source cell's value, style, raw formula and formula attributes; row/column/merge preservation; database eligibility; formula protection; and input dependency tests. **All 312 displayed formula results match source caches** within `max(1e-7, abs(cached)*1e-12)`. Editing B11 by +100 increases Q11 and B15 by +100. No displayed formula errors or cache differences were found.

Two displayed formulas use the unavailable external workbook `[2]RecovBill26`: B22 (`=+[2]RecovBill26!B44`) and D22 (`=+[2]RecovBill26!B45+[2]RecovBill26!B46`). Their original cached values are retained with visible warning details. Their external sources cannot be freshly recalculated or independently validated without that linked workbook. Dependent local formulas calculate using those cached inputs; none were replaced with constants in the workbook.

Whole-sheet evaluation additionally reports external dependency T18, original unsupported `#REF!` formulas AC31/AC38 and downstream `#REF!` errors AC33/AC41, all outside the displayed report. These pre-existing expressions are retained. The full details are in `base-report-validation.json`. No formula/business-logic repair was attempted.

`node tests/base-report-browser.cjs` passes in headless Edge at desktop 1440×1000 and mobile 390×844: exact range/counts, parentheses negatives, input recalculation, formula expression display, protected-cell rejection at the server, confirmed formula unlock, draft save/reload, stale revision rejection, horizontal overflow within the viewport, sticky headers/Particulars, and no JavaScript errors. The temporary test draft was removed; no existing drafts were overwritten. Screenshots were visually reviewed at `tests/base-report-desktop.png` and `tests/base-report-mobile.png` (ignored runtime artifacts). Mobile preserves source widths, so viewing the wide table requires horizontal scrolling.

Regression checks passed: `php tests/editing-policy.php`, `node tests/formula-references.cjs`, `node tests/edit-history.cjs`, PHP syntax checks for the changed entry points/library, and JS syntax check. Annual workbook save/sync/resync workflows were not exercised because they can mutate existing workbook state. Their implementation was not changed.

**No database connections, SQL, schema changes, mappings, autofill or synchronization jobs were introduced or executed. No Git branch changes or pushes were made.**

## Changed/created files

Modified: `index.php` (default report routing only).

Created:

- `base-report.php` — focused report page and protected calculation/draft endpoint.
- `lib/base-report.php` — source discovery, classification, validation and calculation adapters.
- `assets/base-report.css`, `assets/base-report.js` — sticky table, selection, editing and draft UI.
- `excel-files/reference/GENSTAT_BASE.xlsx` — untouched source copy.
- `tools/inspect-base.php` — read-only workbook inspection CLI.
- `tests/base-report.php`, `tests/base-report-browser.cjs` — preservation/calculation and browser checks.
- `docs/base-report-validation.json` — machine-readable counts, comparisons, warnings/errors and classifications.
- `docs/base-report-database-inputs.csv` — every database-input reference, row description, area, original value, fill and qualification reason.
- `docs/base-report-implementation.md` — this implementation/validation record.
