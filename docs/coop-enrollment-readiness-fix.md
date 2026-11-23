# COOP enrollment readiness

Enroll Account previously used the same `ready` flag as Refresh Consumption. A working saved connection could therefore show **Connection Successful** while enrollment remained disabled because billing schema and workbook approvals were pending.

## Enrollment and billing

The API now returns `enrollmentReady` after verifying the current report and Summary mapping, checking the local enrollment tables and seed, and opening the saved COOP source under the existing connection policy. The source probe validates the two `master` identity columns using metadata. A verified loopback source works without remote cloud activation, and a remote source still requires the existing endpoint, read-only privilege and TLS checks.

The `enroll` action uses the same storage and source checks. It validates the area, effective month and account code, then resolves the exact account in `master`. Invalid and ambiguous identities are rejected. Enrollment reads no `billhistory` rows. The repository keeps its canonical account lock, overlap check, transaction and audit write. Deactivation and reassignment use the stored identity and preserve historical intervals.

The frontend displays **Account Enrollment** and **Billing Consumption** statuses separately. Enroll follows `enrollmentReady`; Refresh and Apply follow their existing gates. Refresh still requires a reviewed workbook hash, an eligible source and the billing schema review flag. Refresh and Test Connection still validate the complete source schema. Apply also requires report ownership, a current complete preview, confirmation, and the existing conflict checks.

## Files modified

- `coop-consumption-api.php` exposes and enforces enrollment readiness.
- `lib/coop-cloud.php` validates source identity metadata for enrollment.
- `lib/coop-repository.php` shares enrollment input and storage checks while preserving transactional writes.
- `assets/coop-consumption.js` and `partials/coop-consumption.php` separate the controls and status messages.
- `tests/coop-consumption-browser.cjs` exercises the authorization, source, enrollment and billing gates in isolated fixtures.

## Live verification

On the real localhost HTTPS report, the July 2026 Aborlan example passed form validation. The API returned HTTP 200 with enrollment ready, billing pending review, and ownership disabled. Enroll was enabled and Refresh and Apply were disabled. No live form was submitted, and no source or report records were changed.

## Regression tests

All 22 unit suites and the three targeted browser suites passed. The COOP browser suite used synthetic accounts to verify enrollment with billing review and ownership off. It covered missing source settings, invalid area and month, missing and ambiguous identities, duplicate and overlapping enrollment, authorization and CSRF, deactivation and reassignment, billing-gated Refresh, and ownership-gated Apply. The regression suites also covered unchanged source rows, formula preservation, draft saving, Excel export, and SQL Settings.
