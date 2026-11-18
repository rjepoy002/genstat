# Cancelled billing reporting allowlist fix

Date: 2026-11-17 (Asia/Manila).

The active SQL reporting configuration and fresh-install setup now allow exactly these seven tables in `genstat_db`: `overallsalessummary`, `billadjustment`, `billhistorycancelled`, `billhistorycancelled_2`, `area_mapping`, `area_master`, and `kwh_diff_allocation`. The two cancelled tables were added; no existing table was removed and no wildcard access was introduced.

## Modified and added files

- `config/query-settings.local.php`: added the two table names to the existing `allowedTables` array. This local credentials file remains Git-ignored; credentials were not changed.
- `tools/setup-query-settings.php`: includes both cancelled tables in the fresh-install approved table set and its table-specific SELECT grants. This setup script was not executed against the existing installation.
- `tests/query-parser.cjs`: positive coverage for all seven tables, qualified names, cancelled-table CTEs/joins/aggregation/window functions; regression coverage for forbidden statements, functions, comments/hints, alternate tables, and cross-database access.
- `tools/verify-reporting-access.php`: read-only CLI audit of the exact allowlist, configured reader grants, table access, and saved query definitions. An optional approved SQL file is validated verbatim and, once permissions are available, executed for July 2026 with expected-column checks.
- `tests/reporting-allowlist-browser.cjs`: read-only ten-column UI fixture, dropdown/token/preset checks, unchanged live report snapshot, and unchanged source-workbook SHA-256.
- `docs/cancelled-reporting-allowlist-fix.md`: this report.

The SQL parser/validator implementation, query calculations, allocation rules, formula controls, workbook, and source data were not changed. Its SQL grammar, function allowlist, comment/hint prohibition, single-statement restriction, bound month, timeout, result limits, cross-database prohibition and SELECT-only grant audit remain active.

## Verified configured reader permissions

`CURRENT_USER()` returned `genstat_report_reader@127.0.0.1`. SHOW GRANTS returned USAGE at global scope and table-specific SELECT on the original five approved tables only. No INSERT, UPDATE, DELETE, ALTER, DROP, CREATE, GRANT OPTION, wildcard SELECT or other table access was present.

Direct reader-account probes succeeded for `area_master`, `area_mapping`, `overallsalessummary`, `billadjustment`, and `kwh_diff_allocation`. Both cancelled tables returned permission error 1142. Therefore access to all seven is **not yet verified**. No database grants were changed by this task.

An authorized DBA must execute exactly:

```sql
GRANT SELECT ON `genstat_db`.`billhistorycancelled`
TO 'genstat_report_reader'@'127.0.0.1';

GRANT SELECT ON `genstat_db`.`billhistorycancelled_2`
TO 'genstat_report_reader'@'127.0.0.1';
```

No FLUSH PRIVILEGES is needed for GRANT statements. No credentials need to change. Afterward, rerun:

```powershell
C:\xampp\php\php.exe tools/verify-reporting-access.php
```

The audit deliberately reports failure while any approved table lacks SELECT; it does not substitute another account or grant permissions itself.

## Test results

- `node tests/query-parser.cjs`: PASS. All seven tables accepted in qualified and unqualified form; bound `WITH params AS (SELECT :ReportMonth AS ReportMonth)`, joins, aggregation and ROW_NUMBER window function accepted. Unapproved `billhistorycancelled_3`, `overallsalessummary_x`, `billadjustment_1`, other database tables, comments, executable hints, unknown/unsafe functions, multiple statements and side effects remain rejected.
- `C:\xampp\php\php.exe tests/query-settings.php`: PASS, including real bound-month CTE execution, result limits, three-second server timeout, duplicate/missing/NULL identity checks and rejection of reader UPDATE access. It changes no configuration or source records.
- `node tests/reporting-allowlist-browser.cjs`: PASS. A clearly labeled read-only fixture bound to 202607 returns BillMonth plus the nine requested identity/value columns. All result columns populate the automatic matching dropdown, all six calculation columns populate numeric tokens, and all formula presets become available. The report snapshot and source workbook checksum remain identical. No Test Query values are applied or saved.
- PHP syntax checks: PASS for the active configuration, setup script and audit tool.
- Live permission audit: five PASS, two MISSING SELECT as described above. This is the remaining deployment requirement, not bypassed by the code change.

## Full approved query: pending

No saved SQL query definitions were found. The original approved July SQL is not available in the current Codex context or repository inspection. Per the user's instruction, it was not reconstructed or replaced. The fixture tests establish validator and UI compatibility, not the approved business calculation or its totals.

After the DBA grants and the original query become available, run:

```powershell
C:\xampp\php\php.exe tools/verify-reporting-access.php C:\path\to\approved-query.sql
```

The tool binds ReportMonth to `202607` and checks `BillMonth`, `SACode`, `AreaID`, `AreaName`, `Sales_KWH`, `KWH_Diff`, `Cancelled_KWH`, `Cancelled_KWH_2`, `Total_Cancelled_KWH`, and `TotalKWhSold` without modifying the SQL. Confirm the complete result and then test that exact query in the settings UI. Do not use the fixture as the production calculation.

No database schema, billing records, saved SQL configurations, report drafts, Excel values or formulas were changed. No GitHub push was performed.
