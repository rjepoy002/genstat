# GENSTAT Settings security PIN

The Settings gear opens a six-digit PIN prompt. The existing Settings modal opens after PHP verifies the PIN. The prompt supports masked input, keyboard navigation, full-PIN paste, automatic submission, and a lockout countdown. The new **Security** tab offers **Change PIN**.

## Server authorization

- PHP treats the PIN as a string and stores only a `password_hash()` hash. It compares guesses with `password_verify()`. An uninitialized installation keeps Settings locked.
- Five incorrect attempts lock Settings verification for five minutes. The singleton database row stores the counter and lockout deadline, and a row lock serializes simultaneous attempts. Browser refreshes, new tabs and new sessions share this state. Success or lockout expiration resets the counter.
- A successful verification rotates the PHP session ID and issues a Settings authorization token to the current page. The client keeps that token in memory. Sensitive requests require the token and the existing month-scoped CSRF proof. The server reads the PIN version on each authorization check.
- Closing Settings revokes the grant through the backend. A fresh report page revokes a previous grant in the same session. Navigation also attempts immediate revocation, with the 15-minute server-side idle deadline providing independent enforcement. Changing or administratively resetting the PIN increments its version and invalidates grants across sessions.
- Settings requests require HTTPS outside verified loopback development. Session cookies use HttpOnly, SameSite Strict, and Secure on HTTPS. TLS termination must expose HTTPS to PHP through trusted server configuration. The application does not use client-supplied forwarding headers as proof of TLS.
- Security events are written as structured `GENSTAT_SETTINGS` entries to the configured PHP error log. Entries carry UTC time, event type, request IP when available and a configuration identifier for configuration edits. The shared PIN does not identify individual administrators. CLI events have no request IP. Retain and protect both the web and CLI PHP logs.

## Protected endpoints

| Endpoint | Coverage |
| --- | --- |
| `query-settings-api.php` | Reporting and SQL metadata, tests, mappings, SQL calculation formulas, history, apply, disable, and recovery. This also covers editing saved reporting SQL that references KWH Diff allocation. No separate allocation-write endpoint was found. |
| `coop-phase1-api.php` | COOP settings, direct MySQL and HTTPS credentials, test/save, query configuration, previews, and local account management. Normal COOP Summary retrieval uses the internal provider and remains accessible with the report. |
| `settings-security-api.php` | PIN change, Settings activity/status, and appearance-save authorization require an active Settings grant. Initial PIN verification and lockout status require the report session and CSRF proof. Revocation requires the matching grant token. |

Appearance remains a per-browser preference. Authorization gates saving it through the Settings interface. Locally editing browser preferences does not change a server configuration. Report formula editing, normal draft saves and restoration, automatic calculation, reporting month selection, Excel export, Recovered Sales and report-side SQL reads retain their existing report workflows.

## Migration and database privileges

Apply [migration 007](../migrations/007-settings-security.sql) to the target GENSTAT schema using an administrator account. The SQL creates an empty `genstat_settings_security` table. It does not seed a PIN. Grant the configured Settings writer SELECT, INSERT, and UPDATE only on this new table. For the standard local schema and writer, the grant is:

```sql
GRANT SELECT, INSERT, UPDATE ON genstat_db.genstat_settings_security
TO 'genstat_settings_writer'@'127.0.0.1';
```

Use the actual database name and account host on production. The backend uses PHP 8.2+ with mysqli and the existing InnoDB configuration. For XAMPP, a database administrator can apply the SQL by opening the local MariaDB terminal and running `USE genstat_db;` followed by `SOURCE C:/xampp/htdocs/genstat/migrations/007-settings-security.sql;`. On production, apply the same file to the configured GENSTAT schema through the server's normal database administration process. Confirm web PHP and CLI use the intended protected database configuration before initializing the PIN. No migration or PIN was applied to the working installation during development.

## Initial setup and recovery

From the project directory, an authorized server administrator runs [the CLI utility](../tools/settings-pin.php) in `setup` mode once. To recover a forgotten PIN, use `reset` mode. Both accept input only through standard input from a hidden prompt. Do not place a PIN in a command argument or a literal shell command.

### Windows XAMPP PowerShell

```powershell
$mode = 'setup'   # Use 'reset' for administrator recovery.
$first = Read-Host 'New six-digit PIN' -AsSecureString
$second = Read-Host 'Confirm PIN' -AsSecureString
$firstPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($first)
$secondPtr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($second)
try {
    @(
        [Runtime.InteropServices.Marshal]::PtrToStringBSTR($firstPtr)
        [Runtime.InteropServices.Marshal]::PtrToStringBSTR($secondPtr)
    ) | & C:\xampp\php\php.exe tools/settings-pin.php $mode
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($firstPtr)
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($secondPtr)
    $first.Dispose()
    $second.Dispose()
}
```

### Production terminal with Bash

```bash
set +x
mode=setup  # Use reset for administrator recovery.
IFS= read -rs -p 'New six-digit PIN: ' pin; printf '\n'
IFS= read -rs -p 'Confirm PIN: ' confirmation; printf '\n'
printf '%s\n%s\n' "$pin" "$confirmation" | php tools/settings-pin.php "$mode"
unset pin confirmation
```

Use a PIN that the application accepts as uncommon. A reset clears the lockout and invalidates previously granted Settings access. The setup utility returns 404 through the web server.

## Files and verification

- Added `migrations/007-settings-security.sql`, `lib/settings-security.php`, `settings-security-api.php`, `tools/settings-pin.php`, `partials/settings-security.php`, and `assets/settings-security.js`.
- Updated `base-report.php`, `index.php`, `partials/sql-settings.php`, `query-settings-api.php`, `coop-phase1-api.php`, and `lib/query-apply-save.php`. SQL Apply & Save receipts retain their existing ownership across session-ID rotation.
- Updated `assets/base-report.js`, `assets/sql-settings.js`, `assets/coop-phase1.js`, `assets/report-appearance.js`, `assets/tailwind.input.css`, and `assets/tailwind.css`.
- Added `tests/settings-pin-fixture.php`. Updated `tools/run-tests.cjs`, `tests/base-report-compact.cjs`, `tests/coop-phase1-browser.cjs`, `tests/coop-phase1-fixture.php`, `tests/query-apply-save-browser.cjs`, and `tests/query-apply-fixture.php`. The PIN fixtures use disposable schemas.

PHP and JavaScript syntax checks and the Tailwind build passed. The isolated COOP browser suite covered initialization, leading-zero verification, incorrect input, paste and focus behavior, the five-attempt database lockout, refresh persistence, unauthorized API calls, close/reopen, idle expiration, PIN change and version invalidation, and administrator reset. It also exercised direct and mocked HTTPS COOP, report-month changes, Summary retrieval and Excel export without a live hosted connection. The compact appearance, SQL Settings UI, and SQL Apply & Save lifecycle suites passed. Idle and lockout expiration used fixture timestamps to avoid waiting in real time.
