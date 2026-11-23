# COOP local database setup

Migration 005 was applied to the existing XAMPP MariaDB `genstat_db` database on loopback. Preflight verified the configured administrator's global privileges and grant option, the runtime writer's effective account, the database name, and that zero COOP tables existed.

## Backup

XAMPP `mysqldump` created a new single-transaction SQL dump at `C:\Users\r.g.padrones.paleco\AppData\Local\GENSTAT\backups\genstat_db-20261119-075742-35e4dc61.sql`, outside the web root and repository.

The dump process exited successfully. The file was readable and contained 11,704,068 bytes, the `genstat_db` schema selection, table DDL, and the dump completion marker. SHA-256 was `7358385687965f012e8e04aba080799685cada2ac6ed7a03edfafd514237e967`. These checks were performed before migration. The dump was not restored during verification.

## Migration and runtime writer

| Table created | Verified runtime writer grants |
| --- | --- |
| `coop_enrollment_state` | SELECT, INSERT, UPDATE |
| `coop_account_lock` | SELECT, INSERT, UPDATE |
| `coop_consumption_accounts` | SELECT, INSERT, UPDATE |
| `coop_audit` | SELECT, INSERT |
| `coop_runs` | SELECT, INSERT |
| `coop_applications` | SELECT, INSERT |

Verified primary keys, enrollment and audit indexes, the run history index, effective-date columns and check constraint, audit columns, the application-to-run foreign key, and enrollment-state seed `id = 1`. Verified each table through the actual runtime writer and checked its table-level privilege metadata. The reporting reader and existing reporting data were unaffected.

## API and localhost verification

`coop-consumption-api.php` returns HTTP 503 for recognized database setup failures, including missing tables, denied permissions, and connection failures. The response says “COOP database setup is incomplete. Verify the local database migration and permissions.” It omits connection details and SQL. Authorization and CSRF checks remain in place.

On the running dedicated localhost server, the report loaded and `connection-view` returned HTTP 200 with `editable: false` and `testEnabled: false`. The COOP Consumption `view` request returned HTTP 200 with empty enrollment and history. Invalid CSRF returned HTTP 403. PHP lint and `git diff --check` passed.

The connection settings status is “Configuration Requires Attention.” The localhost HTTP modal is read-only. Cloud connectivity and COOP ownership remain disabled. No production cloud connection was made.

## Automated regression tests

- `node tools/run-tests.cjs --group=unit` passed all 22 suites, including formula verification, workbook preservation, and MySQL TLS.
- `node tools/run-tests.cjs tests/coop-consumption-browser.cjs tests/base-report-browser.cjs` passed both suites. The isolated browser fixtures exercised both development dialogs, the base report, draft save and reload, formula restoration, and Excel export with formulas preserved.
