# COOP account enrollment disabled

## Root cause

`assets/coop-consumption.js:24` sets the **Enroll Account** button's disabled state to `busy || !meta?.ready`. The live COOP `view` response for July 2026 returned HTTP 200 with `ready: false`, `developmentMode: true`, `owned: false`, and `conflict: false`. The connection request independently returned HTTP 200 with `configured: true` and **Connection Successful**.

`coop-consumption-api.php:30–40` computes the shared readiness flag as `$reviewed && $sourceReady && getenv('GENSTAT_COOP_SCHEMA_REVIEWED') === '1'`. The dedicated Apache vhost enables Development Mode and approves local port 3306. The saved loopback source qualifies for `$sourceReady`. Neither `GENSTAT_COOP_REVIEWED_TEMPLATE` nor `GENSTAT_COOP_SCHEMA_REVIEWED` is configured in the vhost and include or in the Windows user or machine environment. The workbook approval requires the exact current workbook hash. The schema flag requires the literal value `1`. Both gates are closed.

Cloud activation is off, which is compatible with an approved direct loopback source. COOP report ownership is also off. It is checked for Apply and is outside the Enroll button's expression. Successful credential testing and saving do not set either review flag.

## Browser findings

In Edge over trusted localhost HTTPS, the modal resolved **Aborlan** to the `aborlan` area key and accepted the example account code and `202607` as a valid HTML form. The July 2026 report period was available, the loading state had cleared, and the button stayed disabled. Reopening the modal and reloading the report both fetched fresh data and reproduced the same state. No JavaScript exceptions occurred. The only console errors were HTTP 403 for `/favicon.ico`.

The Save Connection success handler already reloads both the `view` metadata and `connection-view` status, then recomputes the controls when the operation finishes. This was confirmed by inspecting the handler without resubmitting credentials. There is no separate frontend administrator, operator, or Development Mode predicate for the button. Authorization is enforced by the API, and the frontend receives the resulting server metadata. The HTML requires a selected area, an account code of at most 128 characters, and a six-digit effective month. Form validity does not toggle the button.

## Backend enrollment requirements

| Requirement | Existing behavior |
| --- | --- |
| Action, authorization and CSRF | `POST /coop-consumption-api.php` with JSON `action: "enroll"`, an authorized administrator/operator or verified development actor, and a valid session token for the selected report month. |
| Reporting period and workbook | The selected report month must be available and the submitted workbook hash must match the current workbook. The enrollment branch then checks the separate deployment-reviewed hash before handling enroll, deactivate, or reassign. |
| Shared billing readiness | New enrollment must also pass `$ready`; this includes schema-review approval and source readiness. The API checks this before resolving the example account. |
| Source identity | The source must authenticate under its applicable loopback or remote TLS policy and pass the read-only grant checks. The current enrollment path validates the full source schema, then resolves an exact `master.AcctCode` to a valid, unambiguous canonical identity. |
| Area and effective month | The repository validates the submitted area against the current workbook's Summary targets and validates the effective month. The effective month is separately validated from the selected reporting month. |
| Duplicate prevention | The repository locks the enrollment seed and the canonical identity, checks for overlapping intervals, then writes the enrollment and audit event in a transaction. |
| Billing, cloud and ownership | New enrollment resolves the account in `master` and does not retrieve billing rows. A remote source requires the existing cloud enablement and TLS controls. An approved loopback source does not require cloud enablement. Apply requires report ownership and reviewed confirmation; enrollment does not. |

The deployment-reviewed billing schema and exact workbook approval are appropriate gates for Refresh and Apply. Enrollment needs a valid Summary mapping and a verifiable source identity. It does not need to authorize billing retrieval or a report write. The existing source connection test can still validate the complete billing schema.

## Example account and local enrollment state

Using the saved source configuration and the dedicated reader, a prepared binary-exact query against `master` found **1** matching account. Its `AcctNo` passed the application identifier validator, and the identity was **not ambiguous**. The account number and customer details were not printed or included in this report.

A prepared lookup in `genstat_db.coop_consumption_accounts` by submitted code or canonical identity returned no rows across any effective period. There is no existing area assignment or overlapping enrollment for the example. The enrollment seed `id = 1` exists. The unique index covers `(canonical_acct_no, effective_from)`; the transactional overlap check covers intervals with different start months and applies across areas.

The actual runtime writer has SELECT, INSERT and UPDATE on enrollment state, account locks and enrolled accounts, and SELECT and INSERT on audit. The probe used only SELECT statements and grant metadata. No enrollment action was submitted.

## Recommended implementation

1. In `coop-consumption-api.php`, add an explicit `enrollmentReady` capability and a safe reason when unavailable. Base new enrollment capability on authorized access, usable local enrollment storage, a validated current Summary target mapping, and a saved source permitted by the applicable loopback or remote policy. Keep the billing `ready` calculation for Refresh and Apply. Preserve the report context and workbook hash freshness checks. If organizational policy requires separate mapping approval before enrollment, make it an explicit enrollment-specific capability with a distinct message.
2. Apply the same capability server-side to `enroll`. Validate area, effective month, and account code before the source lookup. Keep exact canonical identity resolution, ambiguity rejection, and the transactional overlap and audit logic. In `lib/coop-cloud.php`, expose the existing identifier metadata checks for `master.AcctCode` and `master.AcctNo` separately from billing schema checks. Deactivate and reassign can use the stored identity with their existing interval and audit checks.
3. In `assets/coop-consumption.js`, drive Enroll from `busy || !meta?.enrollmentReady`. Keep Refresh on the billing capability and Apply on its reviewed-preview and ownership checks. Show enrollment and billing status separately. Add an inline status target in `partials/coop-consumption.php` if needed. Reuse `lib/coop-repository.php` for duplicate detection and writes.
4. Extend `tests/coop-consumption-browser.cjs` and the COOP policy tests with a verified loopback reader and billing review flags off. Confirm a fresh connection save or reload updates both capabilities, valid enrollment succeeds with billing still blocked, and missing storage, invalid area or month, an invalid or ambiguous identity, duplicate overlap, missing authorization and invalid CSRF fail safely. Then exercise reviewed Refresh and ownership-gated Apply under their existing prerequisites. Use synthetic accounts and confirm the production and source data remain unchanged.

No application, configuration, or database records were changed during this inspection.
