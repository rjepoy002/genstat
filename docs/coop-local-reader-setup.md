# COOP local reader setup

Created `genstat_coop_reader` for the exact MariaDB account host `127.0.0.1`. The local XAMPP server answered over loopback at port 3306 and reported MariaDB 10.4.32. No account with that username existed before provisioning. The root account and the existing GENSTAT reporting reader and writer were left unchanged. MariaDB's existing listener bindings were left in place.

## Authentication and privileges

The reader uses MariaDB's `mysql_native_password` authentication with a randomly generated 256-bit password. GENSTAT requires a password when establishing a new connection and rejects the use of root as the COOP reader. The password was provisioned through verified localhost HTTPS and saved in GENSTAT's protected AES-256-GCM credential store.

The new account's effective grants were checked through its own authenticated session. In addition to MariaDB's baseline `USAGE`, it has only:

- `SELECT` on `palecxzp_pal_db.master`
- `SELECT` on `palecxzp_pal_db.billhistory`

No INSERT, UPDATE, DELETE, other database privileges, or GRANT OPTION were present. The source data was left unchanged.

## Connection verification

The reader authenticated to the exact loopback account, ran `SELECT 1`, passed the existing COOP schema validator, and accessed both tables with zero-row metadata probes. Required fields were verified without retrieving customer or billing rows.

The real localhost COOP API accepted the approved port, database and username. **Test Connection** succeeded and returned a matching receipt while leaving the credential file absent. **Save Connection** succeeded using that receipt. The resulting file contained the encrypted credential and version. The settings API reported **Connection Successful** without returning a password.

## Settings values

| Setting | Value |
| --- | --- |
| Database Host | `127.0.0.1` |
| Port | `3306` |
| Database | `palecxzp_pal_db` |
| Username | `genstat_coop_reader` |
| TLS CA path | Leave blank for the approved loopback MySQL policy |
| Password | Leave blank to retain the saved password when keeping the same connection identity |

The connection is already saved in the dedicated HTTPS development instance. Open **Settings → COOP Consumption → Database Connection** to review it. Command-line provisioning explicitly trusted the generated local CA and verified the endpoint IP; this did not add browser trust. Follow the [localhost HTTPS setup](coop-localhost-https-setup.md) if Windows still reports a certificate warning. Cloud synchronization and COOP report ownership were not enabled.
