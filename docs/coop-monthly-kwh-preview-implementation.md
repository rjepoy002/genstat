# COOP monthly KWH and provisional local preview

Month KWH now loads on explicit **Refresh Consumption**. The existing COOP modal shows per-account values and billing status, then shows current and proposed KWH by Summary area. A controlled localhost preview works while billing review and ownership are pending.

## Display and billing classification

| Result | Display and treatment |
| --- | --- |
| Before Refresh | Pending verification |
| One valid positive record | Exact KWH and Verified status, with a single-record candidate notice |
| One zero record | 0.00 KWH and Zero KWH status |
| No records | No billing data, no proposed KWH |
| Multiple records | Requires billing review, with the record count and no proposed KWH |
| Negative or invalid KWH, missing or ambiguous identity | Requires billing review with the reason and no proposed KWH |
| Source retrieval failure | Retrieval failed, with a sanitized error |

The existing prepared queries resolve exact account identity and bind the selected month. They retain leading zeros and use the existing decimal arithmetic. The provisional policy checks the record count before using the aggregate. It leaves an affected area's proposal unavailable if any effective account is incomplete. Areas without enrollment remain without a proposal. The separately reviewed billing path retains its existing aggregation behavior.

## Local readiness, synchronization and protection

The server exposes `localPreviewReady` after validating report context and the workbook-derived Summary mapping, enrollment storage, the saved loopback source, read-only grants, and the full source schema. The local path requires the verified direct Development Mode request, HTTPS, and an approved loopback MySQL port. Remote settings cannot use this action. The readiness probe reads source metadata. It does not query billing rows.

Local Refresh uses the existing batching, area aggregation, preview run storage and audit trail. The modal displays the selected period, connection status, last refresh, preview status, account-level record counts and KWH, and the reason for an incomplete area. Its tables use the existing responsive styling. The period switch clears the current preview and returns Month KWH to Pending verification until a new explicit Refresh. Expired previews are cleared.

The stored preview result includes a server-side provisional marker. Apply rejects these runs even after the billing and ownership flags are enabled. The UI also disables Apply for a provisional result. Preview records local run and audit history and leaves the report values, formulas, draft, and export untouched. The browser response omits canonical source account identities.

## Live localhost verification

| Period | Aborlan billing result | Current Summary | Proposed COOP KWH |
| --- | --- | --- | --- |
| July 2026 | One record, 1407.00 KWH, single-record candidate | C26 was 1407.00 KWH | 1407.00 KWH |
| October 2026 | Zero records, No billing data | C26 was unavailable | Unavailable |

In both periods, the account was pending before Refresh, Apply remained disabled, and the working report snapshot and C26 stayed unchanged. The browser sent no Apply or Save Draft request. Source access remained read-only.

## Files modified

- `coop-consumption-api.php` for local eligibility, staging, error handling and server-side Apply rejection.
- `lib/coop-cloud.php` and `lib/coop-consumption.php` for the local source policy and record classification within the existing aggregation.
- `assets/coop-consumption.js` and `partials/coop-consumption.php` for Month KWH and preview status.
- `tests/coop-consumption.php` and `tests/coop-consumption-browser.cjs` for policy, status, report protection and workflow coverage.

## Requirements before Apply

The billing owner still needs to approve how corrections, cancellations, replacement bills, multiple records and signed KWH affect the final result. Separately reviewed Apply continues to require workbook and schema approval, report ownership, applicable source policy, a fresh complete reviewed run, confirmation, and the existing SQL conflict and report protection checks. Existing provisional runs cannot be promoted.

## Automated verification

All 22 unit suites passed. The COOP, base-report, SQL Settings UI and SQL Settings styling browser suites passed. The COOP test verified the normal reviewed path as well as a provisional run staged with billing and ownership disabled. It then enabled the reviewed gates in the isolated fixture and confirmed that the same run still could not be applied. Tests also covered read-only source access, exact KWH and zero, missing and disputed billing, period switching, report snapshot preservation, formula calculation, Save Draft and Excel export.
