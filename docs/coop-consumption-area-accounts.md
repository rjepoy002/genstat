# COOP monthly consumption by area and account

The COOP API still supports area summaries and paginated account details for validated monthly SQL. In **Settings > COOP Consumption**, **Test Query** validates the editor query and **Save Query** stores it. The Account Management table shows monthly KWH for registered accounts.

## SQL contract

Use the existing saved COOP connection and bind the GENSTAT YYYYMM period as `:ReportMonth`. For an account preview, return these columns.

| SQL alias | Meaning |
| --- | --- |
| `AreaID` | Stable area identifier assigned by the approved source logic |
| `AreaName` | Display name for that identifier |
| `AccountID` | Exact account identifier. Return it as text to preserve leading zeros. |
| `AccountName` | Optional display name |
| `KWH` | Non-null exact SQL decimal or integer consumption |

Return one row per eligible account for the requested month. Resolve multiple billing rows using the approved billing rules in the configured SQL. GENSTAT rejects repeated exact account identifiers within an area or across areas, missing identifiers, and conflicting area names. It never picks a bill from duplicate rows. It groups exact area identifiers, sums account KWH as SQL decimals, and adds area totals using BCMath. The legacy flat preview continues to work for queries without the account-preview aliases.

Details return up to 50 accounts per page, in exact account-ID order. Area and account views use the same source SQL and report month. Each detail request uses a read-only transaction for its validation, totals, and page. If an area's count or total differs from the displayed summary, the interface asks for a refresh. Separate requests can still observe source changes that preserve both the count and the total. The existing SQL execution timeout applies per statement. No report cells, drafts, formulas, or Excel exports receive preview values.

## Recovered historical SQL

Backup commit `26c6a01`, in `lib/coop-cloud.php`, uses these exact statements with bound placeholders for batched account identifiers.

```sql
SELECT AcctCode,AcctNo FROM master WHERE AcctCode IN ($placeholders) LIMIT 1001
```

```sql
SELECT BINARY AcctNo AS identity,COUNT(*) AS records,COUNT(KWH) AS valid_records,SUM(KWH) AS kwh FROM billhistory WHERE BillMonth=? AND AcctNo IN ($placeholders) GROUP BY BINARY AcctNo LIMIT 1001
```

The historical application selected effective enrollments for the month, resolved each exact account code to one canonical `master.AcctNo`, and used the enrollment's area. Billing matched the canonical account and bound `billhistory.BillMonth`. The reviewed path used the exact sum of valid billing records and treated missing or NULL data as incomplete. The provisional path additionally marked multiple bills or negative KWH for review. Historical enrollment is therefore a different population from all accounts in the COOP source.

The connected local database has `master.AcctNo`, `master.AcctCode`, `master.Name`, `master.Area`, and `billhistory.AcctNo`, `billhistory.BillMonth`, and `billhistory.KWH`. `master.Area` contains codes. No verified full mapping from those codes to the GENSTAT area names was found. The previous provisional preview did not use those codes as its area assignment. The live Phase 1 connection was saved, and its SQL editor had no saved query. No replacement SQL was installed.

## Local comparison

The following read-only diagnostic query was tested through the live saved connection in July 2026. It uses the already documented Aborlan enrollment for this comparison only.

```sql
SELECT 'aborlan' AS AreaID, 'Aborlan' AS AreaName,
       b.AcctNo AS AccountID, b.KWH AS KWH
FROM billhistory b
INNER JOIN master m ON b.AcctNo = m.AcctNo
WHERE b.BillMonth = :ReportMonth AND m.AcctCode = '03-0152-0325'
```

July returned one account and **1407.00 KWH** in the area summary and account list. October returned no billing rows. Both matched the historical findings for the same account. The diagnostic is a comparison for one known account. For production coverage, confirm the eligible population, authoritative area mapping, and treatment of any multiple bills requiring review before saving the full source query.

The MariaDB browser fixture verified exact totals, duplicate account rejection, a 510-account area, search by account name, leading zeros, pagination, no matching accounts, an empty result, the existing timeout, month switching, and light and dark mode. The SQL parser suite passed.

## Files

- `lib/coop-account-preview.php` and `lib/coop-phase1.php` for monthly data validation, totals, and bounded account pages.
- `coop-phase1-api.php` for account details on the saved query or the exact query recently tested for that month.
- `assets/coop-phase1.js` and `partials/coop-phase1.php` for the area summary and account details.
- `tests/coop-phase1-browser.cjs` and `tests/coop-phase1-fixture.php` for MariaDB browser regression coverage.
