# GENSTAT COOP Consumption implementation

## 1. Implementation summary

The compact GENSTAT report now has a separate COOP Consumption dialog under Settings, with an administrator-only External Database entry. The module uses the live report period and the workbook's Summary headers. Its refresh, review, apply and Save Draft steps are distinct. Production cloud access is off by default.

## 2. Files created

- `migrations/005-coop-consumption.sql`
- `lib/coop-report.php`, `lib/coop-consumption.php`, `lib/coop-cloud.php`, `lib/coop-repository.php`
- `coop-consumption-api.php`, `partials/coop-consumption.php`, `assets/coop-consumption.js`
- `tests/coop-consumption.php`, `tests/coop-consumption-browser.cjs`, `tests/coop-tls.cjs`
- This implementation guide

## 3. Files modified

- `base-report.php`, `assets/base-report.js`, `assets/base-report.css`
- `partials/sql-settings.php`, `assets/sql-settings.js`, `assets/tailwind.input.css`, `assets/tailwind.css`
- `query-settings-api.php`, `lib/query-settings.php`, `lib/query-apply-save.php`
- `tests/query-apply-fixture.php`, `tools/run-tests.cjs`

## 4. Database migrations

Apply migration 005 once to the **local GENSTAT writer database** using an authorized migration account. It creates `coop_enrollment_state`, `coop_account_lock`, `coop_consumption_accounts`, `coop_audit`, `coop_runs` and `coop_applications`. It does not alter the existing reporting tables. The operator runtime needs SELECT/INSERT/UPDATE on enrollment and locking tables, and SELECT/INSERT on audit, runs and applications. Retain no DELETE permissions on historical module tables. Validate locking permissions on the actual local database version before deployment. No production migration was run during implementation.

## 5. External connection configuration

Configure the following variables in the PHP server environment. Keep the secret file and its directory outside both the application and Apache document roots. Restrict the directory ACL to the PHP service identity and authorized administrators. Back up keys and encrypted settings separately. Replacing an endpoint or CA in the UI requires explicit password entry.

| Variable | Purpose |
|---|---|
| `GENSTAT_COOP_ADMINS` | Comma-separated authenticated Apache `REMOTE_USER` identities allowed to administer the connection and operate the module |
| `GENSTAT_COOP_OPERATORS` | Comma-separated authenticated identities with enrollment, preview and apply access |
| `GENSTAT_COOP_SECRET_FILE` | Absolute path to the private server-side encrypted settings document |
| `GENSTAT_COOP_SECRET_KEY` | Base64-encoded, randomly generated 32-byte AES-256-GCM key from protected deployment secrets |
| `GENSTAT_COOP_ENDPOINTS` | Comma-separated approved `host:port` values. Verify the endpoint and constrain server egress independently. |
| `GENSTAT_COOP_CA_FILES` | Provisioned absolute CA paths separated by PHP's platform path separator, a semicolon on Windows |
| `GENSTAT_COOP_REVIEWED_TEMPLATE` | SHA-256 of the reviewed workbook. Changing the workbook requires reapproval and validation of enrollment area keys. |
| `GENSTAT_COOP_CLOUD_ENABLED` | Set to `1` only for an authorized, provisioned cloud connection. Omit to keep external network access disabled. |
| `GENSTAT_COOP_SCHEMA_REVIEWED` | Set to `1` only after reviewing the cloud schema, actual billing grain, correction rules and precision. |
| `GENSTAT_COOP_OWNERSHIP` | Set to `1` to permit reviewed COOP applications and reserve the COOP row against generic SQL writes. Resolve competing saved SQL ownership first. |

The UI defaults are suggestions. No connection is made when the report or modal opens. Test Connection uses a verified TLS session and `SELECT 1` without retrieving billing data. The connector verifies a TLS cipher and dedicated grants, and sets bounded client and server timeouts. The production connection defaults to MySQLi with mysqlnd. See [PHP MySQLi connection options](https://www.php.net/mysqli_options) and [MySQL server execution budgets](https://dev.mysql.com/doc/refman/8.0/en/server-system-variables.html#sysvar_max_execution_time).

The administrator workflow now requires testing the submitted settings before saving and supports removal. See [COOP connection settings update](coop-connection-settings-update.md).

## 6. Enrollment workflow

Select a workbook-derived area, type the account code, and choose the inclusive effective month. Account codes and canonical numbers are strings. The cloud lookup must resolve to one exact identity. The local repository locks enrollment changes and rejects overlapping canonical identities, including alternate codes for the same account. Deactivation closes the interval; reassignment closes it and adds a successor starting in the same effective month. Membership lookup uses the inclusive start and exclusive end. Combined Araceli/Dumaran and Cuyo/Magsaysay remain intact. The dialog lists effective memberships for the report period and supports search.

## 7. Cloud query implementation

Refresh loads effective accounts and validates the current cloud column types through schema metadata. It resolves codes in batches of 50 and separately aggregates bills grouped by canonical AcctNo for the selected BillMonth. The two queries avoid master-to-bill multiplication. The query also counts all bills and non-null KWH values. The service rejects ambiguous, missing, changed or duplicate canonical identities, missing bills, and invalid KWH. It performs no cancellation or rebill deduplication. A partial retrieval never becomes an applicable preview.

## 8. KWH aggregation logic

BCMath aggregates decimal strings at scale 12. The server stages account totals, counts, exact area totals and differences in JSON with decimal strings rather than JSON numeric doubles. If the source is NULL, missing or invalid, its status is distinct from a legitimate zero. Area sums outside Excel's 15 significant digit limit block application. Areas without an enrollment retain their existing report values. Existing workbook display formatting and formula-engine numeric precision still apply after review.

## 9. Preview and Apply workflow

Refresh creates a durable run with a 15-minute review window. The modal shows the selected period, account statuses, current values and differences, latest attempts and last successful refresh. Preview is read-only with respect to the report. Apply confirms period, totals, changed cell count and recalculation, then checks actor, template, period, enrollment snapshot, draft revision, working inputs and competing SQL ownership. A unique immutable receipt makes a repeat application of the same compatible run idempotent. If a working report changes, refresh and review it again.

## 10. Formula integration

The resolver locates the COOP label and uses the existing `report_area_headers()` and cell eligibility metadata. The current targets are B26:P26. It excludes formula cells and merged/unwritable destinations. The normal calculation engine recalculates the affected area subtotals, Q26 and R26. Restore & Recalculate uses saved local values, retains COOP overlays and skips conflicting generic SQL on the reserved row. The reference workbook is unchanged.

## 11. Draft and export integration

Apply updates the working report and marks it dirty. Save Draft persists the coordinate-keyed values and signed provenance under the normal lock and revision flow. Manual input supersedes the indicator immediately. Save Draft records manual overrides and saved COOP run/revision associations atomically in the same draft file. The SQL application receipt records the revision at apply time; the separate draft history records the resulting actual saved revision. Export follows the existing numeric overlay path, with Q26/R26 formulas and S26 preserved. It contains no enrollment data.

## 12. Security controls

The endpoint checks server-authenticated identity and role, period-bound CSRF, workbook hash and draft revisions. Configure Apache authentication at the trusted server boundary. Do not derive identity from a browser-supplied header. Connection administration requires HTTPS as reported by the trusted server. The UI never receives a stored password, encrypted secret or plaintext MySQL error. The secret is authenticated-encrypted with a separate key. The cloud connector requires the allowed endpoint, configured CA, certificate verification and an encrypted transport. The local module audits connection events, enrollment changes, refresh/review outcomes and application, with draft-side audit for saved events and manual overrides. Billing payloads are limited to reviewed identifiers/status and aggregates needed for the operation.

## 13. Tests executed and results

Use `node tools/run-tests.cjs tests/coop-consumption.php tests/coop-consumption-browser.cjs` for the module suites. The browser suite provisions a temporary local schema and synthetic account/billing data. Its isolated PHP copy replaces only the network connector, while exercising the real fixed queries, schema checks, membership, preview, permissions and apply paths. Production cloud and billing data are outside these tests.

Observed results:

- All 22 maintained unit suites passed with `node tools/run-tests.cjs --group=unit`.
- The COOP browser suite passed for role rejection, overlapping and ambiguous enrollments, reassignment across months, exact decimal sums, zero and missing bills, failed refresh, stale preview, both SQL ownership guards, repeated Apply, working-report recalculation, Save Draft, restore, manual override audit and Excel export. The dialog fit desktop Light Mode and 390 px Dark Mode.
- The native TLS suite used an isolated loopback peer and local certificates. An untrusted CA and a mismatched hostname were both rejected before authentication. The trusted control reached authentication.
- The existing compact report, base report, SQL configuration application lifecycle, SQL settings UI and SQL settings style browser suites passed.
- PHP/JavaScript syntax checks and the Tailwind production asset build passed.


## 14. Remaining deployment requirements

Provision the local migration and runtime grants, trusted Apache identity and HTTPS, a dedicated cloud SELECT-only account, a verified CA and hostname, secret ACLs and approved egress. Verify cloud account/date types, permissions, true bill grain, cancellation and adjustment policy, decimal scale, runtime TLS failure behavior and statement budgets in staging. Confirm the workbook hash. Resolve any enabled generic SQL configuration that already owns the COOP row before enabling ownership.

## 15. Known limitations

The module targets the compact report. Historical memberships are accessed by the report month. A preview is capped at 500 effective accounts per period, queried in groups of 50. Remote privileges are expected as explicit per-table SELECT grants on `master` and `billhistory`. Any schema that represents identifiers or billing month differently requires review. The local MySQLi runtime passed invalid-CA and hostname-mismatch checks. Repeat the native TLS tests and verify the actual endpoint in staging.
