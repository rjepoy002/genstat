# GENSTAT hosted MySQL TLS inspection

Inspected on November 24, 2026. The configured candidate was Remote Hosted MySQL at `paleco.net:3306`, with DNS resolving to `96.125.163.16`. The probe used the requested database name and a diagnostic username with an empty password. No saved credentials were read or sent, and no connection settings or database contents were changed.

## Root cause

The hosted MySQL endpoint has two independent certificate problems.

1. **Incomplete certificate chain.** The server sent only its leaf certificate. Verification against XAMPP's readable CA bundle failed with OpenSSL error **20**, `unable to get local issuer certificate`. The same leaf verified successfully against that bundle when the published **RapidSSL TLS RSA CA G1** intermediate was supplied as an intermediate. [DigiCert intermediate certificate](https://cacerts.digicert.com/RapidSSLTLSRSACAG1.crt)
2. **Certificate identity mismatch.** The certificate covers `*.webhostbox.net` and `webhostbox.net` and contains no IP-address SAN. After supplying the intermediate, checking `paleco.net` failed with OpenSSL error **62**, `hostname mismatch`. Checking `96.125.163.16` failed with error **64**, `IP address mismatch`. The current GENSTAT configuration passes the approved numeric IP to PDO, so the IP mismatch applies to the actual connection. Using `paleco.net` as the TLS identity would also fail with the certificate currently served.

## Native PDO error

The CLI and the running Apache PHP module both returned the same native exception before authentication.

```text
SQLSTATE[HY000] [2002] Cannot connect to MySQL using SSL
PDO errorInfo: ["HY000", 2002, "Cannot connect to MySQL using SSL"]
```

[GENSTAT's PDO connector](../lib/coop-phase1.php) catches the exception and matches the SSL error text to the hosted TLS verification message. It handles known MySQL authentication and database-access error codes before checking the TLS text. The live probe did not reach authentication, so the supplied account's password, database access, and SELECT grants remain unverified.

## Certificate and TLS observations

| Item | Observed value |
| --- | --- |
| Peer | `96.125.163.16:3306` |
| Tool | OpenSSL `s_client -starttls mysql`, with `paleco.net` sent as SNI |
| Subject | `CN=*.webhostbox.net` |
| Issuer | `RapidSSL TLS RSA CA G1`, DigiCert Inc |
| Subject Alternative Names | `DNS:*.webhostbox.net`, `DNS:webhostbox.net` |
| Certificate validity | October 19, 2026 at 00:00:00 UTC through May 5, 2027 at 23:59:59 UTC |
| Certificates sent | One leaf certificate |
| TLS 1.2 | Negotiated `ECDHE-RSA-AES256-GCM-SHA384` |
| TLS 1.3 | Server returned a protocol-version alert when the probe offered TLS 1.3 only |
| TLS 1.0 and 1.1 | The diagnostic client could not offer these versions under its local policy, so server support was undetermined |

The certificate is CA-issued and was within its validity period. The successful TLS 1.2 negotiation establishes that the server offers MySQL TLS. Certificate validation still failed.

## PHP and PDO configuration

| Setting | PHP CLI | Running Apache PHP |
| --- | --- | --- |
| SAPI | `cli` | `apache2handler` |
| PHP and PDO MySQL | `8.2.12` | `8.2.12` |
| MySQL client library | `mysqlnd 8.2.12` | `mysqlnd 8.2.12` |
| PHP OpenSSL | `3.0.11` | `3.0.11` |
| Loaded `php.ini` | `C:\xampp\php\php.ini` | `C:\xampp\php\php.ini` |
| Additional INI files | None | None |
| `openssl.cafile` | `C:\xampp\apache\bin\curl-ca-bundle.crt`, readable | Same, readable |
| `openssl.capath` | Unset | Unset |
| `curl.cainfo` | Same CA bundle path | Same CA bundle path |
| `GENSTAT_COOP_HOSTED_CA_FILE` | Unset | Unset |
| PHP sockets extension | Disabled | Disabled |

Hosted mode passes `PDO::MYSQL_ATTR_SSL_CIPHER = 'HIGH:!aNULL:!eNULL'` to request TLS and `PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT = true` for certificate and peer-identity verification. It passes `PDO::MYSQL_ATTR_SSL_CA` only when a readable, private CA file is configured. With no override, PHP OpenSSL uses its configured trust settings. The connector also requires a nonempty session `Ssl_cipher` after connection and uses a three-second PDO connection timeout. [PHP OpenSSL configuration](https://www.php.net/manual/en/openssl.configuration.php), [mysqlnd 8.2.12 TLS implementation](https://github.com/php/php-src/blob/php-8.2.12/ext/mysqlnd/mysqlnd_vio.c)

XAMPP already has the root needed for the observed chain, as shown by the offline verification using the published intermediate. An explicit PDO CA bundle is unnecessary if the server presents the complete public chain for this certificate or its replacement chains to an already trusted root. Supplying a CA bundle by itself cannot correct a peer-identity mismatch.

## Recommended secure configuration

- **Recommended on shared hosting.** Ask the provider for the exact MySQL hostname covered by the certificate and have the provider serve the leaf with all required intermediates on port 3306. Then approve that exact hostname, port, and IPs in GENSTAT. For a DNS-only certificate, the current Windows connector requires PHP sockets and a protected hosts-file mapping so PDO can verify the DNS name while the destination remains pinned. Do not infer a hostname from the wildcard alone. [GENSTAT hosted setup](coop-consumption-phase1.md)
- **If the provider supports an IP certificate.** A trusted certificate with `96.125.163.16` in its IP SAN and a complete chain would work with the existing numeric pin and require no Windows hosts-file entry. A certificate covering only `paleco.net` would require the DNS-name configuration above.
- **After TLS succeeds.** Test the real account through GENSTAT and verify that it has the required direct SELECT privileges. If the provider uses a private CA, obtain its trusted PEM through the provider and configure `GENSTAT_COOP_HOSTED_CA_FILE` outside the web root.

**Can this be fixed entirely within GENSTAT?** No for the current `paleco.net`/IP identity. The provider must confirm a supported certificate-matching MySQL hostname or provision a certificate for the requested identity. The provider should complete the served chain. GENSTAT can configure destination approval and trust after those hosting details are known. No authentication, privilege, or end-to-end login success is claimed.
