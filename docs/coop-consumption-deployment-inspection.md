# GENSTAT COOP Consumption deployment inspection

**Environment inspected:** XAMPP at `C:\xampp`, GENSTAT at `C:\xampp\htdocs\genstat`, local reporting schema `genstat_db`.

## 1. Executive Summary

**NO-GO for cloud access or activation.** The module exists, the localhost report and database work, and the synthetic COOP workflow passes. Local migration 005 and the corresponding runtime grants are absent. Apache has no verified GENSTAT administrator/operator authentication, and ordinary HTTPS verification fails. The COOP environment variables are absent from the inspected PHP CLI environment. The actual cloud endpoint, role, TLS chain and billing contract remain unverified.

This inspection used existing files, read-only local metadata, anonymous HTTP probes and isolated temporary test fixtures. No production cloud connection, production migration, enrollment change, credential creation or activation was performed.

## 2. Current Project Status

**VERIFIED.** All expected files exist. The implementation review included the two reference reports and the actual code below.

| Files and anchors | Observed responsibility |
|---|---|
| `migrations/005-coop-consumption.sql:1–47`; `lib/coop-repository.php:4–47` | Schema, effective enrollment, identity and global enrollment locks, audit and application receipts |
| `lib/coop-report.php:4–65`; `lib/coop-consumption.php:4–61` | Workbook target discovery, SQL ownership, signed provenance and decimal aggregation |
| `lib/coop-cloud.php:4–129`; `coop-consumption-api.php:3–200` | Authorization, encrypted external configuration, certificate verification, prepared cloud reads, preview and apply |
| `partials/coop-consumption.php`; `assets/coop-consumption.js` | Operational and administrator views, status, enrollment, preview, review and history |
| `base-report.php:145–171, 239–255`; `assets/base-report.js:135–172, 310–361`; `assets/base-report.css` | Report integration, draft/provenance persistence, indicators and dialog styling |
| `partials/sql-settings.php:7–13`; `assets/sql-settings.js:12–18, 65`; `assets/tailwind.input.css:1–9`; `assets/tailwind.css` | Settings entry, dialog handoff and compiled utilities |
| `query-settings-api.php:74–84`; `lib/query-settings.php:185–205`; `lib/query-apply-save.php:48–59` | Generic SQL ownership protection, preview exclusion and recovery guard |
| `tests/coop-consumption.php`; `tests/coop-consumption-browser.cjs`; `tests/coop-tls.cjs` | Aggregate, permission, isolated end-to-end and native TLS coverage |

No missing project file or syntax error was observed in the inspected entry points. The remaining dependencies are deployment configuration, local schema/grants and the verified production cloud contract. The COOP implementation and prior reports were already present as working-tree changes at the start of this inspection.

## 3. Local Database Status

**VERIFIED.** `lib/query-settings.php:12–35`, `qs_config()` and `qs_db()`, were exercised with the configured reader and writer after confirming both destinations were loopback. Both connected to **MariaDB 10.4.32 / genstat_db**. The administrative profile used for the metadata check and fixture harness also pointed to the loopback instance. No account names or secrets are included here.

- Reader grants were global USAGE and SELECT on the eight approved reporting tables. `qs_db('reader')` accepted the grant and MariaDB statement-timeout checks.
- The writer had SELECT/INSERT/UPDATE on `genstat_query_definitions`, `genstat_row_query_mappings` and `genstat_query_history`, plus SELECT/INSERT on `genstat_query_apply_operations`. It had no grants on COOP tables.
- The four existing generic query tables were present. This establishes deployed schema evidence for earlier migrations, rather than proving a migration ledger. The isolated suite used migrations 001–005.
- The enabled A26 configuration count was **0** at inspection time. Recheck immediately before permitting COOP ownership.

## 4. Migration 005 Status

**BLOCKED.** Independent administrative metadata inspection found **0 of 6** COOP tables in `genstat_db`. The sandbox browser fixture successfully applied the SQL to its separate randomly named schema. The deployed structures and indexes remain unverified because the tables are absent.

| Expected table | DDL inspected in migration 005 |
|---|---|
| `coop_enrollment_state` | Singleton primary key and seed row `id=1`, used as the shared enrollment lock |
| `coop_account_lock` | Binary canonical-account primary key for identity-level locking |
| `coop_consumption_accounts` | Numeric primary key, binary string IDs with leading zeros preserved, `CHAR(6)` effective dates, end-after-start check, unique canonical account/start and period index, status and actor/timestamp fields |
| `coop_audit` | Primary key, unique operation/action, actor, period, timestamp and JSON details |
| `coop_runs` | Run primary key, period/history index, actor, source and enrollment hashes, revision, status, count, expiry and JSON review snapshot |
| `coop_applications` | Run primary key and foreign key, result hash, actor, apply time and reviewed base draft revision |

`coop_change_enrollment()` in `lib/coop-repository.php:14–47` checks overlap transactionally; the unique index alone cannot prevent all interval overlap. The service stores KWH as exact decimal **strings** in JSON, not in local DECIMAL columns. Scale 12 is enforced in `lib/coop-consumption.php:4–8`. Draft history in `base-report.php:157–172` separately records the actual saved revision.

## 5. PHP Runtime Compatibility

**VERIFIED for the XAMPP CLI and isolated connector.** `C:\xampp\php\php.exe` reports PHP **8.2.12**, ZTS x64, mysqlnd **8.2.12**, OpenSSL **3.0.11**, with MySQLi, BCMath, JSON and sessions enabled. `C:\xampp\php\php.ini` is loaded, and the configured file-session directory exists. Live Apache successfully served a report and maintained a session-bound CSRF token. The authenticated Apache service's access to a chosen secret directory/CA and its effective environment remain to be checked.

`lib/coop-cloud.php:59–92`, `coop_cloud()` and `coop_query()`, use a three-second connect option, a five-second read option, disabled local infile, explicit SSL and certificate-verification flags, a configured CA and an encrypted-session check. The code sets a four-second server SELECT budget via MariaDB `max_statement_time` or MySQL `max_execution_time`. Refresh also checks an application deadline around batches. It uses native prepared statements and sanitized connection errors. Loopback TLS tests confirmed the untrusted-CA and wrong-host cases fail before authentication. The actual cloud account, server version, timeout support and metadata access were not tested.

## 6. Authentication and Authorization

**NOT CONFIGURED for GENSTAT COOP.** Apache 2.4.58 reported `Syntax OK`. PHP, SSL, environment, Basic Auth, file auth and user authorization modules are loaded. The inspected active Apache configuration and GENSTAT access rules contained no authenticated GENSTAT location. The anonymous report returned HTTP 200 without an authentication challenge. The COOP code uses Apache's `REMOTE_USER`, not a GENSTAT application login or a browser-provided identity header. No trusted administrator or operator identity could be verified.

**VERIFIED denial.** Anonymous localhost `view`, `connection-save`, `enroll` and `apply` each returned HTTP **403**, `COOP authorization required.` The connection-save probe with spoofed `Remote-User` and `X-Remote-User` headers still returned 403. The live general SQL metadata route accepted the page-issued CSRF token with its session and rejected an invalid token with HTTP **400**, `Session expired. Reload the report.` In `coop-consumption-api.php:17–29`, COOP checks the actor first and then its period-bound CSRF. An authenticated live COOP CSRF exchange was **not verified**. The synthetic browser suite verified role denials and allowed operator use with fixture identity.

**Required configuration.** Use a trusted authenticated HTTPS Apache location for the GENSTAT report and both API routes. Configure actual authenticated names in the administrator/operator allowlists. Keep service environment and identity handling under server control. Section 17 provides an example for review.

## 7. Cloud Connection Configuration

**REQUIRES CONFIGURATION.** The following names were absent in the command environment and in a name-only scan of the inspected Apache configuration files. No trusted authenticated Apache request was available to prove the web-service environment independently. Values were withheld. For missing values the format and any resulting file/ACL remain unverified. Source is `lib/coop-cloud.php:4–75`, `lib/coop-report.php:4–6`, and `coop-consumption-api.php:22–33`.

| Variable | Status and format | Purpose |
|---|---|---|
| `GENSTAT_COOP_ADMINS` | Missing; required for connection administration; comma-separated exact identities | Administrator authorization |
| `GENSTAT_COOP_OPERATORS` | Missing; required for separate operator access; comma-separated exact identities | Operator authorization |
| `GENSTAT_COOP_SECRET_FILE` | Missing; required; private path outside both roots | Encrypted connection settings |
| `GENSTAT_COOP_SECRET_KEY` | Missing; required; strict Base64 decoding to 32 bytes | AES-256-GCM key |
| `GENSTAT_COOP_ENDPOINTS` | Missing; required; comma-separated approved `host:port` entries | Exact outbound destination allowlist |
| `GENSTAT_COOP_CA_FILES` | Missing; required; platform-path-separated CA file list | Allowed TLS trust anchors |
| `GENSTAT_COOP_REVIEWED_TEMPLATE` | Missing; required for billing; SHA-256 matching the approved source | Mapping review gate |
| `GENSTAT_COOP_CLOUD_ENABLED` | Missing; optional while disabled; exact `1` enables the connector | Network activation gate |
| `GENSTAT_COOP_SCHEMA_REVIEWED` | Missing; required before billing; exact `1` attests review | Schema/business activation gate |
| `GENSTAT_COOP_OWNERSHIP` | Missing; optional while disabled; exact `1` enables reviewed apply and reserves the row | Ownership gate |

No configured COOP secret file exists to assess against Git or inspect for ACLs. `coop_secret_path()` rejects a symlink and verifies that the resolved directory is outside the project and Apache document root. Source/config deny rules and the project ignore rules are supplemental controls. Keep the actual file outside the repository and inspect its Windows ACL as the service identity before loading credentials. Do not add the encryption key or password to a command line or tracked config.

## 8. External MySQL Requirements

**NOT VERIFIED.** The suggested host `paleco.net`, port 3306 and database `palecxzp_pal_db` are defaults in `coop_connection_settings()`, not an established endpoint. `coop_connection_input()` requires an allowlisted destination, approved readable CA path and non-root username. `coop_cloud()` checks exact SELECT grants on the approved tables, allowing global USAGE, and rejects broader grants and grant delegation. Admin Test Connection requires HTTPS and performs only `SELECT 1` after connection checks.

Obtain the following from the hosting administrator before any authorized connection:

- Actual database DNS hostname, port, schema, server product/version, approved client egress IP and firewall policy.
- Certificate name and issuing CA chain, certificate rotation contact and a securely provisioned CA file.
- Dedicated account restricted to SELECT on the exact required objects `master` and `billhistory`. Confirm the account can query required metadata and the server can enforce the statement deadline.
- Verified column names and types for AcctCode, AcctNo, BillMonth and KWH, account-code case/leading-zero rules, canonical identity uniqueness, billing grain, adjustment/cancellation handling, valid NULL policy, KWH precision and index coverage.
- An authorized staging endpoint and synthetic records for reviewing the actual contract before changing any production flag.

## 9. Billing Query Verification

**VERIFIED in source and synthetic data only.** `lib/coop-cloud.php:90–129`, `coop_query()`, `coop_cloud_schema()`, `coop_master()` and `coop_bills()`, first resolve AcctCode to AcctNo, then separately group bills by binary canonical AcctNo with the selected BillMonth bound. They use parameterized batches of 50, generated placeholder counts and fixed SELECT statements. Using two queries avoids multiplying bills through duplicate master rows. Text/binary identifiers retain leading zeros; the schema gate requires supported identifier types and exact numeric KWH. No cancellation/adjustment filtering or `SUM(DISTINCT KWH)` is present.

`lib/coop-consumption.php:4–61`, `coop_aggregate()`, checks identity and duplicate canonical account, differentiates no bill from a retrieved zero and treats a NULL billing entry as invalid. It sums decimal strings with BCMath. `coop-consumption-api.php:86–140` uses a read-only cloud transaction, stores complete or failed outcomes, and stages no report edits during refresh. Incomplete runs cannot pass apply. Actual billing grain and cross-record corrections remain **not verified**.

## 10. Summary Integration Verification

**VERIFIED.** Workbook extraction used `excel-files/reference/GENSTAT_BASE.xlsx` and the current resolver. `Jul26!A26` is COOP Consumption; all 15 inputs resolve to **B26:P26**. The resolver returned B Main, C Aborlan, D Narra, E Quezon, F Brooke's Point, G Roxas, H Taytay, I Balabac, J Araceli/Dumaran, K San Vicente, L El Nido, M Cagayancillo, N Cuyo/Magsaysay, O Agutaya and P Rizal. Both combined areas remained intact.

- `lib/report-areas.php:8–27` and `lib/coop-report.php:7–64` derive the location and enrollment keys from workbook metadata and normalized labels. They reject ambiguous or ineligible targets.
- `coop-consumption-api.php:142–185` restricts changes to resolved eligible coordinate-keyed inputs. A26, Q26/R26 and comparator S26 stay outside the apply target set. The browser fixture verified recalculation and numeric export with the Q/R formulas retained, including the shared Excel formula at Q26.
- `assets/base-report.js:310–361`, `base-report.php:145–172` and `lib/base-report-export.php:30–70` integrate working state, separate Save Draft and Excel export. Restore retains accepted values and does not invoke the cloud reader. Ownership guards also cover generic SQL apply, recovery and preview.
- Current reference workbook SHA-256 is **`752c1ad06bd770ea749ef7925a49adbc725ee81b3a7dd6342f5b1585253c3dce`**. This is a measured value, not approval. Set the reviewed hash only after workbook review. No ownership flag was changed.

## 11. UI Integration Verification

**VERIFIED on anonymous localhost and in the isolated browser.** Live HTML served the COOP and External Database settings entries, native dialog, enrollment and history sections, refresh/apply buttons and loading status. `partials/sql-settings.php:7–13`, `assets/sql-settings.js:12–18, 65`, `partials/coop-consumption.php`, and `assets/coop-consumption.js:15–153` implement the handoff, workbook-derived area options, current read-only period, preview, separate confirmation and error/loading behavior. Compiled `assets/tailwind.css` contains the relevant dark and dialog utilities. The isolated browser exercised the authorized view, spreadsheet integration, desktop Light Mode and 390 px Dark Mode. Administrator settings cannot be used on the current anonymous, untrusted-HTTPS localhost.

## 12. Test Results

| Check | Observed result |
|---|---|
| PHP lint on the COOP endpoint, libraries, partials and related report/settings entry points; Node syntax check on the COOP client | Passed |
| `node tools/run-tests.cjs --group=unit` | Passed, 22 of 22, including native loopback TLS |
| `node tools/run-tests.cjs tests/coop-consumption.php tests/coop-consumption-browser.cjs` | Passed, 2 of 2 |
| Native TLS fixture | Untrusted CA and wrong hostname rejected before authentication; trusted control reached authentication |
| Verified HTTPS against localhost | Failed with curl 60, `SEC_E_UNTRUSTED_ROOT (0x80090325)` |
| Authenticated Apache administrator/operator action and live COOP CSRF round trip | Skipped; trusted identity and HTTPS are missing |
| Live cloud DNS endpoint, credentials, schema, read grants, statement budgets and billing data | Skipped by inspection scope |

Before running the browser suite, the configured reader and fixture administrator host were confirmed loopback. `tests/query-apply-fixture.php:3–35` uses a random schema and account with guarded cleanup. It reads only the original SQL configuration digest and local reporting reference data; synthetic customer account/bill rows are in the isolated schema. The only stubbed COOP connector is in the temporary PHP copy. `tests/coop-tls.cjs:19–69` listens on ephemeral loopback and uses temporary certificates. No completed automated suite failure was observed.

## 13. Security Findings

- **High — HTTPS.** Apache SSL is listening. `C:\xampp\apache\conf\extra\httpd-ssl.conf:121–144` uses a default `www.example.com:443` vhost and the bundled certificate. Static certificate inspection shows a self-signed localhost CN, no SAN and expiration on **2019-11-08**. Verified HTTPS fails. Install a trusted, hostname-matching certificate and validate HTTPS without bypasses.
- **High — Identity.** The role checks in `lib/coop-cloud.php:4–11` require server-authenticated names. Protect both the report entry points and the APIs at the trusted Apache boundary before deploying credentials. Test separate admin/operator identities and unauthenticated rejection.
- **High — Secret handling.** `lib/coop-cloud.php:13–57` encrypts the password with AES-256-GCM and an external key. `coop-consumption-api.php:42–72` removes stored secrets from the returned view and requires HTTPS for writes/tests. A real secret path, file permissions and service-account access cannot be verified until provisioning.
- **High — Local exposure.** Loopback database access worked, but listeners were also observed on wildcard interfaces (Apache 80/443 and MySQL 3306). Actual firewall reachability was not tested. Restrict listener or firewall scope before provisioning authenticated access.
- **High — Billing integrity.** Approve account uniqueness, period type, duplicates, cancelled/rebilled rows and KWH precision before enabling billing. The connector deliberately fails closed on unsupported schema or grants.

## 14. Deployment Readiness Table

READY entries for application functions indicate isolated verification. They do not authorize live use.

| Component | Status | Findings | Required Action |
|---|---|---|---|
| 1. COOP implementation files | READY | All expected entry points and assets exist; syntax checks passed | Retain reviewed source and build |
| 2. Local database connection | READY | Both loopback MariaDB roles connect to genstat_db | Confirm expected service scope before changes |
| 3. Migration 005 | BLOCKED | Zero of six deployed tables | Back up, review and apply under separate approval |
| 4. Runtime database grants | BLOCKED | Existing writer grants exclude COOP tables | Provision narrowly scoped local grants after migration |
| 5. PHP extensions | READY | XAMPP CLI checks and native TLS fixture passed | Verify authenticated Apache worker deployment environment |
| 6. Apache authentication | NOT CONFIGURED | No trusted GENSTAT COOP identity | Configure HTTPS server authentication and role allowlists |
| 7. HTTPS configuration | FAILED | Verified localhost HTTPS rejects bundled certificate | Replace certificate and align hostname/SAN and trust |
| 8. Cloud endpoint configuration | NOT CONFIGURED | Suggested defaults only | Obtain actual DNS endpoint, port and approved egress |
| 9. Secret storage | NOT CONFIGURED | No external path, key or ACL to inspect | Provision outside application/docroot and protect key separately |
| 10. TLS CA certificate | NOT CONFIGURED | No cloud CA allowlist | Obtain chain and verify against actual endpoint in staging |
| 11. Cloud read-only account | NEEDS VERIFICATION | No cloud account checked | Obtain restrictive SELECT grants and verify metadata access |
| 12. Cloud schema verification | NEEDS VERIFICATION | Only the synthetic schema passed | Review actual types, bill grain, uniqueness and correction rules |
| 13. Workbook hash approval | NOT CONFIGURED | Current SHA-256 measured; approval absent | Review template and set exact hash |
| 14. COOP ownership setting | NOT CONFIGURED | Off; zero active A26 SQL mappings in local snapshot | Recheck conflict and activate only after signed-off verification |
| 15. Enrollment functionality | READY | Passed with effective-dated synthetic memberships | Requires deployed schema and verified cloud identity |
| 16. Consumption preview | READY | Synthetic exact totals, missing/zero and failure paths passed | Verify staging billing contract |
| 17. Summary Apply | READY | Isolated stale/conflict, target and repeat-apply tests passed | Enable only after controls are approved |
| 18. Save Draft | READY | Isolated snapshot persistence and override audit passed | Verify approved operational workflow |
| 19. Excel export | READY | Isolated numeric export and protected formulas passed | Check staging workbook output |
| 20. Automated tests | READY | 22 unit and 2 targeted suites passed | Repeat after provisioning changes |

## 15. Blocking Issues

1. Migration 005 and COOP runtime grants are absent.
2. Localhost HTTPS fails trust validation; an authenticated GENSTAT administrator or operator is unavailable.
3. COOP endpoint allowlist, CA, key, secret file and workbook approval are absent from inspected configuration.
4. The actual cloud endpoint, privileges, schema and billing rules lack verification. Cloud and ownership activation are blocked.

## 16. Recommended Deployment Sequence

1. Review the report and obtain explicit approval for local-only changes. Back up the local schema, check for partial migration artifacts and review runtime grants.
2. Provision trusted HTTPS and Apache authentication. Independently prove administrator/operator authorization and CSRF behavior on the local deployment.
3. Apply migration 005 during the approved local window and grant only the local writer capabilities needed. Verify all tables, indexes and the singleton seed.
4. Provision protected secrets, approved egress, reviewed workbook hash and cloud CA. Keep cloud/ownership flags off until the staging prerequisites are met.
5. Obtain the verified cloud data contract and perform a separately authorized staging connection and synthetic billing review. Confirm no generic A26 ownership conflict.
6. Review results before authorizing cloud enablement or COOP ownership. Preserve the existing report/draft fallback on refresh failure.

## 17. Exact Commands Required

**Examples for a later approved deployment. The commands below were not executed for deployment.** Assume the working directory is `C:\xampp\htdocs\genstat`. Replace the account placeholder and verify the port against the approved loopback profile. Supply the password interactively. Before running SOURCE, confirm the selected schema, ensure none of the six migration tables exists, and have a restorable local backup. MariaDB DDL can partially commit; review partial application rather than rerunning over existing objects.

```powershell
& 'C:\xampp\mysql\bin\mysql.exe' --host=127.0.0.1 --port=3306 --user='<approved-local-migration-account>' --password genstat_db
```

Run inside the MySQL client:

```sql
SELECT DATABASE(), VERSION();
SELECT TABLE_NAME FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN
 ('coop_enrollment_state','coop_account_lock','coop_consumption_accounts',
  'coop_audit','coop_runs','coop_applications');
SOURCE C:/xampp/htdocs/genstat/migrations/005-coop-consumption.sql;
SELECT id FROM coop_enrollment_state WHERE id=1;
SHOW INDEX FROM coop_consumption_accounts;
SHOW INDEX FROM coop_audit;
SHOW CREATE TABLE coop_applications;
```

After verifying all six tables, grant SELECT/INSERT/UPDATE only on `coop_enrollment_state`, `coop_account_lock` and `coop_consumption_accounts` to the existing local writer identity, and SELECT/INSERT only on `coop_audit`, `coop_runs` and `coop_applications`. Confirm the writer can take the required locking reads on the deployed local engine. Do not change the general reporting reader.

For a planned HTTPS Apache vhost, install the trusted certificate and private key, set its `ServerName` to the certificate's approved SAN, and redirect HTTP before issuing any Basic Auth challenge. Example protection inside the trusted HTTPS vhost:

```apache
<Directory "C:/xampp/htdocs/genstat">
    AuthType Basic
    AuthName "GENSTAT"
    AuthBasicProvider file
    AuthUserFile "C:/protected/genstat/users.htpasswd"
    <RequireAll>
        Require ssl
        Require valid-user
    </RequireAll>
</Directory>
```

Choose the private path and ACL intentionally. Provision approved identities using a trusted server authentication mechanism. If using Apache Basic Auth and a pre-provisioned password file, update it interactively with `C:\xampp\apache\bin\htpasswd.exe C:\protected\genstat\users.htpasswd <approved-identity>`. Keep the role lists and secrets in the trusted PHP service environment. Do not use HTTP headers for role assignment outside the isolated test harness.

```powershell
& 'C:\xampp\apache\bin\httpd.exe' -t
& 'C:\xampp\apache\bin\httpd.exe' -t -D DUMP_VHOSTS
icacls 'C:\protected\genstat'
Get-FileHash 'excel-files/reference/GENSTAT_BASE.xlsx' -Algorithm SHA256
curl.exe --silent --show-error --output NUL --write-out '%{http_code}' 'https://<approved-host>/genstat/'
node tools/run-tests.cjs --group=unit
node tools/run-tests.cjs tests/coop-consumption.php tests/coop-consumption-browser.cjs
```

An unauthenticated challenge over validated HTTPS is expected once authentication is installed. Test trusted identities separately via an approved browser session, without exposing passwords or checking in secrets.

## 18. Final Go/No-Go Recommendation

**GO for planning and isolated testing. NO-GO for localhost-to-cloud connectivity, cloud billing access or COOP ownership activation.** Local code, calculation, drafts, export and isolated certificate handling passed verification. The required local schema, trusted HTTPS/identity, cloud credentials and reviewed data contract must be provisioned and verified under separate approval first.
