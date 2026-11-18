# Brooke's Point mapping inspection and correction

Later authorized policy: [Row-level SQL formula replacement](row-sql-formula-replacement.md) permits F18 only through a saved, tested row opt-in and explicit reviewed confirmation. The root-cause evidence and default protection below remain valid.

## Confirmed root cause (before correction)

The clean reference workbook SHA-256 is `752c1ad06bd770ea749ef7925a49adbc725ee81b3a7dd6342f5b1585253c3dce`.
Direct OOXML/layout inspection found:

- F8 is `Brooke's`; F9 is `Point`.
- The previous `qs_rows` implementation already joined those two strings into `Brooke's Point`.
- F18 contains the ordinary Excel formula **`3011664+767072`**, cached value **3778736**, style 93, number format `#,##0.00`.
- F18 is not a covered merged placeholder. Its formula classification/protection is correct, not stale metadata.
- The reference workbook is rebuilt into metadata per request, not loaded from an outdated area cache.
- `area_master` contains AreaID 12 / `Brooke's Pt.`. Existing `area_mapping` rows retain the distinct source/reporting/adjustment IDs.
- `Total KWH Sold` therefore has 15 recognized area columns but only **14 writable non-formula inputs** in this source. F18 is excluded by formula protection.
- No persisted SQL configurations were present during inspection. A pasted query in an existing browser editor is not persisted configuration and was not overwritten.

The requested assertion that F18 is non-formula conflicts with the actual source. This update will preserve its formula and protect it from SQL application. It cannot safely turn F18 into a fifteenth writable input without a separately authorized source/formula policy change. The mapping error must be explained precisely, not hidden by automatically excluding AreaID 12.

## Implemented correction

- Added `report_area_headers`, deriving the header band from the current report start and first Particulars data label. It combines fragments within the same column, supports embedded line breaks, skips covered merge placeholders, and stops before the TOTAL group. It does not edit workbook text/styles. `Brooke's` + `Point` resolves to `Brooke's Point`; `Araceli/Dumaran` and `Cuyo/Magsaysay` remain distinct combined headings.
- Added explicit Brooke's Point/Pt./Brookes aliases, case/whitespace normalization and typographic-apostrophe support. No fuzzy, substring or prefix matching is used. The AreaID 12 suggestion requires both the expected master-area name and an existing reporting mapping (`GenstatAreaID = 12`). Source `AreaID`, reporting `GenstatAreaID` and adjustment `kwhdiffAreaID` are not collapsed.
- SQL row metadata now exposes protected cells separately from writable targets. A genuine non-formula Brooke's Point input defaults to AreaID 12 only when its existing binding is absent. Saved nonempty choices, SQL text, query keys, result columns, SACode choices, other area assignments and exclusions are not rewritten.
- The modal shows the recognized F18/AreaID 12 association in Excel column order, together with its protected formula. It does not create an editable dropdown or submit F18 as a writable binding. Other area mappings keep their ordering.
- Preview diagnostics now identify the protected destination and source formula instead of giving the misleading generic “map or exclude” advice for AreaID 12. Test Query and Review Proposed Changes show a formatted protected-target comparison. This remains an error, blocks saving/applying that invalid mapping, and does not automatically exclude AreaID 12.
- Review tables now include a Difference column. The supplied figures are shown as **3,778,736.00 → 3,776,446.00; difference −2,290.00**. Current values come from the live calculation snapshot when supplied, including existing draft/formula overlays. No value is overwritten during testing, saving or review.
- Exact alias/column conflicts are rejected; AreaID 12 cannot be silently shifted into a different area's column. Duplicate detection, missing-area checks, CSRF, source/revision validation and explicit confirmation are retained.

## Area coverage verified

The following established test bindings were used to verify identity matching with results in reverse order. They were not installed as new production mappings, and combined-area assignments were not inferred or changed in saved configuration.

| Column | Recognized source heading | Fixture AreaID | Current Total KWH Sold eligibility |
| --- | --- | --- | --- |
| B | Main | 1 | Writable B18 |
| C | Aborlan | 8 | Writable C18 |
| D | Narra | 9 | Writable D18 |
| E | Quezon | 10 | Writable E18 |
| F | Brooke's Point | 12 | **Protected formula F18** |
| G | Roxas | 2 | Writable G18 |
| H | Taytay | 5 | Writable H18 |
| I | Balabac | 15 | Writable I18 |
| J | Araceli/Dumaran | 4 | Writable J18 |
| K | San Vicente | 3 | Writable K18 |
| L | El Nido | 6 | Writable L18 |
| M | Cagayancillo | 19 | Writable M18 |
| N | Cuyo/Magsaysay | 16 | Writable N18 |
| O | Agutaya | 18 | Writable O18 |
| P | Rizal | 11 | Writable P18 |

All 15 results map uniquely in an **in-memory alternate template** where F18 actually is a constant input. The unchanged real source maps 14 writable destinations and reports one protected destination. This distinction is intentional: making the actual F18 writable would contradict formula preservation.

## Tests and preservation

- `php tests/brookes-point.php`: all 15 headings, explicit aliases, no fuzzy matching, original F18 formula/merge status, correct protected error/comparison, forged F18 mapping rejection, no automatic exclusion, 15 valid mappings on a genuine constant-input fixture, duplicate detection, split/single-cell-line-break headers, and untouched layout/source checksum all pass. A synthetic November worksheet moves the sold row and Brooke's Point column; the helper resolves **G20**, proving it does not hardcode F18 or July.
- `node tests/brookes-point-browser.cjs`: live 15-row SELECT fixture, automatic F11 → AreaID 12 suggestion, 14 writable inputs plus the protected F18 association, exact current/proposed/difference, disabled save/apply and unchanged F18 all pass. The fixture is read-only, is not the approved genstat_kwh calculation, and is not saved.
- `node tests/brookes-point-review-browser.cjs`: a temporary reserved saved-query fixture produces the exact protected F18 difference in **Review Proposed Changes**. Checking confirmation cannot enable applying a formula target. Temporary configuration/history was cleaned up; source and report drafts were not written.
- Existing query-settings PHP, parser, complete browser lifecycle, base-report/formula/cache, hidden-row and compact responsive tests pass. Saved-query reuse, history, editing/protection and review/apply separation remain functional.
- PHP/JS syntax, local Tailwind compilation and Git whitespace checks pass. The protected comparison screenshot was visually reviewed.

The source still has **402 worksheet formulas**, **312 report formulas**, **96 database-input classifications**, and **312 cached-result matches** with no new result differences. The source hash is unchanged. No source/area/mapping records or business SQL were changed. The temporary lifecycle fixtures use reserved identifiers, refuse to run when configuration records already exist, and remove only their own records. Existing drafts/manual values are preserved.

## Files

Modified: `lib/query-settings.php`, `query-settings-api.php`, `assets/sql-settings.js`, `partials/sql-settings.php`, generated `assets/tailwind.css`.

Created: `lib/report-areas.php`, `tools/inspect-brookes-point.php`, `tests/brookes-point.php`, `tests/brookes-point-browser.cjs`, `tests/brookes-point-review-fixture.php`, `tests/brookes-point-review-browser.cjs`, and `docs/brookes-point-mapping.md`.

## Remaining limitations

F18 remains protected by `=3011664+767072`. There is no correction to its eligibility rule that both makes it a SQL input and preserves that formula. Fifteen writable sold-area inputs and successful saving/application including F18 require an independently authorized source/formula-policy change; no such change was made. The current implementation instead recognizes and explains the valid formula exclusion, as requested for legitimately excluded cells.

No persisted approved genstat_kwh query was available during inspection, so its production arithmetic was not rerun or certified. Supplied proposed F18 values were reproduced with explicitly identified test fixtures, without changing or substituting the approved calculation.

The clean base workbook still only supplies Jul26. Other-month/column behavior is covered with in-memory fixtures, without fabricating new worksheets. The annual export implementation was not changed; the focused base report's export adapter remains the previously documented future phase. Source hash/formula/style preservation was verified; no new export workflow or source-writing operation was introduced.

No database tables, authentication features, Git branches, commits or pushes were added.
