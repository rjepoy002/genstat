# Recovered Sales

The main GENSTAT report uses saved **KWH Used** from the annual recovery workbook, grouped by reporting month and area. KWH Paid and Remaining KWH are available in the editor and export for tracking. The application derives the 14 destination columns from the report headers and finds the recovered-sales row by its Particulars label.

| Main | Aborlan | Narra | Quezon | Brooke's Point | Roxas | Taytay | Balabac | Araceli/Dumaran | San Vicente | El Nido | Cagayancillo | Cuyo/Magsaysay | Agutaya |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| B22 | C22 | D22 | E22 | F22 | G22 | H22 | I22 | J22 | K22 | L22 | M22 | N22 | O22 |

An area with no saved entries displays blank. An explicit zero displays 0.00. The report derives these cells again on load, month changes, recalculation, Restore & Recalculate, and export. Report drafts and SQL previews remain independent of the recovery workbook. The annual workbook editor at the legacy route continues to display its original worksheets, and its Resync workflow does not write to the recovery workbook.

## Excel storage and export

Each year uses one private **storage/genstat_YYYY_recovered_sales.xlsx** workbook. The original annual GENSTAT workbook stays available as a template and can be replaced by the existing upload and synchronization workflow. The private recovery workbook retains the previous saved version with a `.bak` suffix and serializes writes with an annual lock.

`RecovBill_Data` stores RecordID, BillMonth (YYYYMM), Area, KWH Used, KWH Paid, Remaining KWH, and Remarks in columns A–G. D–F use two-decimal number formatting; F uses a row-relative formula and a cached value. The hidden audit sheet stores record changes and the request fingerprint used to recognize a repeated save. Existing saved data takes precedence over the legacy source, so upgrading does not reimport or duplicate records. A reporting year without saved data can seed its original monthly entries, using stable IDs for source rows. Exports include the entire annual breakdown and the original RecovBill sheet, with selected-month formulas and matching cached values in the managed Summary cells.

## Save and recovery

Save Changes opens a confirmation with the month, totals, and entry count. Confirm Save writes the Excel records and refreshes the existing report calculation from the saved workbook while keeping the current draft. A successful refresh closes both dialogs and posts a report notice. If the workbook is saved and the calculation fails, **Retry Summary** reruns the calculation without submitting the records again.

## Historical reconciliation

The old July report read fixed references to `RecovBill26!B44:B46` for Main and Narra. Aborlan's C22 was blank and had no link to the new data table. With the four example entries, July displays 8,048.00 in B22, 123.00 in C22, 6,880.00 in D22, and 15,051.00 in Q22.

The existing store already contained the historical entries. A read-only comparison found the following differences between the original annual Summary and the consolidated KWH Used roll-up. No workbook was rewritten for migration.

| Month | Original Summary B22:O22 | Consolidated Summary | Explanation |
|---|---:|---:|---|
| February 2026 | 71,089.00 | 39,379.00 | 31,710.00 kWh is stored under BPSO pending a confirmed mapping. |
| July 2026 | 14,928.00 | 25,051.00 | The local consolidated workbook includes saved additions beyond the four-entry example. |
| September 2026 | 34,315.00 | 33,356.00 | 959.00 kWh is stored under BPSO pending a confirmed mapping. |

January, March, April, May, June, and August matched the original annual Summary across B22:O22. The original RecovBill subtotals can differ from these report-row totals because they use fixed ranges. BPSO remains in storage, in the editor on existing rows, and in Excel exports under its original label.
