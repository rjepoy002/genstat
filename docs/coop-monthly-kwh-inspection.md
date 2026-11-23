# Phase 2 COOP monthly KWH inspection

## 1. Executive summary

July 2026 Aborlan account `03-0152-0325` has **one exact billing record totaling 1,407.00 KWH**. Its saved enrollment matches the unique source identity and maps to `Jul26!C26`. **1,407.00 KWH is the candidate for this single-record case**. A general rule for multiple billing records, corrections, and cancellations remains pending billing-owner verification.

Per-account KWH retrieval, area totals, preview, and reviewed Apply already exist. The live report currently has billing review and ownership disabled. Nothing was refreshed or applied on the live report during this inspection.

## 2. Current COOP module status

`coop-consumption-api.php` and `lib/coop-cloud.php` already orchestrate and run Refresh. `lib/coop-consumption.php` produces per-account status, area totals and completeness. `lib/coop-repository.php` selects effective accounts. `lib/coop-report.php` resolves workbook targets and ownership. `assets/coop-consumption.js` and `partials/coop-consumption.php` already render enrolled accounts, preview, totals, and history. `base-report.php` and `assets/base-report.js` integrate report updates, calculations, and draft handling.

The enrollment-readiness change deliberately separated account enrollment from billing review. Successful connection testing and enrollment therefore leave billing readiness pending. The implementation, readiness, and local-reader documentation match the observed architecture.

## 3. Local source database verification

The saved COOP reader authenticated to XAMPP MariaDB 10.4.32 at `127.0.0.1:3306`, schema `palecxzp_pal_db`. The authenticated source account has baseline USAGE and only SELECT on `master` and `billhistory`. The existing source schema validator passed. Source diagnostics used the saved reader and prepared statements. No source query used root or the GENSTAT reporting writer. Enrollment metadata came from the local COOP `view` API.

## 4. Enrolled account verification

The July view returned exactly one effective enrollment. Its area key was `aborlan`, effective from `202607` with no end month. The prepared exact lookup returned exactly one `master` row. Its canonical identity was valid and exactly matched the saved enrollment. No second enrollment for that canonical identity was effective in July. The canonical identity and customer details were omitted from diagnostic output.

## 5. billhistory schema analysis

| Purpose | Verified schema |
| --- | --- |
| Primary and unique keys | PRIMARY on `TransID`, VARCHAR(255), NOT NULL. No other unique index and no unique constraint on `(AcctNo, BillMonth)`. |
| Other indexes | `idx_acctno (AcctNo)`, `idx_acno_bm (AcctNo, BillMonth)`, `idx_trx_acno (TransID, AcctNo)`. |
| Billing identity and period | `AcctNo` VARCHAR(15), `BillMonth` VARCHAR(6), both NOT NULL. |
| Consumption | `KWH` DECIMAL(19,2), NOT NULL. |
| Bill identity and timing | `BillNo` INT(11). `ReadingDate`, `BillDate`, `DueDate`, and `Created` are DATETIME. |
| Other columns | `PrsRdngKWH`, `PrvRdngKWH`, `Evat`, `PowerBill`, `TotalOC`, and `TotalBill` are DECIMAL(19,2). `MeterReader` is VARCHAR(50), and `UserName` is VARCHAR(20). |
| Correction semantics | There are no explicit cancellation, adjustment, status, replacement, or original/rebill linkage columns. Neither `BillNo` nor a date establishes which bill is final without the source owner's contract. |

## 6. July 2026 billing record findings

| Field | Verified result |
| --- | --- |
| Area and account | Aborlan, `03-0152-0325` |
| BillMonth | `202607` |
| Billing record count | 1 |
| NULL / zero / negative / positive KWH records | 0 / 0 / 0 / 1 |
| Raw KWH total | **1,407.00 KWH** |
| Recommended KWH for this example | **1,407.00 KWH** as the single-record candidate. Whether separate correction or cancellation data supersedes it remains unverified. |
| Summary target | **`Jul26!C26`** |

The current `coop_bills()` implementation independently returned the same count and total. In October 2026 the same account returned zero billing rows, which means **NO BILLING DATA** for that account and month.

## 7. Duplicate and correction analysis

| Bounded aggregate scope | Account-month groups | Duplicate groups | Max records per group | Groups with differing KWH |
| --- | ---: | ---: | ---: | ---: |
| All rows in July 2026 | 59,238 | 0 | 1 | 0 |
| All rows in October 2026 | 0 | 0 | No groups | 0 |
| First 100,000 rows in primary-key order | 100,000 | 0 within the sample | 1 | 0 within the sample |
| Exact enrolled account, all available months | Duplicate-only check | 0 | No duplicate groups | 0 |

The all-history aggregate hit the six-second statement limit, so historical duplicate prevalence and maximum multiplicity are not established. The primary-key sample can miss duplicates split across its boundary. July and the sample also had no groups with differing bill numbers. The schema permits multiple rows for an account and month. The evidence does not identify how to distinguish installments, additive adjustments or superseded bills if a duplicate appears. Confirm the authoritative billing grain and correction sources with the data owner before treating arbitrary multi-record sums as final.

## 8. Existing KWH query review

`coop_master()` and `coop_identity()` resolve a single exact code and treat identifiers as strings, preserving leading zeros. The service compares the resolved identity with the enrollment, deduplicates identities for billing, and rejects the same canonical account twice during aggregation. There is no master-to-bill join to multiply rows.

`coop_bills()` binds the selected YYYYMM and account identities, groups by binary `AcctNo`, and returns COUNT, non-NULL COUNT and SUM. It already sums every non-NULL bill for each account and month and has no correction or cancellation filtering. The service rejects a result with NULL KWH and distinguishes no rows from a real zero. MariaDB DECIMAL and PHP BCMath use exact arithmetic with 12 fractional places in the service. A valid negative value is included under the current code, and its business meaning still requires a billing rule.

## 9. Month KWH display findings

Opening the dialog requests `view` and `connection-view`. Neither returns billing values. The enrolled-account table gets KWH by enrollment ID from the client-side staged Refresh result. Before Refresh, or for an entry whose KWH is null, the `Month KWH` column displays `--`. It retains a returned zero. The preview table already exposes account-specific status such as No Bill History, Zero KWH, or Connection Failed. Reopening starts a fresh client-side preview state.

Reuse Refresh for Month KWH and expose the existing result status alongside each account. Show **Pending verification** before a successful result, the numeric KWH including **0 KWH** for a real zero, **No billing data** when no rows exist, and **Retrieval failed** for a failed lookup. Keep identity or disputed-bill conditions visibly incomplete. Refresh should continue to stage results while leaving the report unchanged.

## 10. Summary area aggregation

Parsed the reference workbook through GENSTAT's existing layout reader. The authoritative worksheet is `Jul26`, and `coop_targets()` identifies row 26 from worksheet and area metadata. Aborlan maps to `C26`. The 15 eligible input cells are `B26:P26`. Araceli/Dumaran remains combined at `J26`, and Cuyo/Magsaysay at `N26`. `A26` is static. `Q26` is protected `=SUM(B26:P26)`, `R26` is protected `=+S26+Q26`, and `S26` is manual.

The aggregator adds complete accounts sharing an area. It leaves an area total unknown if an enrolled account is missing or invalid. An area without enrollment has a null proposal. Apply skips null proposals, which keeps other areas unchanged for a complete Aborlan-only preview. The calculation engine recalculates dependent formulas after Apply, and export preserves the workbook formulas.

## 11. Preview and Apply readiness

Refresh already returns per-account KWH and status, area current and proposed KWH, differences, and a completeness result. It stages a 15-minute local run and writes audit events. Missing billing, NULL KWH, identity errors, incomplete responses, and excessive Excel numeric precision prevent Apply. Refresh does not update working or saved Summary values.

Refresh requires the exact workbook hash in `GENSTAT_COOP_REVIEWED_TEMPLATE`, `GENSTAT_COOP_SCHEMA_REVIEWED=1`, and an allowed source. The verified local reader, saved connection, and approved loopback port satisfy the local source policy in Development Mode. A remote source requires the separate remote policy. Apply adds `GENSTAT_COOP_OWNERSHIP=1`, explicit confirmation, no generic SQL ownership conflict, and an applicable run for the same actor, period, workbook hash, draft revision, active enrollments, connection version, and working report state. Apply updates eligible working values and recalculates. The browser marks changes as unsaved until Save Draft.

Live July and October returned `ready=false`, `owned=false`, and `conflict=false`. Both review variables and ownership are absent from the dedicated listener configuration. These review gates are appropriate for local development because the connector can retrieve live source data and Apply can change report values. This inspection left all gates as configured.

## 12. Reporting period synchronization

In Edge, switching the existing report selector from July 2026 to October 2026 changed the COOP period shown in the modal. The open-ended enrollment appeared in both periods. Effective dates are inclusive at the start and exclusive at the end. The request uses the report snapshot month and the backend filters both effective enrollments and bills for that period. Month changes load a separate draft scope, and COOP discards the client-side staged preview on modal close and reopen. Apply rejects cross-period runs. Previously saved values are left in their original draft scope, and switching away from unsaved changes requires confirmation.

The observed live COOP API calls were only `view` and `connection-view` on modal opening. The readiness probe checks source identity metadata. Billing rows are retrieved only on explicit Refresh.

## 13. Security and performance findings

The connector validates table-level read-only grants. Refresh runs bounded prepared queries in a read-only transaction with a consistent snapshot request and closes the connection in a finally block. Accounts are capped at 500 per report period, and master and billing lookups batch 50 identities. The connector sets connection and read timeouts plus a four-second MariaDB statement budget. Refresh checks an 18-second overall budget between batches. Failed and incomplete runs record sanitized statuses in local history. Apply audits accepted values and rejections in the existing paths.

EXPLAIN for the current single-account July billing query selected `idx_acno_bm` and estimated one row, with temporary sorting for grouping. This supports keeping the existing index. The aggregate-only all-history timeout does not establish a performance problem for the application's selective lookup. No index change is justified by these checks.

## 14. Blocking issues

- The general billing contract for multiple rows, cancellations, corrections, replacement bills, and signed KWH is unverified.
- Billing workbook and schema review are pending. Report ownership remains off.
- The account-table column currently uses the same placeholder for pre-refresh and missing or failed results.

## 15. Recommended implementation

1. Confirm the monthly billing grain and correction or cancellation sources with the billing owner. Use the one valid row as the single-record candidate and leave missing rows unavailable. Until a contract exists, treat multiple rows as requiring review and prevent them from producing an applicable preview. Do not infer a replacement bill from sort order or equal KWH.
2. Reuse Refresh, its per-account statuses, the area preview, and the reviewed Apply flow. Expose KWH and status together in enrolled accounts. Keep billing review, ownership, freshness checks, workbook-derived area mapping, and formula protection.
3. Once the billing owner confirms the rule and an administrator approves a local test, verify the current workbook hash and source schema before staging a preview. Approve ownership separately only when an authorized operator is ready to review and apply a complete result.

## 16. Exact files requiring changes

For the display improvement, update `assets/coop-consumption.js` and `partials/coop-consumption.php`. If the verified business rule requires different row classification or retrieval, update `lib/coop-cloud.php` and `lib/coop-consumption.php`. Change `coop-consumption-api.php` only if preview statuses or its response contract need to carry additional results. Extend `tests/coop-consumption.php` and `tests/coop-consumption-browser.cjs` for the agreed rule and display states. The existing repository, workbook mapping, draft, and export paths can be reused.

## 17. Test results

Read-only source diagnostics verified the exact identity, July KWH, October absence, metadata, duplicate-scan bounds, and index use. Workbook inspection verified the target and protected formulas. A trusted localhost browser session verified July and October synchronization with no JavaScript exceptions. Live Refresh and Apply were not submitted. Automated tests used synthetic data.

All three targeted suites passed: COOP aggregation and policy, COOP browser workflow, and base-report browser regression. The fixtures cover missing versus zero, area totals, month changes, staging, reviewed Apply, draft saving, and formula-preserving export.

## 18. Final go/no-go

**Go** for the Month KWH status improvement using the existing staged response and an isolated single-record verification workflow. **No-go** for applying live results or treating arbitrary multi-row KWH as final until the billing contract, workbook and schema review, and ownership approval are complete.
