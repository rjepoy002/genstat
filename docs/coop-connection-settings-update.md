# GENSTAT COOP connection settings update

## 1. Implementation summary

Authorized administrators can test unsaved external MySQL settings, save the exact tested configuration, replace credentials, or remove the active connection. The existing COOP connector, settings entry, enrollment workflow and report integration are reused. The HTTPS and server-controlled identity requirements still apply.

## 2. Files modified

- `lib/coop-cloud.php` for credential validation, encryption helpers, test receipts, connection versions, and locking.
- `coop-consumption-api.php` for connection view, test, save, remove, audit, and refresh/apply version checks.
- `partials/coop-consumption.php`, `assets/coop-consumption.js`, `assets/base-report.css`, and compiled `assets/tailwind.css` for the existing settings dialog.
- `tests/coop-consumption.php` and `tests/coop-consumption-browser.cjs` for backend and isolated HTTPS coverage.
- `docs/coop-consumption-implementation.md` to link this administrator guide.

## 3. Files created

- `docs/coop-connection-settings-update.md`.

## 4. UI changes

Open **Settings → External Database — COOP Consumption**. The existing COOP dialog shows editable host, port, database, username, CA path and a blank password field. The eye button reveals only the current input. The dialog hides that input again when reopened or after saving. It shows a status, **Last Successful Test**, and **Last Test Attempt**. The timestamps describe historical tests. The operational COOP route still opens its enrollment and reporting interface.

The dialog uses existing Tailwind and confirmation conventions. Requests disable the action controls. A field change invalidates the form's current test. An unauthenticated visitor sees a configuration-required message. Over HTTP, connection administration controls remain disabled even for an authenticated administrator.

## 5. Credential encryption

`coop_connection_input()` uses AES-256-GCM with the server-provisioned key and a fresh nonce, binding the connection metadata as authenticated data. Passwords entered for Test remain ephemeral and are never persisted by Test. The protected file contains ciphertext and nonsecret metadata. The settings view returns only an allowlisted subset of display metadata. It returns neither passwords, ciphertext, secret keys nor test fingerprints. The browser keeps the typed candidate only in the form and the current request. The administrator must provision a restrictive ACL outside both the project and the web root and keep the key separately protected.

## 6. Connection test workflow

Enter the proposed settings and click **Test Connection**. The server validates the administrator, HTTPS, CSRF, endpoint and CA policies, and attempts the existing verified MySQLi TLS connection. It checks the TLS transport and exact read-only table grants, then runs `SELECT 1`. No billing rows are fetched. Errors are sanitized.

A successful test creates a five-minute receipt in the administrator's PHP session, binding the actor, baseline connection version and a keyed fingerprint of the exact connection fields, effective password and CA contents. The receipt itself stays server-side. A read-only timestamp indicates when the test occurred.

## 7. Save/update workflow

After a successful test, click **Save Connection**. The server takes an exclusive lock, rereads the current configuration, validates the exact candidate and checks the receipt before replacing the file atomically. A successful save consumes the receipt. A competing save or removal changes the baseline and invalidates outstanding receipts. An expired test or changed candidate must be retested. A blank password retains the saved credential only with unchanged metadata. A changed endpoint, port, database, username or CA path requires explicit credential re-entry.

Save and remove record an audit intent before the protected file write and a completion event afterward. If the replacement fails, the old file remains in place. The filesystem and audit database are separate stores; investigate a request event without a completion event before retrying after an infrastructure failure.

## 8. Remove connection workflow

Select **Remove Connection** and confirm. The endpoint checks the administrator, HTTPS, CSRF and recently viewed configuration version under the exclusive lock. Removal atomically replaces the encrypted file with a version-only marker, removing the stored credential and metadata while invalidating receipts that refer to the previous version. Enrollment, run history, current report values and saved drafts remain available. After removal the interface reports **Not Configured**, and historical test timestamps remain visible.

## 9. Configuration versioning

Each save or removal creates a random nonsecret version independent of credentials. The version-only removal marker prevents an older receipt for an unconfigured state from becoming valid again. Legacy encrypted files can still be read. Refresh/apply snapshots include the connection version and CA contents, so rotation, replacement or removal invalidates pending previews. Apply checks the snapshot under a shared connection lock. The server does not expose the preview hash or credential fingerprint to the browser.

## 10. Cloud refresh integration

Explicit COOP refresh loads and validates the saved encrypted settings on the server. The same connector verifies the destination, CA, encrypted session and privileges. A missing or invalid connection fails refresh with a sanitized error and leaves report values intact. Report opening, changing period, credential rotation and removal do not automatically fetch cloud billing data.

## 11. Authorization requirements

Connection view and mutations require an administrator identity from the server's authenticated `REMOTE_USER`, plus the report's CSRF token. Test, Save and Remove require server-verified HTTPS, and the form checks the browser's secure context. Client-supplied identity headers are not used. The existing deployment flag still prevents outbound connections by default.

## 12. Deployment prerequisites

Provision trusted HTTPS and Apache identities, an external secret path with a restricted ACL and encryption key, the server endpoint/CA allowlists, and the dedicated cloud MySQL reader. Apply migration 005 separately and provision the required local audit/runtime grants. Verify the actual endpoint, CA chain, grants and billing schema in staging before enabling cloud access or COOP ownership. The current localhost deployment was previously found to lack trusted HTTPS, trusted identities and migration 005.

## 13. Tests executed

Use the repository runner for the unit, COOP browser, native TLS, base-report and SQL settings suites. The COOP browser harness provisions a temporary local schema, uses synthetic accounts and a mock cloud connector, and pins only its temporary HTTPS test certificate. The native TLS fixture independently verifies the real MySQLi rejection paths on loopback. No test connects to the production cloud.

- All 22 maintained unit suites passed, including receipt expiry and mismatch, encryption, invalid host/port, missing storage, and native TLS rejection of both an untrusted CA and a wrong hostname.
- The five targeted suites passed for COOP, native TLS, the COOP browser lifecycle, base report and SQL settings UI. The HTTPS fixture exercised unsaved testing, failed credentials, non-HTTPS rejection, password visibility, exact save, competing administrators, protected write failure, credential replacement, removal confirmation, stale previews, and preservation of enrollment and saved report values.
- PHP and JavaScript syntax checks and the Tailwind production build passed.

## 14. Known limitations

The module retains the provisioned CA-path interface and requires the separate one-time server policy. Current status describes the most recent applicable verification, not a live health monitor. The global attempt and success timestamps can include tests of unsaved configurations. Expired receipts require a new test, and concurrent administrators must retest after another session changes the configuration. A malformed protected settings file or missing key/path requires operator repair.
