# COOP HTTPS API integration

GENSTAT now offers **Remote HTTPS API** in **Settings > COOP Consumption** alongside Local XAMPP, Remote XAMPP (LAN), and Remote Hosted MySQL. Production access was outside this implementation. The results below come from disposable local fixtures and a mocked API.

## Existing behavior and integration

- The COOP settings endpoint already used the active month and session CSRF token, an exact-configuration test fingerprint, and a revision check before saving. The private settings file already used AES-256-GCM encryption. HTTPS profiles now store the endpoint, encrypted API key, and timestamp of the successful check performed on save. The metadata response exposes only whether a key is present. Other connection profiles and direct SQL settings remain available when switching back.
- Account registration and area assignments stay in GENSTAT's existing registry. Adding an account uses the hosted `account_lookup` action for HTTPS mode. Monthly refresh uses the API's canonical details to revalidate the same registry entries. The existing local unique keys and conflict handling still prevent double counting. Deleting an account removes the local registration.
- Monthly refresh sends `monthly_consumption` with the active GENSTAT `ReportMonth` and up to 100 exact `AcctCode` strings per request. GENSTAT verifies the returned month and each account code before associating the result. An invalid or failed batch leaves its accounts unavailable while successful batches remain usable. KWH stays a decimal string, and GENSTAT displays a confirmed zero as `0.00`.
- The existing account table, filters, and reporting-period checks carry the new provider's values. The COOP summary consumes the same shared monthly retrieval as the table, with the existing BCMath aggregation and incomplete-total handling. HTTPS summary reuse is scoped to the saved source, report month, and registry account and area set. No new workbook write path, formula changes, or export changes were added. A future summary phase can use the shared monthly retrieval's validated KWH and status per registration.
- Direct COOP SQL remains available in direct MySQL modes. HTTPS mode hides that editor and uses only the health, account lookup, and monthly operations in the hosted contract.

## Connection security

All hosted requests originate from PHP cURL. The default approved URL is `https://paleco.net/genstat-api/coop.php`. Further exact URLs can be approved by a deployment administrator using a JSON array in `GENSTAT_COOP_HTTPS_ENDPOINTS`. The default endpoint needs no hosts-file or environment change. GENSTAT allows HTTPS on port 443, validates resolved public addresses, and pins the connection to those addresses while retaining the hostname for TLS verification. cURL verifies the certificate and hostname, disables proxies and redirects, uses the XAMPP CA bundle where configured, and enforces connection, request, and response size limits.

Test Connection requires a valid authenticated HTTP 200 JSON response with `success` and `database_connected` both true, and a compatible API 1.x version starting at 1.1. Save checks the exact URL and key against the successful test and rechecks the connection before replacing the active profile. The key is sent from the form only to GENSTAT and is stored only in its encrypted settings. A blank field keeps the stored key for the same approved URL. The bullets shown in the field are a placeholder.

## Retrieval statuses

| Hosted status | GENSTAT result |
| --- | --- |
| `found` | Canonical identity available during lookup |
| `ok` | Valid monthly KWH |
| `account_not_found` | Account not found |
| `no_active_account` | No active account |
| `multiple_active_account_matches` | Multiple active account matches |
| `no_billing_record` | No billing record |
| `incomplete_billing_data` | Incomplete billing data |
| `retrieval_failure` | Unable to retrieve data |

## Local verification

- `C:\xampp\php\php.exe tests/coop-https-client.php` exercised URL approval, invalid addresses and payloads, input and secret handling, exact decimal values, 100-account batching, and partial failures. It rejected an old report month and duplicate or unexpected response accounts.
- `node tests/coop-https-transport.cjs` used a disposable loopback HTTPS server to verify actual cURL certificate and hostname checks, address pinning, GET and POST headers, redirect refusal, and the response size limit. Loopback was used only to test the transport directly. The production destination policy rejects loopback.
- `node tests/coop-phase1-browser.cjs` exercised the settings UI and existing direct MariaDB behavior in light and dark modes. The copied test app used a mocked HTTPS transport for health successes and failures, save authorization, encrypted persistence, hidden secret metadata, switching back to local mode, existing registry preservation, lookup and deletion, and month switching. The July fixtures displayed `1407.00` and `40.00`. August returned different values. These are mocked integration results.

## Live test in GENSTAT

1. Select **Remote HTTPS API** under **Settings > COOP Consumption** and keep the suggested URL for the PALECO endpoint.
2. Enter the existing API key in the password field and click **Test Connection**. A successful result displays the API version and **Hosted Database: Connected**.
3. Click **Save Connection** and confirm the existing connection replacement prompt if shown.
4. Review the existing registrations and KWH statuses. To compare July and August, close Settings, select the reporting month in GENSTAT, and reopen Settings.

No hosting or database changes are needed for these steps. The live connection test remains to be performed by the user.

## Files

- Added `lib/coop-https-client.php` for endpoint validation, cURL, and response validation.
- Updated `lib/coop-phase1.php`, `coop-phase1-api.php`, `partials/coop-phase1.php`, and `assets/coop-phase1.js` for settings, protected profiles, and connection tests.
- Updated `lib/coop-registered-accounts.php` and `lib/coop-summary.php` to use the shared monthly retrieval.
- Added `tests/coop-https-client.php`, `tests/coop-https-transport.cjs`, and `tests/coop-https-mock.php`. Extended `tests/coop-phase1-browser.cjs`.
