# COOP Consumption settings and local source

Settings has one **COOP Consumption** entry. Its dialog contains **Database Connection**, **Account Enrollment**, and **Consumption Preview** with area totals and refresh and apply history. The connection section opens first when credentials are missing. Enrollment opens first when credentials are configured. Connection management continues to require administrator authorization or verified localhost development access. Operators can use enrollment and preview under their existing permissions.

## Configure the local source

The editable defaults are host `127.0.0.1`, port `3306`, and database `palecxzp_pal_db`. Supply a dedicated reader username and password. Its effective grants must be SELECT on the source's `master` and `billhistory` tables only. Test Connection verifies authentication, grants, the required schema, and a SELECT 1 probe. It reads no billing records.

For direct loopback MySQL without TLS, leave the CA path blank and set the development listener's server-side `GENSTAT_COOP_LOCAL_PORTS` to the explicitly approved native MySQL TCP port, for example `3306`. The backend also requires `GENSTAT_COOP_DEV_MODE=1`, the existing verified direct development listener, a loopback client, and a database host literal of `127.0.0.1` or `::1`. Approve only ports served directly by local MySQL and keep the development listener and MySQL port free of proxies, relays, and forwarding. Hostnames do not qualify for this policy.

Credential entry, testing, and saving require trusted HTTPS. The ordinary PHP development router uses HTTP and leaves the form read-only. For credential setup use a dedicated, trusted HTTPS loopback Apache listener configured as described in the [development mode guide](coop-development-mode.md). Provision the protected external secret file location and server-side encryption key there and provide the same secret configuration to the intended development listener. Test the exact settings before saving. The saved password is encrypted, never returned to the browser, and cleared from the input after saving or closing.

Remote endpoints continue to require explicit cloud enablement, an approved endpoint and CA, and TLS with certificate and hostname verification. A failed TLS connection never falls back to plaintext. Loopback endpoints using TLS also follow that deployment policy. Local development source testing does not enable remote access.

## Report integration

COOP reads `master.AcctCode` and `master.AcctNo` to resolve enrollments, then reads `billhistory.AcctNo`, `BillMonth`, and `KWH` for the report month. Enrollment, audit, and run records remain in GENSTAT. Connection edits invalidate staged previews and leave report values intact. The existing workbook review, schema review, ownership, and reviewed Apply checks still govern report updates. Eligible changes remain limited to Summary B26:P26. A26 and Q26:S26 are preserved.

## Files changed

- `partials/sql-settings.php`, `assets/sql-settings.js` and `partials/coop-consumption.php` for the single entry and combined modal.
- `assets/coop-consumption.js` and `assets/tailwind.css` for modal state, status, and styling.
- `lib/coop-cloud.php` and `coop-consumption-api.php` for source policies, connection status, and test workflow.
- `tests/coop-consumption-browser.cjs` and `tests/query-apply-fixture.php` for synthetic loopback source and browser coverage.

## Deployment status

The running localhost listener returned HTTP 200 with the local defaults and a read-only form. A local metadata check with the existing administrator confirmed that `palecxzp_pal_db`, `master`, and `billhistory` exist and pass the schema validator. The identifiers and billing month use VARCHAR, and `KWH` is DECIMAL(19,2). A dedicated reader's credentials and grants remain to be verified.

Deployment still requires a trusted localhost HTTPS listener, an approved native MySQL port, protected secret storage, and user-supplied reader credentials. No real source credentials were provisioned, and no remote endpoint or report ownership was activated.

## Verification

- All 22 unit suites passed, including native MySQL TLS rejection of invalid trust and hostname.
- All 12 browser suites passed, covering the combined COOP modal, SQL Settings, report calculations, draft save and reload, Excel export, worksheet editing, Undo, and headers.
- An isolated, separately granted native MySQL reader was tested with synthetic data. Its credentials were saved encrypted, and the local fixture supported enrollment, refresh, reviewed Apply, and formula-preserving Excel export. A before-and-after hash verified that the fixture source rows were unchanged.
