# GENSTAT Report Formula visibility inspection

Inspected the compact report and live July 2026 configuration on 2026-11-18. The formula editor is intentionally scoped to **Total KWH Sold (row 18)**. **Configured** means the selected row has an enabled saved SQL configuration. Bill Adjustment is configured to take its values directly from `KWH_Diff`, so its formula editor is hidden by design.

## Root cause and row selection flow

1. The report includes SQL Settings at [base-report.php:117](../base-report.php#L117). The selector is populated by `loadMetadata()` at [assets/sql-settings.js:52](../assets/sql-settings.js#L52). The metadata endpoint reads saved definitions and mappings, calculates status from the record's enabled flag, and returns the template rows and configurations at [query-settings-api.php:12–21](../query-settings-api.php#L12-L21). `qs_load()` joins the definition and mapping tables and indexes the results by template row key at [lib/query-settings.php:107–110](../lib/query-settings.php#L107-L110).
2. `populateRow()` finds the selected row and loads the matching record's SQL text, result columns, bindings, and formula JSON. It assigns the formula expression and checkbox from that row's metadata, then runs `$('sql-calculation-section').hidden=current.row!==18` at [assets/sql-settings.js:31–36](../assets/sql-settings.js#L31-L36). The status badge is updated independently at [assets/sql-settings.js:6](../assets/sql-settings.js#L6) and [assets/sql-settings.js:49](../assets/sql-settings.js#L49).
3. The formula section stays in the DOM. The dialog's `[hidden]` rule applies `display:none!important` at [assets/tailwind.input.css:11](../assets/tailwind.input.css#L11). Other eligible rows retain the generic SQL Result Column Settings above it. The formula section itself says “Total KWH Sold / Target row 18” at [partials/sql-settings.php:24–34](../partials/sql-settings.php#L24-L34).
4. Changing rows invokes `populateRow()` again. Reopening fetches fresh metadata and initially selects the first eligible row. Loading a row clears its predecessor's tested result, formula preview state, and Apply & Save authorization at [assets/sql-settings.js:31–36, 49–57](../assets/sql-settings.js#L31-L57).

## Live configuration comparison

| Field | Total KWH Sold | e.) Bill Adjustment (Previous months) |
| --- | --- | --- |
| Workbook row and record key | A18, `genstat-base/Jul26/A18` | A25, `genstat-base/Jul26/A25` |
| Canonical row ID | `genstat_total_kwh_sold` | `genstat_e_bill_adjustment_previous_months` |
| Saved SQL definition | `sales_kwh`. Uses `:ReportMonth` and returns sales, adjustments, cancellations, late bills, and `TotalKWhSold`. | `adj_kwh`. Uses `:ReportMonth` and returns `KWH_Diff` alongside other summary columns. |
| Area mapping | `AreaID`, 15 saved cell bindings, `AreaName` for auto-match, no configured SACode column | Same mapping fields, with 15 bindings for row 25 |
| Saved value column | `TotalKWhSold` | `KWH_Diff` |
| Saved formula settings | Enabled. Target `total_kwh_sold`. Expression `KWH_Diff + Sales_KWH + Late_Bills_KWH - Cancelled_KWH`. | Disabled with an empty expression. The stored `total_kwh_sold` target is the default metadata value. |
| Value source | The enabled expression evaluates the returned numeric columns. `TotalKWhSold` is the configured value column. | Directly uses `KWH_Diff`. The SQL also returns `TotalKWhSold`, but the saved mapping selects `KWH_Diff`. |
| Browser state | Configured, formula section visible, saved expression and enabled checkbox shown. | Configured, formula section hidden, expression cleared and checkbox unchecked. SQL and `KWH_Diff` still load. |

Both stored SQL definitions were executed through the existing read-only reporting reader for `202607`. Each returned 15 rows without truncation. Both returned their saved value column, and A18 returned all four input columns used by its expression. The backend checks required result columns and chooses between the computed expression and the saved result column at [lib/query-settings.php:140–148](../lib/query-settings.php#L140-L148). The formula evaluator exits when the feature is disabled at [lib/report-expression.php:53–58](../lib/report-expression.php#L53-L58). The default formula target comes from [lib/query-settings.php:232–239](../lib/query-settings.php#L232-L239).

## Intended scope and Review & Apply

The feature documentation explicitly limits report expressions to Total KWH Sold at [docs/query-calculation-settings.md:4](query-calculation-settings.md#L4). The API rejects enabled formulas for rows other than 18 at [query-settings-api.php:22–26](../query-settings-api.php#L22-L26), and normalization requires row 18 and target `total_kwh_sold` at [lib/query-settings.php:111–115](../lib/query-settings.php#L111-L115). Workbook formula overwrite authorization is a separate rule at [lib/sql-replacements.php:19](../lib/sql-replacements.php#L19).

Compared the working tree with commit `d234b8a`. That version includes the Review & Apply panel and already runs the same row-18 visibility check and enabled-formula API restriction. The removal changed the apply workflow. The formula editor's row restriction predates it.

## Browser verification

| Scenario | Observed |
| --- | --- |
| Total KWH Sold → Bill Adjustment | Section changed from `hidden=false` and `display:block` to `hidden=true` and `display:none`. The saved editor SQL changed from `sales_kwh` to `adj_kwh`, and the value column changed to `KWH_Diff`. |
| Bill Adjustment → Total KWH Sold | Section became visible again and restored A18's saved expression, checkbox, and value column. |
| Close and reopen Settings, then select Bill Adjustment | Metadata reloaded. A25 remained Configured with the formula section hidden and the saved SQL and `KWH_Diff` loaded. |
| Select another configured row | Only A18 and A25 were configured in the live selector. No third configured row was available. |
| Select a Not Configured row (A11) | Status changed to Not Configured. The SQL editor, expression, and value-column field were empty. The formula section stayed hidden. |

Headless Edge reported no JavaScript exceptions or console errors. Apply & Save stayed disabled after each selection. The browser test permitted the metadata POST and blocked other write-capable requests. No settings, workbook values, or database records were changed.

## Smallest safe fix and regression risks

Clarify the generic value-column field in [partials/sql-settings.php:27](../partials/sql-settings.php#L27). Use **Value Column** with help explaining that the selected column supplies the report value and that Total KWH Sold can use a report formula when enabled. This addresses the misleading **Fallback Value Column** label on A25 while keeping the existing row gate and both calculation paths.

- Showing the editor for every Configured row would expose options the test and apply backend rejects. If per-row expressions become a separate requirement, define an explicit row capability and align the selector, preview, and backend validation. In particular, the preview branch forces `formulaEnabled=true` after the current early guard. Any expansion should validate row eligibility for the preview action itself. [query-settings-api.php:22–33](../query-settings-api.php#L22-L33)
- Copying the A18 expression into A25 would change the basis of the adjustment row and require `Late_Bills_KWH`, which A25's saved query does not return. This can affect report calculations and exports.
- Recheck A18 → A25 → A18, reopen on A25, and select a Not Configured row after any copy change. Confirm the correct SQL and result column load and that switches still clear the previous test authorization. Keep workbook formula overwrite permissions independent of editor visibility.
