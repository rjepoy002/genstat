# COOP Account Management

Open **Settings > COOP Consumption > Account Management**. Choose a GENSTAT area, enter a COOP account code, and select **Add Account**. GENSTAT saves the registration even when the COOP master lookup is missing, ambiguous, or unavailable. Use the pencil button to change an account's area and the trash button to remove its registration. Both actions request confirmation. Select **Filter** beside All Added Accounts to search by account code, number, or name and narrow the list by area. Apply Filter starts at page 1, Clear resets both filters, and closing the dialog keeps the active filters. The table shows 25 accounts per page.

## Storage and deployment

Apply `migrations/005-coop-account-management.sql` and then `migrations/006-coop-unresolved-accounts.sql` to a fresh GENSTAT database. For an installation with the registry already in place, apply migration 006. It makes the canonical number nullable and preserves existing registrations and both unique keys. Grant the configured GENSTAT settings writer SELECT, INSERT, UPDATE, and DELETE on `coop_registered_accounts` and INSERT on `coop_audit`. The existing report reader supplies area names from `area_master`. Run migrations and permission changes as a database administrator.

```sql
GRANT SELECT, INSERT, UPDATE, DELETE ON genstat_db.coop_registered_accounts
    TO 'genstat_settings_writer'@'127.0.0.1';
GRANT INSERT ON genstat_db.coop_audit
    TO 'genstat_settings_writer'@'127.0.0.1';
```

Registrations are global across report months. Unique binary keys preserve exact account strings and leading zeros, and a foreign key constrains assignments to existing areas. Older effective-date enrollments remain in their historical table.

Each successful change commits with its audit event. The log records the registration ID, action, applicable areas, report month, and existing web-server identity when provided. Sessions without a web-server identity use the report session actor label.

## Account lookup and billing

The module reuses the saved COOP connection. For LAN mode the reader needs SELECT on the source `master` and `billhistory` tables. COOP lookups and monthly billing queries run in read-only transactions.

Account lookup matches the exact `master.AcctCode` and resolves canonical details from records with `Status = 'A'`. Each table refresh rechecks all registered accounts, so activation can resolve an existing registration and deactivation clears stale canonical details. The registry enforces unique codes and unique non-null canonical numbers. A code that resolves to a number already registered shows **Canonical account already registered**.

Monthly retrieval binds the active GENSTAT YYYYMM period to `billhistory.BillMonth` and batches registered canonical `AcctNo` values. It uses the historical billing aggregation.

```sql
SELECT BINARY b.AcctNo AS identity, COUNT(*) AS records,
       COUNT(b.KWH) AS validRecords, SUM(b.KWH) AS kwh
FROM billhistory b
WHERE b.BillMonth = ? AND b.AcctNo IN (...)
  AND EXISTS (
    SELECT 1 FROM master m
    WHERE m.AcctNo = b.AcctNo AND BINARY m.AcctNo = BINARY b.AcctNo
      AND m.Status = 'A'
  )
GROUP BY BINARY b.AcctNo
```

The **Current month KWH** column accepts a single exact nonnegative KWH record and displays confirmed zero as `0.00`. It shows **No active account**, **Account not found**, **Multiple active account matches**, **Unable to retrieve data**, **No billing record**, or **Incomplete billing data** when applicable. Only valid amounts from active canonical accounts contribute to area totals. Missing or disputed data marks affected summary cells as incomplete. A previous total can be reused when its accounts remain eligible or their status temporarily cannot be verified.

Account actions use the current report month and its CSRF token. The server also checks the session's active report month. To switch months, close Settings, change the report month, and reopen Settings. The existing configurable SQL preview is separate from the registry.

## Verification

The isolated MariaDB browser suite covers registration during missing, ambiguous, and unavailable lookups, persistence across reloads, automatic resolution of `70-0550-0750`, canonical conflicts, missing and incomplete bills, confirmed zero, reporting month changes, and preservation of the last valid summary value.

## Files

- `migrations/005-coop-account-management.sql` creates the registry and audit table. `migrations/006-coop-unresolved-accounts.sql` supports pending registrations.
- `lib/coop-registered-accounts.php` implements the registry, read-only lookup and billing, aggregation, pagination, and audit events.
- `coop-phase1-api.php` adds account actions under existing report session and CSRF protections.
- `partials/coop-phase1.php`, `assets/coop-registered-accounts.js`, `assets/coop-phase1.js`, and `base-report.php` integrate the account section with existing Settings.
- `tests/coop-phase1-browser.cjs` and `tests/coop-phase1-fixture.php` cover the account workflow in an isolated MariaDB database.
