# COOP Consumption localhost development mode

## Purpose

Temporary development access lets a developer open COOP Consumption and its existing External Database settings without a login on an explicitly configured direct localhost listener. It is off by default. The temporary audit actor is `GENSTAT_LOCAL_DEV`.

## Start a dedicated localhost server

From `C:\xampp\htdocs\genstat`, run in a dedicated PowerShell window:

```powershell
$env:GENSTAT_COOP_DEV_MODE = '1'
try {
    & 'C:\xampp\php\php.exe' -S 127.0.0.1:8087 -t . tools/coop-dev-router.php
} finally {
    Remove-Item Env:GENSTAT_COOP_DEV_MODE -ErrorAction SilentlyContinue
}
```

Open `http://127.0.0.1:8087/`. Use the supplied router. It validates the actual PHP bind address and TCP peer, rejects forwarded/proxied headers, and serves only the compact report entry points and approved static assets. It does not serve private configuration, storage, raw workbook files or arbitrary PHP files. Bind only to `127.0.0.1` or `::1`. Do not use a wildcard bind, reverse proxy, port forwarding, public tunnel, or network share for this listener.

The normal XAMPP Apache site does not acquire development access when the separate CLI listener is started. If an administrator deliberately provisions a **separate direct Apache HTTPS loopback listener** for development, they must confirm its socket is loopback-only, its certificate is trusted by the browser, and no proxy, forwarding or client address rewriting is involved. Within that vhost only, configure `GENSTAT_COOP_DEV_MODE=1` and set the trusted request environment marker `GENSTAT_COOP_DEV_DIRECT=apache-loopback` only after checking that Apache's actual TCP peer and local socket address are both loopback. Do not map a client header to that marker or put it in a shared/global environment. PHP also checks its own peer and local address. Apache without that server-side configuration fails closed. No Apache vhost was provisioned during this change.

## Local access restrictions

- An explicit opt-in, a trusted direct-listener marker and verified loopback addresses are all required.
- Recognized forwarding and identity headers cause denial on the dedicated router. The HTTP authority is checked only to reject nonlocal names, including DNS-rebinding origins. Authority headers never establish identity, listener address or eligibility.
- A relay that strips all forwarding information can look identical to a local peer at the application layer. Keep this listener inaccessible to such relays. Never approve the marker on a proxied or publicly reachable endpoint.
- A qualifying anonymous request receives the development actor. An authenticated request continues through its ordinary administrator/operator allowlists. The reserved development actor is available only through the development check.

## Features and HTTPS

The verified badge says **Development Mode**, with the tooltip “Temporary localhost access without login.” Developers can open both dialogs and use enrollment, preview, reviewed Apply and history when the existing prerequisites permit. Mode does not apply migration 005, provision secrets, activate COOP ownership, or enable outbound synchronization. Keep cloud activation off for ordinary localhost testing. Use synthetic fixtures to exercise billing paths.

The CLI listener is HTTP. Its connection settings are read-only, and the UI displays: “Local development access enabled. Configure trusted HTTPS to test or save database credentials.” Credential test, save, and removal still require server-verified HTTPS. Other controls continue to enforce CSRF, SQL validation, formula protection, the cloud endpoint/CA allowlists, stored encryption, schema review and draft checks.

COOP write, refresh and application audits record `GENSTAT_LOCAL_DEV` with a development marker. Existing timestamps and periods are retained. Area information is included where applicable, including draft-save and manual-override events. Credentials and encryption secrets stay outside the audit records.

## Disable and return to normal access

Stop the dedicated server and remove `GENSTAT_COOP_DEV_MODE` from its shell/process configuration. If a dedicated Apache development vhost was provisioned, remove its development flag and request marker and reload Apache through the approved process. Normal GENSTAT access continues to require the trusted `REMOTE_USER` identity, appropriate role membership and the existing CSRF checks. No configuration or authentication file was changed automatically.

## Test results

All five targeted suites passed, including COOP policy, the COOP browser workflow, native MySQL TLS, the base report browser workflow, and SQL Settings.

The maintained unit and isolated browser suites cover default-off behavior, explicit local opt-in, non-loopback peers and listeners, forwarding/identity headers, protected-file routing, both dialogs, enrollment, preview, reviewed Apply, CSRF, HTTP credential rejection, ordinary authorization and development audit identity. The browser fixture uses a separate temporary database schema and synthetic accounts. The native TLS fixture tests certificate and hostname rejection on loopback. The Apache marker decision is unit-tested; the production Apache server and cloud endpoint were not used to exercise development mode.
