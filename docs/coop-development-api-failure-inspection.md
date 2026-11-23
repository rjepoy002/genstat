# COOP development mode API failure inspection

The External Database settings modal fails on its first local audit-history query. The runtime writer receives MariaDB error **1142** (SELECT denied on `coop_audit`). A separate metadata check found **zero of the six migration 005 tables**. The same table probe with the local metadata profile returned **1146** (table does not exist). Both the schema and the writer grants need attention.

## Live request

Reproduced on the existing dedicated PHP CLI server at `127.0.0.1:8087`. The report returned HTTP 200 and issued a session cookie. Opening the settings modal calls `loadConnection()` in `assets/coop-consumption.js:124–125,149–151`, which posts JSON to `/coop-consumption-api.php` with `action: "connection-view"`, the current report month, workbook hash, CSRF token, and report context. The reproduction sent the required fields with the same report session cookie.

| Probe | Result |
| --- | --- |
| Valid session, CSRF token, and workbook hash | HTTP 400, JSON error “The COOP operation could not be completed. Check local deployment setup and try again.” |
| Same session with invalid CSRF token | HTTP 403, “Reload GENSTAT to renew this session.” |
| Valid CSRF token with invalid workbook hash | HTTP 400, “The workbook changed. Reopen the report.” |

The JSON responses confirm routing to the API. Authorization passed through the local development actor at `lib/coop-cloud.php:7`. The changed-hash control reached workbook validation at `coop-consumption-api.php:28`, after authorization and CSRF validation. The local writer connection also succeeded in the read-only replay.

## Exception and logs

| Field | Finding |
| --- | --- |
| Exception | `mysqli_sql_exception`, MariaDB 1142, SELECT command denied on `genstat_db.coop_audit` (account identity omitted) |
| Throw site | `lib/coop-cloud.php:143`, `coop_query()` calling `mysqli::prepare()` |
| API call site | `coop-consumption-api.php:50`, the first audit query in `connection-view` |
| Read-only SQL replayed | `SELECT created_at FROM coop_audit WHERE action='connection-tested' ORDER BY id DESC LIMIT 1` |

The running server is an XAMPP PHP process using `tools/coop-dev-router.php`. Its existing console output was unavailable from the diagnostic shell. The CLI configuration enables error logging and names `C:\xampp\php\logs\php_error_log`; that file was absent. The recent Apache error log had no matching entries, and Apache is a separate listener. The API catches the exception at line 237 without logging it. The exception above was captured by replaying the exact read-only query through the same PHP helper and writer profile.

Connection settings are read at `coop-consumption-api.php:45–48`. Missing or invalid secret storage and saved settings are handled by the inner catch at line 49, which sets a configuration-attention flag. The first audit read is outside that catch. `lib/coop-repository.php` handles COOP writes and enrollment reads; this settings request fails in the shared query helper before any repository write.

## Local schema and runtime permissions

| Table from migration 005 | Present in the runtime writer's schema |
| --- | --- |
| `coop_enrollment_state` | No |
| `coop_account_lock` | No |
| `coop_consumption_accounts` | No |
| `coop_audit` | No |
| `coop_runs` | No |
| `coop_applications` | No |

The metadata evidence shows migration 005 is missing from the effective local schema. The writer has table-level `SELECT, INSERT, UPDATE` for the existing query definitions, mappings, and history tables, plus `SELECT, INSERT` for query apply operations. Its effective grants include no COOP tables. The MariaDB writer reports the permission failure first, while the metadata check independently confirms the missing table.

## PHP CLI environment

Only variable presence was checked. A fresh XAMPP PHP CLI process from the diagnostic shell reported all five variables absent. The already-running server inherited a different environment. The router explicitly requires the development flag and successfully served the report, confirming the flag is enabled there. Presence of the other four variables in that process was not verified. Their absence from the diagnostic shell does not establish their state in the server.

| Variable | Fresh CLI | Needed to open settings |
| --- | --- | --- |
| `GENSTAT_COOP_DEV_MODE` | Absent | Required for anonymous access through the dedicated development router |
| `GENSTAT_COOP_SECRET_FILE` | Absent | No. Required for protected settings storage |
| `GENSTAT_COOP_SECRET_KEY` | Absent | No. Required for encrypted credentials |
| `GENSTAT_COOP_ENDPOINTS` | Absent | No. Used to validate approved endpoints for candidate or saved settings |
| `GENSTAT_COOP_CA_FILES` | Absent | No. Used to validate approved CA files for candidate or saved settings |

With the local audit query working, missing cloud configuration should yield a readable configuration status, including “Configuration Requires Attention” for missing secret storage or key. The HTTP development listener keeps credential operations read-only. The `connection-view` branch performs no cloud connection.

## Recommended fix and verification

1. In a separately approved local deployment, back up the runtime schema, apply migration 005, and verify all six tables and the enrollment-state seed. Provision the runtime writer's narrowly scoped COOP grants as documented in the [deployment inspection](coop-consumption-deployment-inspection.md). For the observed settings request, the specific grant needed is SELECT on `coop_audit`.
2. Improve the server error mapping at `coop-consumption-api.php:237–239`. Map known schema and permission failures to stable codes and a safe message such as “COOP database setup is incomplete. Ask an administrator to verify migration 005 and the local database permissions.” Record a correlation ID and sanitized diagnostic details server-side. Keep raw exceptions, SQL, account identities, passwords, and encryption keys out of the response.
3. In the same localhost browser session, reopen the report and inspect the settings request in DevTools. A valid `connection-view` should return HTTP 200 and a safe connection status with cloud credentials absent. Confirm the HTTP modal stays read-only and an invalid CSRF test still returns HTTP 403.
4. Use an isolated test schema to verify that a missing audit table and a denied SELECT each produce the safe deployment error. Verify the writer read and check the protected diagnostics for the matching correlation ID.

The frontend already displays the backend's JSON error through `assets/coop-consumption.js:31–43`; the generic message originates in PHP. This inspection used read-only localhost requests, schema metadata, and SELECT probes. It made no application source changes, applied no migration or grant changes, and made no production cloud connection.
