# GENSTAT Summary empty rows

The Summary hides completely empty spacer rows by default. **Settings > Appearance > Show empty rows** previews the change immediately. **Apply Changes** saves the preference in the existing browser Appearance settings. Cancel or closing Settings restores the last saved choice.

## Inspection

The renderer distinguishes short visual spacers from rows hidden by the worksheet or Summary label policy. Some rows that look empty in the Summary contain data or formulas outside the table. The Summary now hides rows 13, 14, 17, 20, 29, 32, 35, 38, 41, and 42 by default. The labeled Unbilled kWh row remains visible.

## Visibility rules

[The row visibility policy](../lib/base-report-view.php) inspects the Summary's rendered columns A through R, including the totals group when collapsed. Headers, visible merges, labels, formulas, protected formula cells, SQL input cells, and designated manual inputs within that range keep their rows visible. Whitespace and formatting alone do not count as content. Numeric zero counts as content. Cells outside the Summary remain available to the calculation engine and Excel export.

[The report renderer](../base-report.php) marks explicit hides, spacer candidates, and their initial visibility independently. It also includes configured SQL rows from [the existing SQL settings read](../lib/query-settings.php). A row with saved SQL settings remains visible even when its result is blank. [The browser controller](../assets/base-report.js) rechecks only the spacer candidates when a calculation or reporting month changes. A draft formula, calculated value, or SQL configuration can keep a candidate visible without rebuilding the table.

[The Appearance form](../partials/report-appearance.php) uses the existing themed settings controls. [Appearance preferences](../assets/report-appearance.js) persist under the same browser key as the other report preferences. Showing empty rows cannot reveal an explicitly hidden row. Navigation and formula highlighting use actual row visibility. The screen keeps the original Excel row numbers and references.

## Verification

- [View tests](../tests/base-report-view.php) cover off-table values and formulas, visible numeric zero, whitespace, empty-result formulas, designated input cells, SQL configuration, and merged rows.
- [Compact browser tests](../tests/base-report-compact.cjs) cover default visibility, instant preview, persisted preferences, explicit hides, original row numbers, and Enter navigation across a hidden spacer.
- Workbook, export, and report-calculation checks use the original worksheet structure. No database schema, worksheet addresses, or Excel export code changed.

The view, workbook/export, compact browser, report browser, Recovered Sales, SQL Apply & Save, and isolated COOP suites passed. The compact suite verified July-to-August switching, the original row number after showing a spacer, restoration on Cancel, and persistence after refresh.
