# GENSTAT SQL cell manual overrides

## Existing behavior

The Summary treated Total KWH Sold and COOP overlays as read-only. It also protected Excel cells populated through signed SQL formula replacements. Ordinary input edits cleared the SQL source marker, so they lacked a durable source value to restore. The report already scoped drafts and formula audit history by reference-workbook hash and reporting month, signed SQL preview data, and calculated and exported through the workbook model.

## Implementation

- Edit a SQL-populated Summary cell directly using the existing worksheet input. Enter and Tab commit and move, Escape cancels, and leaving an edited cell commits it. A numeric change saves the selected cell immediately. An equivalent number leaves its source unchanged.
- The overridden cell shows the amber **MAN** badge. Hovering over the cell shows its address, area, original and latest SQL values, manual value, difference, and modification details. The Settings PIN still protects SQL configuration. Ordinary Excel formula editing still requires the worksheet unlock.
- The server validates the reporting period, CSRF token, template, draft revision, source eligibility, and numeric input. It obtains source values from successful queries or verified preview and replacement data. Overrides use GENSTAT report storage.
- The effective value is the active override, otherwise the latest eligible SQL value, followed by the existing fallback. SQL Apply & Save and report refresh update the source value independently. COOP uses the same overlay and retains its account eligibility and incomplete-total checks for last valid values.
- **Restore SQL Value** is available on the selected MAN cell. The confirmation shows the manual value and the latest SQL value. Restoration persists immediately and requires a valid SQL value. **Restore Report Formulas** preserves active overrides.
- Undo restores the earlier MAN or SQL source state and recalculates. A cell returned to SQL uses its latest eligible source value. The undo controls use the existing worksheet edit history.
- The calculation engine and Excel export read the same effective values. Excel writes numeric values at the original cell addresses and keeps dependent formulas. Cumulative calculations retain access to the hidden historical column.

## Storage and audit

`storage/base-report-<workbook-hash>-<YYYYMM>.json.sql-manual.json` follows the existing report sidecar pattern. Each override records its worksheet, original coordinate, period, row, area, original SQL value, manual value, and timestamps. An authenticated editor is recorded when available. The application uses the existing atomic file writer and a file lock to save the override and audit event together. No database migration is required.

The sidecar retains the latest successful source values and a durable history of `SQL_MANUAL_OVERRIDE_CREATED`, `SQL_MANUAL_OVERRIDE_UPDATED`, `SQL_MANUAL_OVERRIDE_RESTORED`, and `SQL_MANUAL_OVERRIDE_UNDONE`. Each event includes the previous and new effective values and source labels, original and latest SQL values, location, timestamp, and available editor. The audit contains no credentials or SQL statements.

Override state survives a reload. Undo is available in the current editing session, following the worksheet's existing undo behavior. The sidecar depends on the existing writable GENSTAT storage directory.

## Files

| Files | Changes |
| --- | --- |
| `base-report.php`, `assets/base-report.js`, `assets/base-report.css`, `assets/edit-history.js` | Edit requests, worksheet input and source indicators, restore confirmation, undo |
| `lib/base-report-sql-manual.php`, `lib/base-report.php`, `lib/base-report-export.php` | Validation, storage, audit, effective values, Excel export |
| `lib/query-settings.php`, `query-settings-api.php`, `lib/coop-summary.php` | Latest SQL values and safe fallback during refresh |
| `tests/base-report-browser.cjs`, `tests/query-apply-save-browser.cjs`, `tests/query-apply-fixture.php` | Worksheet and SQL override regression coverage |
| `tests/coop-phase1-browser.cjs`, `tests/coop-https-mock.php` | COOP override and resync coverage with a local HTTPS mock |

## Verification

| Checks | Results |
| --- | --- |
| SQL Apply & Save browser suite | Direct edits on ordinary and formula-backed SQL cells, unchanged numeric input, invalid input, zero, Enter and Tab navigation, Escape, MAN styling in both themes, and unaffected neighboring SQL cells. |
| Persistence and restoration | Overrides survive reload, draft save, and formula restoration. Month and year changes isolate overrides. SQL refresh updates the underlying value and preserves MAN. Failed refresh retains the last valid SQL value. Restore and Undo preserve the expected numeric value and source label. |
| Audit and workbook | The test checks all four audit event types, recalculates the affected total, and verifies a numeric override and the dependent formula in the exported Excel package. The reference workbook checksum is unchanged. |
| COOP browser suite | Disposable MariaDB and mocked HTTPS sources exercised account registration and month changes. Resync updated the latest COOP value from 40.00 to 42.00 while the manual value remained 7.25. Restore returned the cell to SQL 42.00. |
| Regression and syntax checks | The base report PHP, browser and compact suites, Recovered Sales browser suite, edit history, SQL formula replacement, and query settings checks passed. Modified PHP and JavaScript files passed syntax checks. |

Testing used local fixtures and a mocked HTTPS transport.
