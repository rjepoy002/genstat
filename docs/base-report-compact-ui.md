# GENSTAT compact table and hidden rows

Updated 13 November 2026, Asia/Manila. Scope: the focused base-report presentation only. The annual workbook editor, formula engine, source workbook, classifications and draft persistence implementation were not changed.

## Hidden rows

| Excel row | Source description |
| --- | --- |
| 19 | Total KWH Sold (w/o BILL ADJ) |
| 34 | Dist. System Loss In KWH - ADJ |
| 37 | Rate of Dist. System - ADJ |
| 46 | System Loss in KWH - ADJ |
| 47 | Rate of System Loss -ADJ |

`lib/base-report-view.php` centralizes these exact labels and normalizes whitespace and ASCII case before comparing the Particulars text. Matching report rows receive the HTML `hidden` attribute; all cells remain in the DOM, source layout, metadata and calculation responses. Similar descriptions, including `Total KWH Purchased - ADJ` and both unadjusted distribution-loss/rate rows, remain visible. No workbook rows were deleted.

The source still contains all 402 worksheet formulas, including 312 in A8:S47. All 96 database-input classifications remain, including seven in the hidden row 19. Every source value, formula expression and formula attribute was checked against the source extraction. The workbook SHA-256 remains `752c1ad06bd770ea749ef7925a49adbc725ee81b3a7dd6342f5b1585253c3dce`. All 312 report formula results still match their source caches, with no new errors or differences.

## Compact presentation and fitting

The existing table now uses navy two-row headers, subtle 1px gridlines, Arial tabular numerals, right-aligned numeric cells, restrained total separators and pale blue database-input cells. Original cyan Particulars indicators remain. Body rows target 22px, headers 26px each, and ordinary cells use 2px vertical/5px horizontal padding. Desktop text starts at 11px. Particulars starts at 210px, area columns at 94px, and total/comparison columns at 126px. Numeric columns widen based on measured formatted text, including after recalculation, so fitting never truncates values. Labels can ellipsize; complete descriptions remain in tooltips and accessible labels.

Only completely empty rows with no formula and no intersecting merge are compressed to 5px separators. Their model coordinates remain unchanged. In particular, merged row 45 is not collapsed and supporting content is retained.

The fit routine measures the actual rendered table and available scroll viewport after the page controls are laid out. It applies **table-only CSS zoom**, bounded between 10/11 and 1 on desktop, and recomputes on container/window resize. This keeps effective desktop table text at least 10px and scales both axes equally. Browser zoom is untouched. At widths up to 700px, scaling is disabled and table text is 12px; horizontal and vertical scrolling remain available. The focused report had no manual zoom control to preserve; the separate annual editor's controls remain unchanged.

Native sticky headers, the merged Taytay header, two-line area headers and sticky Particulars stay in the table's coordinate system. Workbook notes expand as a bounded overlay, avoiding a tall extra toolbar.

## Verification

Browser tests ran in headless Microsoft Edge. Screenshots were reviewed at 1920×1080, 1280×720 and 390×844.

| Viewport | Effective table font | Table height | Available scroll viewport height | Result |
| --- | --- | --- | --- | --- |
| 1920×1080 | 10.26px | 598.64px | 969px | All visible rows and columns fit |
| 1600×900 | 10px | 583.5px | 789px | All visible rows fit; horizontal scrolling |
| 1366×768 | 10px | 583.5px | 657px | All visible rows fit; horizontal scrolling |
| 1280×720 | 10px | 583.5px | 609px | All visible rows fit; horizontal scrolling |
| 390×844 | 12px | 717px | 705px | Readable mobile table; horizontal/vertical scrolling |

The browser checks confirm exactly the five specified rows are hidden, all formula/classification metadata remains, no numeric display string overflows its cell, the document itself does not overflow horizontally, sticky first/second header rows align, Particulars stays pinned, and the final comparison column is accessible. Resizing an already loaded mobile page back to desktop recomputes fitting successfully. No JavaScript errors occurred.

Commands that passed:

- `php tests/base-report-view.php`: exact label resolution, normalization, nonmatching labels, complete source preservation, classifications and hidden dependency chain. Editing B24 increases hidden B19 by 1, reduces hidden B34/B46 by 1, and updates hidden B47's rate accordingly.
- `php tests/base-report.php`: all prior source/formula/cache/input checks, with zero result differences and zero report calculation errors.
- `node tests/base-report-compact.cjs`: all four requested desktop viewports, mobile, resize, numeric clipping and sticky alignment.
- `node tests/base-report-browser.cjs`: existing editing, recalculation, selection/formula bar, server formula protection, confirmed unlocking, draft save/reload, stale revision rejection and scrolling checks. Temporary test drafts were removed; no existing drafts were overwritten.
- PHP syntax checks for the modified page/new view helper, JS syntax check, and `git diff --check`.

The previously documented unavailable external recovered-sales workbook and off-table source `#REF!` issues remain unchanged. Small windows may require both scroll directions; fitting stops at the readable minimum. Screenshots are ignored test artifacts under `tests/base-report-compact-<width>.png`.

## Files changed in this update

Modified: `base-report.php`, `assets/base-report.css`, `assets/base-report.js`, and the presentation-status note in `docs/base-report-implementation.md`.

Created: `lib/base-report-view.php`, `tests/base-report-view.php`, `tests/base-report-compact.cjs`, and `docs/base-report-compact-ui.md`.

No database integration, SQL/query changes, schema changes, area mapping changes, Git branch changes or pushes were made.
