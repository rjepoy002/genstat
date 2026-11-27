# GENSTAT monthly snapshot implementation

The report toolbar's **Save** action publishes a monthly snapshot. Initial load, year/month selection, and reload restore that snapshot before invoking the live reporting pipeline. A saved month remains editable. **Restore Report Formulas & SQL Values** refreshes the working report, which becomes the new snapshot only after a successful Save.

## Storage and workflow

- `storage/monthly-snapshot-YYYYMM.json` holds a versioned, checksummed snapshot. It includes the template hash and worksheet identity, explicit revision and timestamp, manual inputs, Excel formulas and overrides, signed SQL replacements and provenance, the captured SQL/Coop and manual override state, Recovered Sales inputs and summary, effective totals, and calculated cell values, displays, and errors.
- The per-month file lock and revision check serialize publication. The atomic file writer replaces the snapshot only after writing the complete replacement. A stale Save or an unreadable snapshot produces an error. The application preserves the existing file in either case.
- The shared loader returns the stored cells and captured source inputs for saved months. Unsaved periods continue through the reporting SQL, Coop Consumption, Recovered Sales, and formula calculation pipeline.
- The local reporting-month catalog is combined with saved snapshot filenames. Saved months can open without a reporting connection. The live source catalog is refreshed when the app opens an unsaved period. A newly available source month may require navigating directly to its month URL from a session that is viewing saved periods exclusively.
- Each open report has a session-scoped working context tied to its month and snapshot revision. Editing, undo, and deliberate refreshes affect that working context. Excel export uses the displayed working inputs and the existing workbook exporter. Save updates the status and explicit timestamp after the server confirms publication.
- Existing monthly drafts remain in place and retain their manual inputs and overrides. A legacy draft, its revision, its timestamp, and legacy worksheet autosave do not establish explicit snapshot status. The separate GENSTAT Sync application and its scheduler were left untouched.

## Files

| File | Change |
|---|---|
| `base-report.php` | Shared load flow, snapshot-backed edits, Save and export, and captured Recovered Sales context |
| `lib/monthly-snapshot.php` | Snapshot persistence, checksum and revision validation, local month catalog, and working contexts |
| `lib/base-report.php` | Loads the snapshot helpers for report services |
| `lib/base-report-sql-manual.php` | Applies saved-month SQL overrides in the working context |
| `lib/base-report-restore.php` | Restores formulas and SQL/Coop inputs without publishing a saved month |
| `query-settings-api.php` | Uses the saved baseline and preserves captured inputs during explicit SQL settings operations |
| `assets/base-report.js` | Save confirmation and status, working-state lifecycle, and saved-month refresh behavior |
| `assets/recovered-sales.js` | Carries the working report identity through the existing Recovered Sales editor |
| `tests/monthly-snapshot.php` | Isolated storage tests for drafts, revisions, month separation, and damaged snapshots |

## Regression results

Browser checks used a disposable MariaDB instance and loopback PHP server. July and August used fixture sales values of 101.00 and 201.00, with a separate local Coop billing account. Database logging verified query execution and the absence of reporting and Coop queries on saved-month loads. Production databases were not accessed.

| Test | Result | Evidence |
|---|---|---|
| A. Unsaved month and first Save | PASS | Automatic SQL and Coop loading ran. July displayed 101.00. Save wrote the complete snapshot. |
| B. Month switching | PASS | Unsaved August loaded 201.00 and its own Coop values. Returning to July restored 101.00 without reporting SQL or Coop queries. |
| C. Source database changes | PASS | After the July sales fixture changed to 999.00, reopening and reload kept the displayed values and snapshot bytes unchanged. |
| D. Explicit refresh | PASS | Restore displayed 999.00 and the changed Coop amount in the working report. Discard restored 101.00. Restore followed by Save published 999.00. |
| E. Editing | PASS | A manual edit recalculated a dependent total without reporting or Coop queries. Save and reopen preserved it. |
| F. Database unavailable | PASS | A fresh browser opened saved July with its reporting connection pointed at an unavailable endpoint. |
| G. Drafts and month isolation | PASS | An existing August draft kept its manual input, still loaded live values, and did not create a snapshot. Storage tests also verified separate month files. |
| G. Background synchronization isolation | PASS | An isolated GENSTAT Sync run completed and left the snapshot bytes unchanged. Changing the report fixture separately left displayed July values unchanged. |
| G. SQL provenance and manual overrides | PASS | The SQL badge appeared for captured values. A manual SQL override stayed in the working report until Save and retained its label and value after reopen. An explicit SQL settings Apply changed the working SQL value while preserving captured Coop values and snapshot bytes. |
| G. Coop Consumption and Recovered Sales | PASS | July's captured Coop totals survived source changes. An explicit Recovered Sales edit stayed in the working report until Save and reopened with the captured result. |
| G. Excel export | PASS | The browser downloaded an XLSX without reporting or Coop refresh. Its worksheet XML contained the expected SQL, Coop, and manual input values. |
| G. Rapid switching | PASS | With the first response delayed, a second change event could not apply another period's response. |
| G. Write failure and invalid snapshots | PASS | An injected write failure kept the prior snapshot bytes and working values. A damaged snapshot was reported without a live fallback. Stale revision and checksum cases also passed the PHP storage test. |

Run the standalone storage regression with `C:\xampp\php\php.exe tests\monthly-snapshot.php`. It uses a disposable temporary directory.

## Remaining limitations

- Saved reports require the reference template version recorded by the snapshot. Changing the deployed template requires retaining or explicitly migrating affected snapshots.
- The targeted fixture tests cover the new snapshot workflow. The broader legacy browser suites were not run. Some of their assertions still expect the main Save button to write a draft or Restore to persist a saved report, which are superseded behaviors.
- The production scheduled worker was inspected for isolation. It was not run against production during this implementation.
