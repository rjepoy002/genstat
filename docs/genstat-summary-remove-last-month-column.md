# GENSTAT Summary column S

The browser Summary now ends at R. With **Show Totals** enabled, **TOTAL** spans Q and R, labeled **This Month** and **To Date**. No column S heading, table cell, or width definition is rendered. The existing collapse control still hides and restores the totals group.

## Inspection and implementation

The reference workbook supplies the worksheet range `Jul26!A8:S47`. The Summary uses its Excel coordinates for heading letters, grouping, cell selection, formulas, and drafts. The browser's width calculation and keyboard navigation already read rendered elements. Formula highlighting intersects references with rendered rows and columns. The Excel export uses the full worksheet layout independently.

[The rendered-column policy](../lib/base-report-view.php) excludes Excel identifier S. [The Summary renderer](../base-report.php) uses that list for column definitions, letters, cells, and visible header spans. The footer describes the browser range through R. The renderer preserves the source TOTAL merge and the Q and R widths and styles. No styles, workbook formulas, draft coordinates, or exporter code were changed.

## Column S dependencies

The following expanded formulas in column R refer directly to the corresponding column S cell.

| Formula cells | Calculation |
| --- | --- |
| R11, R12, R19, R26, R27, R28 | Prior-period cell in S plus the current-period value in Q |
| R21, R22, R23, R24, R25, R30 | Current-period cell in Q plus the prior-period value in S |

Other cumulative rows depend on these results. For example, R18 references R21 and R26 among its inputs. The worksheet also contains an S21 formula and a U18 formula that references S18 beyond the displayed Summary. All remain in the workbook model. Selecting R11 still displays the S11 reference in the formula bar and outlines the visible Q11 reference.

## Verification

- [Worksheet and export checks](../tests/base-report.php) compare the source formulas and confirm that editing B11 changes R11 while preserving its S11 dependency. The test opens the exported workbook and verifies the S11 value, the S21 formula, and the R11 reference to S11. It also checks the source workbook hash.
- [Compact browser checks](../tests/base-report-compact.cjs) exercise the two-column totals group, Tab at the right edge, original formula references, responsive width, sticky headers, and reporting month changes.
- [Browser report checks](../tests/base-report-browser.cjs) now expect the selected-row outline to end at R and retain the existing report editing and reference checks.

The worksheet, compact/browser report, Recovered Sales, SQL Apply & Save, and isolated COOP suites passed. The browser checks covered July and August and both light and dark appearance settings.

The display change requires no database migration and leaves Excel column S intact.
