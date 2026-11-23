# COOP localhost HTTPS setup

The dedicated development endpoint is **https://127.0.0.1:8443/base-report.php**. It is running as a separate XAMPP Apache instance under the current Windows user. The existing XAMPP Apache configuration, processes on ports 80 and 443, and PHP development listener on port 8087 were left in place.

## Certificate trust and approval

The endpoint successfully negotiated TLS 1.3 with a client that explicitly trusted the generated CA and verified the server IP. The certificate includes `IP Address:127.0.0.1` and Server Authentication usage, and expires on February 17, 2027 UTC. The expired XAMPP certificate was left in place for the existing sites.

**Windows browser trust is pending approval.** The generated CA is absent from both Windows root stores, and a default Windows TLS probe rejected it as untrusted. Do not enter credentials until the browser accepts the endpoint without a certificate warning.

The approval action is to trust the public certificate at `%LOCALAPPDATA%\GENSTAT\coop-https\ca.crt` in the intended Windows user's **Trusted Root Certification Authorities** store. Verify its SHA-256 fingerprint first:

```text
B6:F3:C1:7A:01:3A:1B:D6:26:B1:F1:F3:31:0E:A8:FE:E0:80:8E:08:8F:50:93:29:12:A2:DF:4E:BA:CE:3C:FD
```

After the certificate change is approved under local policy, the authorized user can run:

```powershell
certutil.exe -user -addstore Root "$env:LOCALAPPDATA\GENSTAT\coop-https\ca.crt"
```

Reopen Microsoft Edge, navigate to the endpoint, and confirm the certificate is accepted for `127.0.0.1` without an exception. Open **Settings → COOP Consumption → Database Connection**. The current-user trust change authorizes this CA to issue certificates trusted by that user. The CA and server private keys stay in the protected directory.

## Apache and protected settings

The standalone configuration is `%LOCALAPPDATA%\GENSTAT\coop-https\httpd.conf`. It loads XAMPP's existing Apache PHP module, binds exclusively to `127.0.0.1:8443`, and allows TLS 1.2 and 1.3. Its document root is empty. Explicit aliases expose the compact report, its two API entry points, and allowlisted asset types. Directory overrides, indexing, and proxy modules are absent from this instance. Existing port 80/443 configuration files were not edited.

The vhost explicitly enables `GENSTAT_COOP_DEV_MODE=1` and sets the existing `apache-loopback` request marker only for an actual loopback TCP peer. The listener and vhost are bound to loopback, and PHP independently checks the local address, remote address, hostname, and forwarding and identity headers. Anonymous verified requests use `GENSTAT_LOCAL_DEV`. The development instance uses an isolated PHP session directory and a separate Secure, HttpOnly, SameSite=Strict session cookie.

The same private directory contains the protected Apache include for the random 32-byte AES-256-GCM key and the independent `connection.json` destination. The directory disables ACL inheritance and allows access to the current Windows account, which runs the Apache process. The private key and encryption include inherit that restricted ACL. PHP resolved both secret settings successfully. The existing application writes encrypted credentials atomically only after a matching connection test. There is no live saved connection file yet.

## Local database and credentials

The vhost explicitly approves port `3306` for the existing development-only loopback source policy. The server reports MariaDB 10.4.32, port 3306, and the XAMPP MySQL base directory. Metadata confirms `master.AcctCode` and `master.AcctNo`, `billhistory.AcctNo`, `billhistory.BillMonth` as VARCHAR(6), and `billhistory.KWH` as DECIMAL(19,2). The existing schema validator passed. `master` has an `AcctCode` index and a primary key on `AcctNo`; `billhistory` has a composite `(AcctNo, BillMonth)` index. Metadata confirms the month field can hold YYYYMM; there is no CHECK constraint confirming stored values, and this inspection did not query customer records.

After browser trust is verified, keep the defaults `127.0.0.1:3306` and `palecxzp_pal_db`. Leave the TLS CA path blank for approved development loopback MySQL. Enter the approved dedicated reader credentials, use **Test Connection**, then **Save Connection**. The reader needs SELECT on `master` and `billhistory` only. This setup did not create or change any real database account, save any live source credentials, or refresh billing. The remote cloud and COOP ownership flags remain disabled. Enrollment, preview and Apply continue to require their applicable deployment review and ownership checks.

## Verification

- The listener appeared only on `127.0.0.1:8443`. A connection through the machine's non-loopback IPv4 address failed, and no Windows port proxy entries were configured.
- With the generated CA supplied explicitly, the report, COOP settings, and COOP view returned HTTP 200. The server reported Development Mode, an editable form and enabled connection testing. The settings response contained no password. Requests for configuration, storage, source, workbook and Git paths were denied.
- A forwarded request and an invalid CSRF token returned HTTP 403. The dedicated HTTP listener rejected credential testing, and anonymous access to the COOP API on normal Apache still returned HTTP 403.
- All 22 unit suites and all four targeted browser suites passed. The isolated COOP browser suite was rerun after adding native loopback checks for incorrect passwords and excess write privileges. It also verified secure test/save, encrypted storage, credential replacement and removal, preserved enrollment, reviewed Apply, drafts, and Excel export. No actual source credentials or customer data were used in those tests.
- The remaining live checks are browser trust and a connection test with the administrator's approved source reader. No live credentials were submitted.

## Disable the development endpoint

Stop only the custom Apache instance. Its parent PID is recorded in `%LOCALAPPDATA%\GENSTAT\coop-https\httpd.pid`. The following checks the process name and configuration path before stopping that parent and its Apache child processes:

```powershell
$coopDir = Join-Path $env:LOCALAPPDATA 'GENSTAT/coop-https'
$coopConfig = Join-Path $coopDir 'httpd.conf'
$coopPid = [int](Get-Content -LiteralPath (Join-Path $coopDir 'httpd.pid'))
$coopProcess = Get-CimInstance Win32_Process -Filter "ProcessId=$coopPid AND Name='httpd.exe'"
if (!$coopProcess -or !$coopProcess.CommandLine.Contains($coopConfig)) { throw 'Dedicated Apache process not found' }
$coopChildren = Get-CimInstance Win32_Process -Filter "ParentProcessId=$coopPid AND Name='httpd.exe'"
Stop-Process -Id $coopPid
$coopChildren | ForEach-Object { Stop-Process -Id $_.ProcessId -ErrorAction SilentlyContinue }
```

To start it again, run `C:\xampp\apache\bin\httpd.exe -f` with the standalone configuration path. This instance was not registered as a service. If certificate trust was installed and is no longer needed, remove this CA only from the same current-user root store using thumbprint `B8F5AEDE744D6F74CC95103468639150EAD6B3FE`.
