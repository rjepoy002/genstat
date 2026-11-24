# COOP Consumption Phase 1

Open **Settings > COOP Consumption**. Test and save the database connection, then test and save a monthly SELECT query. Test Query validates the editor contents against the saved connection for the current GENSTAT report month. The account table retrieves monthly KWH independently.

## Local XAMPP setup

1. Start Apache and MySQL in XAMPP. Enable PDO MySQL, OpenSSL, and BCMath for the PHP used by Apache. The project also uses the Node executable and parser configured for GENSTAT Reporting Configuration.
2. Open **Settings → COOP Consumption**. With the standard Windows XAMPP document root, GENSTAT automatically creates a private directory and a random 32-byte encryption key under Windows ProgramData. No environment variables are required.
3. In **Local XAMPP**, enter the local MySQL host, port, database, username, and password. The form starts with `127.0.0.1`, port `3306`, and username `root`. If the existing MySQL account has a blank password, select **Use no password**. Then click **Test Connection** and **Save Connection**. An existing local account may be used when GENSTAT verifies the destination is `127.0.0.1` or `::1`.
4. Enter the query, click **Test Query**, review the month and total, then click **Save Query**. A query must bind `:ReportMonth` and return a decimal-compatible `KWH` column. Use the real date/month column and source identifiers for your database.

   ```sql
   SELECT Area, ConsumptionKWH AS KWH
   FROM monthly_consumption
   WHERE BillMonth = :ReportMonth
   ORDER BY Area
   ```

A saved password is shown only as a presence indicator. Leave the password field blank to keep it for the same server and username. Select **Use no password** to explicitly save a blank password. For a changed server, port, or username, re-enter the password so old credentials are not forwarded automatically.

## Private storage and manual configuration

On standard Windows XAMPP, the private directory is `%ProgramData%\PALECO-GENSTAT-COOP-<installation-id>`. The installation ID is derived from the resolved XAMPP installation path. This location is outside the document root. GENSTAT checks the Windows ownership and access rules and restricts the directory and its files to the Apache process account, SYSTEM, and Administrators. It rejects redirected paths and unsafe permissions. Keep the Apache process identity stable across restarts, or have an administrator update the protected ownership and access rules before switching accounts.

Back up the encrypted settings and original key in protected storage. A moved installation can have a different directory name. Restore the original pair into a protected location when moving the installation. If an existing key is missing or invalid, GENSTAT reports the problem and retains the encrypted settings.

Administrators can continue using an existing explicit configuration or set one for servers with a different layout. Set **both** variables in Apache’s environment and restart Apache.

| Environment variable | Value |
|---|---|
| `GENSTAT_COOP_PHASE1_FILE` | Absolute path to the encrypted settings file in a writable directory outside all web roots |
| `GENSTAT_COOP_PHASE1_KEY` | Original Base64-encoded 32-byte key for existing settings, or a Base64-encoded 32-byte cryptographically random key for a new store |

The administrator sets and maintains the ACL for an explicit directory. Restrict it to Apache and the administrators responsible for deployment. Keep keys, settings, and backups outside the repository and web root. Explicit settings take precedence over automatic initialization.

## Switching to a LAN XAMPP server

1. On the COOP laptop, have the administrator configure MariaDB to listen on its private LAN interface and allow incoming traffic on its configured MySQL port from the GENSTAT laptop only. Keep firewall protection enabled and avoid router port forwarding. Create the dedicated reader for the GENSTAT laptop’s source address, with a direct SELECT grant on the required table or view. [MariaDB LAN connection guidance](https://mariadb.com/docs/server/mariadb-quickstart-guides/mariadb-remote-connection-guide)
2. In GENSTAT choose **Remote XAMPP (LAN)**, enter the private IPv4 address or a hostname resolving to it, the port, database and reader credentials, then test and confirm the replacement. GENSTAT does not modify the database server or firewall.
3. Test and save the query for the replacement connection. The existing SQL text stays in the editor, and a changed connection must pass its own query test before saved previews resume.

LAN mode accepts loopback and private IPv4 destinations. Use it on a trusted private network. Serve the GENSTAT web UI over HTTPS when accessed by remote browsers.

## Remote Hosted MySQL

Confirm the database hostname, port, public destination addresses, and CA with the hosting provider. A website's hostname can differ from its database hostname. Enable remote MySQL access on the host for the GENSTAT server's source address and use a dedicated account with direct SELECT grants on the COOP database or its required tables.

GENSTAT reads the approved destinations from `config/coop-hosted-endpoints.php`. It includes `paleco.net:3306` at `96.125.163.16`. Deployments can override the complete map with the `GENSTAT_COOP_HOSTED_ENDPOINTS` JSON environment variable. Each key is an exact hostname or IP with a port, and each value is a list of approved public IP addresses. Use brackets around a literal IPv6 address in a key.

```json
{"paleco.net:3306":["96.125.163.16"]}
```

By default, GENSTAT resolves an approved hostname, checks its destination addresses against the approval map, and passes an approved numeric address to PDO. This requires no Windows hosts-file changes and avoids a second DNS lookup. The MySQL certificate must cover the numeric address. PHP sockets enables IPv6 hostname resolution.

For a certificate that covers only DNS names, use a provider-confirmed hostname listed on the certificate. On Windows, enable PHP sockets and add that name and its approved addresses to the server's protected hosts file. GENSTAT verifies the mapping and passes the hostname to PDO for certificate-name verification. Update the approval map and the hosts-file entry together if the provider changes the addresses.

PDO verifies the certificate against PHP's default trust store. If the host supplies a private CA, set `GENSTAT_COOP_HOSTED_CA_FILE` to the absolute path of its trusted PEM file outside the web root, readable by Apache. Restart Apache after changing its environment or PHP extensions. Choose **Remote Hosted MySQL** in the existing form and test the connection before saving it. MySQL TLS is required for this mode.

Run `node tests/coop-hosted-tls.cjs` to verify PDO TLS with a disposable MariaDB instance. The test covers a trusted CA, a hostname mismatch, an untrusted certificate, and a server that offers only plaintext.

## Bounds and persistence

The existing MySQL SELECT validator rejects comments, multiple statements, writes, unsafe functions, and SQL outside the accessible source schema. GENSTAT requires a dedicated account with direct SELECT-only privileges for LAN connections and for any non-loopback destination. Prepared queries for both local and LAN connections run in a read-only transaction. Queries have a three-second server execution limit on supported MariaDB/MySQL servers. Previews are capped at 500 rows, 20 columns, and a bounded payload. A query exceeding a limit fails rather than displaying a partial total. BCMath sums the returned decimal strings without rounding the displayed values.

The encrypted file stores the connection and the query with a revision check. Saving requires a successful recent test of the exact settings in the current GENSTAT session, confirmation when replacing saved settings, and a final server check before writing. No COOP enrollment, approval, or separate feature role is required. The endpoint uses GENSTAT’s existing session and month-scoped CSRF check.

## Migration and Phase 2

No application migration is required. This phase leaves the historic COOP tables and settings files in place. Phase 1 uses its own encrypted file and never applies a preview to the workbook. The historic enrollment workflow is available in backup commit `26c6a01` for reference.

Before connecting the preview to the Summary, agree on the result columns, area identifiers, aggregation rules, and how to handle areas with missing results or existing values. Test those rules with the actual local and LAN data sources. The Phase 1 browser tests use disposable MariaDB tables and a dedicated fixture reader. See the implementation handoff for the executed test results and LAN verification limits.
