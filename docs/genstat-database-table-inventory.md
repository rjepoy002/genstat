# GENSTAT database table inventory

Inspected November 24, 2026 in `C:\xampp\htdocs\genstat`. The local reporting and settings configurations both point to loopback `genstat_db`, running MariaDB 10.4.32. The schema was inspected with read-only SQL. Row counts are exact at inspection time. Allocated data and index sizes come from MariaDB metadata and are approximate.

## Executive summary

The local database contains **20 base tables**. Six support application state and eight supply active reporting, for **14 tables required to reproduce the current local deployment**. Five belong to the retired COOP enrollment workflow. One, `sales_sync_status`, has no identified current application reference and requires an upstream ownership check. There are no missing local runtime tables and no local application views.

The checked-in hosted COOP API reads two external tables, `master` and `billhistory`. Their absence from `genstat_db` is expected. No hosted API call, production database connection, or database change was made.

I searched executable PHP, SQL, JavaScript, migrations, setup utilities, and fixture code. I parsed the saved local reporting SQL and historical query snapshots without running their queries. I checked the reference workbook's actual cell classifications and compared source references against the live local catalog, exact counts, keys, and indexes. The retired COOP tables were cross-checked against executable source in local Git commit `26c6a01`.

## 1. Local GENSTAT tables

**A** = active application, **B** = active reporting source, **C** = external, **D** = legacy, **E** = potentially unused, **F** = missing, **G** = test-only, **H** = unverified. The inventory uses one primary classification per table. External tables have an additional live-verification limitation. Operations describe application or fixture use and schema declarations, not operations performed for this inspection.

| No. | Database | Table | Class | Purpose | Module | SQL operations | In local schema | Records | Required for live | Source |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| 1 | `genstat_db` | `coop_registered_accounts` | A | Registered codes, canonical details and GENSTAT area | COOP Account Management and summary | SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER | Yes | 30 | Schema and registrations | [lib/coop-registered-accounts.php:53](../lib/coop-registered-accounts.php#L53), [lib/coop-registered-accounts.php:120](../lib/coop-registered-accounts.php#L120), [migrations/005-coop-account-management.sql:1](../migrations/005-coop-account-management.sql#L1), [migrations/006-coop-unresolved-accounts.sql:1](../migrations/006-coop-unresolved-accounts.sql#L1) |
| 2 | `genstat_db` | `coop_audit` | A | Account action audit trail, shared with the historical workflow | COOP Account Management | INSERT, CREATE | Yes | 49 | Schema and audit history | [lib/coop-registered-accounts.php:144](../lib/coop-registered-accounts.php#L144), [migrations/005-coop-account-management.sql:17](../migrations/005-coop-account-management.sql#L17) |
| 3 | `genstat_db` | `genstat_query_definitions` | A | Saved SQL and result-column metadata | SQL Configuration | SELECT, INSERT, UPDATE, CREATE | Yes | 2 | Schema and saved definitions | [lib/query-settings.php:111](../lib/query-settings.php#L111), [lib/query-settings.php:252](../lib/query-settings.php#L252), [migrations/001-query-settings.sql:2](../migrations/001-query-settings.sql#L2) |
| 4 | `genstat_db` | `genstat_row_query_mappings` | A | Row bindings, enabled state, calculation settings and formula-replacement permissions | SQL Configuration and Summary | SELECT, INSERT, UPDATE, CREATE, ALTER | Yes | 2 | Schema and current mappings | [lib/query-settings.php:259](../lib/query-settings.php#L259), [lib/query-apply-save.php:68](../lib/query-apply-save.php#L68), [migrations/001-query-settings.sql:12](../migrations/001-query-settings.sql#L12), [migrations/002-row-formula-replacement.sql:1](../migrations/002-row-formula-replacement.sql#L1), [migrations/003-query-calculation-settings.sql:1](../migrations/003-query-calculation-settings.sql#L1) |
| 5 | `genstat_db` | `genstat_query_history` | A | Versioned snapshots for history and restore | SQL Configuration | SELECT, INSERT, CREATE | Yes | 7 | Schema and existing versions | [query-settings-api.php:53](../query-settings-api.php#L53), [lib/query-apply-save.php:63](../lib/query-apply-save.php#L63), [migrations/001-query-settings.sql:27](../migrations/001-query-settings.sql#L27) |
| 6 | `genstat_db` | `genstat_query_apply_operations` | A | Idempotent apply/save receipts and recovery metadata | SQL Configuration | SELECT, INSERT, CREATE, compatibility ALTER | Yes | 10 | Schema and existing receipts | [query-settings-api.php:18](../query-settings-api.php#L18), [query-settings-api.php:108](../query-settings-api.php#L108), [migrations/004-query-apply-operations.sql:1](../migrations/004-query-apply-operations.sql#L1), [tools/migrate-query-apply-operations.php:10](../tools/migrate-query-apply-operations.php#L10) |

## 2. Local reporting source tables

Both enabled saved definitions reference all eight tables. The application reporting reader is configured for this exact allowlist, and its startup checks require direct SELECT access on all eight. Area metadata is also read separately for result matching. Saved SQL text is stored in the database, so those references have definition IDs and mapping keys in place of file line numbers. [lib/query-settings.php:8](../lib/query-settings.php#L8), [lib/query-settings.php:19](../lib/query-settings.php#L19), [lib/query-settings.php:75](../lib/query-settings.php#L75), [lib/query-settings.php:111](../lib/query-settings.php#L111), [lib/query-settings.php:162](../lib/query-settings.php#L162)

| No. | Database | Table | Class | Purpose | Module | SQL operations | In local schema | Records | Required for live | Source |
| ---: | --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| 7 | `genstat_db` | `area_master` | B | Stable area IDs and labels, parent of mappings, allocations and registry | Reporting and COOP | SELECT, FK parent | Yes | 20 | Schema and reference rows | Saved definitions 17/18, [lib/coop-registered-accounts.php:8](../lib/coop-registered-accounts.php#L8), [migrations/005-coop-account-management.sql:14](../migrations/005-coop-account-management.sql#L14) |
| 8 | `genstat_db` | `area_mapping` | B | Maps source `(SACode, Area)` pairs to GENSTAT IDs | Sales, adjustment, late and cancelled billing | SELECT | Yes | 39 | Schema and mapping rows | Saved definitions 17/18, [lib/query-settings.php:9](../lib/query-settings.php#L9) |
| 9 | `genstat_db` | `overallsalessummary` | B | Sales KWH and available GENSTAT reporting months | Sales, Total KWH Sold, worksheet source review | SELECT | Yes | 3,257 | Schema and reporting history | Saved definitions 17/18, [lib/query-settings.php:40](../lib/query-settings.php#L40), [config/monthly-source.php:13](../config/monthly-source.php#L13) |
| 10 | `genstat_db` | `billadjustment` | B | Adjustment KWH and original referenced billing month | Bill Adjustment and Total KWH Sold | SELECT | Yes | 4,461 | Schema and reporting history | Saved definitions 17/18, [config/monthly-source.php:14](../config/monthly-source.php#L14), [lib/monthly-worksheets.php:34](../lib/monthly-worksheets.php#L34) |
| 11 | `genstat_db` | `billhistorycancelled` | B | First cancelled-billing history, including `Cancelled_KWH` | Bill Adjustment and Total KWH Sold | SELECT, database BEFORE INSERT trigger for imports | Yes | 534 | Schema, history and trigger review | Saved definitions 17/18, [lib/query-settings.php:10](../lib/query-settings.php#L10), local catalog trigger `genstat_uuid_billhistorycancelled` |
| 12 | `genstat_db` | `billhistorycancelled_2` | B | Second cancelled-billing history, included in the combined cancelled result column | Saved reporting SQL | SELECT | Yes | 506 | Schema and reporting history | Saved definitions 17/18, [lib/query-settings.php:10](../lib/query-settings.php#L10) |
| 13 | `genstat_db` | `latebills` | B | Late bill KWH by `LateBillMonth`, excluding cancelled rows | Bill Adjustment and Total KWH Sold | SELECT | Yes | 3,575 | Schema and reporting history | Saved definitions 17/18, [lib/query-settings.php:10](../lib/query-settings.php#L10) |
| 14 | `genstat_db` | `kwh_diff_allocation` | B | Effective-month redistribution of source-area KWH differences | Bill Adjustment and Total KWH Sold | SELECT | Yes | 3 | Schema and effective-dated rules | Saved definitions 17/18, [lib/query-settings.php:10](../lib/query-settings.php#L10) |

### Saved SQL and workbook row behavior

| Report item | Inspected behavior |
| --- | --- |
| Total KWH Sold, row 18 | Enabled mapping `genstat-base/Jul26/A18` uses definition 17, `sales_kwh`, revision 2. Fallback column `TotalKWhSold`. Enabled calculation is `KWH_Diff + Sales_KWH + Late_Bills_KWH - Cancelled_KWH`. Formula replacement permission is enabled for the mapping. |
| Bill Adjustment (Previous months), row 25 | Enabled mapping `genstat-base/Jul26/A25` uses definition 18, `adj_kwh`, revision 2. Fallback column `KWH_Diff`. Enabled calculation is `KWH_Diff + Late_Bills_KWH - Cancelled_KWH`. Formula replacement permission is disabled. |
| Sales KWH, adjustments, late and cancelled KWH, allocation | Both definitions contain the same eight-table dependency set. Sales uses `BillMonth`. Adjustments and cancellations use `BillMonthRef`. Late bills use `LateBillMonth`. Allocation selects the latest rule at or before the report month. Both cancellation sources contribute to the SQL alias `Total_Cancelled_KWH`. The two enabled formulas use the first source's alias `Cancelled_KWH`. |
| Historical SQL | Seven history rows contained two distinct SQL texts. The older variant omits `latebills`. The other uses the current eight-table set. No additional physical tables were found in those snapshots. SQL CTE names are query-local expressions. |
| Sales in KWH (Inclusive of bill adj), row 21 | The template uses formulas for all 15 area cells. `Sales_KWH` is an input returned by both enabled definitions. There is no separate enabled mapping for row 21. |
| Disconnected Consumers, row 24 | All 15 area cells are designated input cells, with no saved mapping. No consumer-count table dependency was identified. `overallsalessummary.TotCon` exists and is listed as a source descriptor, but neither saved definition uses it. |
| COOP Consumption, row 26 | The shared registry and monthly provider populate the existing COOP summary overlay. There is no local SQL row mapping for row 26. The encrypted direct COOP SQL configuration is separate from the reporting database. [lib/coop-summary.php:61](../lib/coop-summary.php#L61), [lib/coop-registered-accounts.php:193](../lib/coop-registered-accounts.php#L193) |
| Recovered Sales, row 22 | File-backed Recovered Sales records and template formulas. No database dependency. [lib/recovered-sales.php:139](../lib/recovered-sales.php#L139), [base-report.php:259](../base-report.php#L259) |
| Purchased KWH, Total KWH Sold without adjustments, TX/SS losses and loss ratios | The inspected template mixes eligible inputs and Excel formulas. No additional saved mapping exists for these rows. An eligible input designation does not establish a SQL source. [lib/base-report.php:23](../lib/base-report.php#L23), [lib/base-report.php:35](../lib/base-report.php#L35) |

The observed six-digit source period spans were May–October 2026 for sales, adjustments and the second cancellation table, January–November 2026 for first cancellations, January–October 2026 for late bills, and July 2026 for allocation. These are observed data spans, not a retention recommendation.

## 3. External COOP database tables

The hosted database name supplied for the configured connection is `palecxzp_pal_db`. The deployed name was not independently verified. The local deployment artifact reads `db_name` from its private configuration, so the same source can run against a different schema. [deploy/coop-https-api/public/coop.php:33](../deploy/coop-https-api/public/coop.php#L33), [deploy/coop-https-api/public/coop.php:214](../deploy/coop-https-api/public/coop.php#L214)

| No. | Database | Table | Class | Purpose and columns read | Module or action | SQL operations | In local schema | Records | Required for live | Source |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 15 | Configured COOP source | `master` | C, live unverified | Exact `AcctCode`, canonical `AcctNo`, `Name`, and active `Status` | HTTPS `account_lookup`, `monthly_consumption`, and direct COOP lookup | SELECT | No, external | Unverified | Required at COOP source. No GENSTAT transfer | [deploy/coop-https-api/public/coop.php:106](../deploy/coop-https-api/public/coop.php#L106), [deploy/coop-https-api/public/coop.php:158](../deploy/coop-https-api/public/coop.php#L158), [lib/coop-registered-accounts.php:26](../lib/coop-registered-accounts.php#L26) |
| 16 | Configured COOP source | `billhistory` | C, live unverified | `AcctNo`, `BillMonth`, `KWH` for monthly consumption and billing validation | HTTPS `monthly_consumption` and direct COOP billing | SELECT | No, external | Unverified | Required at COOP source. No GENSTAT transfer | [deploy/coop-https-api/public/coop.php:154](../deploy/coop-https-api/public/coop.php#L154), [lib/coop-registered-accounts.php:151](../lib/coop-registered-accounts.php#L151) |

The HTTPS health check runs table-free `SELECT 1`. The API does not query the local registry or persist changes at the source. Direct-connection SQL preview discovers tables and views from `information_schema.TABLES` in the configured source, so saved direct COOP SQL could add source-specific dependencies. [deploy/coop-https-api/public/coop.php:227](../deploy/coop-https-api/public/coop.php#L227), [lib/coop-phase1.php:354](../lib/coop-phase1.php#L354)

## 4. Legacy and potentially unused local tables

Historical references below are file-and-line addresses within local Git commit `26c6a01`, not current runtime files. The current registry uses `coop_registered_accounts` and shares `coop_audit` with the historical implementation.

| No. | Database | Table | Class | Purpose | Module | SQL operations | In local schema | Records | Required for live | Source |
| ---: | --- | --- | --- | --- | --- | --- | --- | ---: | --- | --- |
| 17 | `genstat_db` | `coop_account_lock` | D | Per-account serialization of effective-date changes | Retired COOP enrollment | Historical SELECT, INSERT/upsert, CREATE | Yes | 1 | Review and preserve pending approval | `26c6a01:lib/coop-repository.php:23`, `:42`, `26c6a01:migrations/005-coop-consumption.sql:5` |
| 18 | `genstat_db` | `coop_applications` | D | Receipt for applying a historical COOP run | Retired COOP reporting | Historical SELECT, INSERT, CREATE | Yes | 0 | Review and preserve pending approval | `26c6a01:coop-consumption-api.php:234`, `:256`, `26c6a01:migrations/005-coop-consumption.sql:37` |
| 19 | `genstat_db` | `coop_consumption_accounts` | D | Historical effective-dated enrollments | Retired COOP enrollment | Historical SELECT, INSERT, UPDATE, CREATE | Yes | 1 | Review and preserve pending approval | `26c6a01:lib/coop-repository.php:13`, `:47`, `:53`, `26c6a01:migrations/005-coop-consumption.sql:8` |
| 20 | `genstat_db` | `coop_enrollment_state` | D | Singleton enrollment coordination row | Retired COOP enrollment | Historical SELECT/lock, seed INSERT, CREATE | Yes | 1 | Review and preserve pending approval | `26c6a01:lib/coop-repository.php:21`, `:40`, `26c6a01:migrations/005-coop-consumption.sql:1` |
| 21 | `genstat_db` | `coop_runs` | D | Historical COOP retrieval runs and results | Retired COOP reporting | Historical SELECT, INSERT, CREATE | Yes | 4 | Review and preserve pending approval | `26c6a01:coop-consumption-api.php:62`, `:206`, `26c6a01:migrations/005-coop-consumption.sql:28` |
| 22 | `genstat_db` | `sales_sync_status` | E | Source, table and month verification ledger by schema | Upstream ownership unverified | No current executable or saved-query reference identified | Yes | 96 | Review with import owner before deciding | Local schema appendix, [prior inspection](batch-c-inspection.md#sales_sync_status-structure-and-purpose) |

## 5. Missing tables

**None** among the current application and saved local reporting references. The source and fixture objects below have distinct schemas and are intentionally outside `genstat_db`.

## 6. Test-only tables

| No. | Database | Table | Class | Purpose | Module | SQL operations | In local schema | Records | Required for live | Source |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 23 | Disposable `coop_fixture_*` | `monthly` | G | Mock monthly KWH source | COOP browser fixture | CREATE, INSERT, SELECT | No | Run-specific | No | [tests/coop-phase1-fixture.php:34](../tests/coop-phase1-fixture.php#L34), [tests/coop-phase1-browser.cjs:201](../tests/coop-phase1-browser.cjs#L201) |
| 24 | Disposable `coop_fixture_*` | `accounts` | G | Mock account-grain preview source | COOP browser fixture | CREATE, INSERT, SELECT | No | Run-specific | No | [tests/coop-phase1-fixture.php:36](../tests/coop-phase1-fixture.php#L36), [tests/coop-phase1-fixture.php:45](../tests/coop-phase1-fixture.php#L45) |
| 25 | Disposable `coop_fixture_*` | `billing_off` | G | Temporary alias of fixture `billhistory` used to simulate failure | COOP browser fixture | RENAME | No | Same rows as fixture `billhistory` while renamed | No | [tests/coop-phase1-fixture.php:66](../tests/coop-phase1-fixture.php#L66) |
| 26 | Disposable `coop_tls_fixture` | `fixture` | G | TLS smoke-test row | Hosted MySQL TLS tests | CREATE, INSERT, SELECT | No | One when seeded | No | [tests/coop-hosted-tls.cjs:75](../tests/coop-hosted-tls.cjs#L75), [tests/coop-hosted-tls.cjs:98](../tests/coop-hosted-tls.cjs#L98) |

The COOP fixture also makes test copies of the local six application tables, `area_master`, and the external names `master` and `billhistory`. The query-apply fixture creates disposable copies of the four settings tables. These copies reuse inventoried names and are excluded from the unique-name counts. [tests/coop-phase1-fixture.php:17](../tests/coop-phase1-fixture.php#L17), [tests/coop-phase1-fixture.php:20](../tests/coop-phase1-fixture.php#L20), [tests/query-apply-fixture.php:11](../tests/query-apply-fixture.php#L11)

## 7. Database views and system objects

| No. | Database | View or catalog | Class | Purpose | Module | SQL operations | In local schema | Records | Required for live | Source |
| ---: | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 27 | Disposable `coop_fixture_*` | `slow_accounts` | G | View over `accounts` for timeout testing | COOP browser fixture | CREATE VIEW, SELECT | No | Run-specific | No | [tests/coop-phase1-fixture.php:46](../tests/coop-phase1-fixture.php#L46) |
| — | `information_schema` | `TABLES`, `COLUMNS` | Server catalogs | Source table and column discovery | Direct COOP preview, worksheet source review | SELECT | Outside `genstat_db` | Server-managed | Provided by MariaDB | [lib/coop-phase1.php:357](../lib/coop-phase1.php#L357), [lib/monthly-worksheets.php:34](../lib/monthly-worksheets.php#L34) |
| — | `mysql` | `user` | Server privilege catalog | Check whether setup accounts already exist | Installation utility | SELECT | Outside `genstat_db` | Server-managed | Provision users and grants on target | [tools/setup-query-settings.php:16](../tools/setup-query-settings.php#L16) |

The local schema has **zero views**, **zero routines**, **zero events**, and **one trigger**. `genstat_uuid_billhistorycancelled` runs before insertion into `billhistorycancelled` and assigns `UUID()` when the incoming `TransID` is empty. This is database-side import behavior, independent of GENSTAT's reporting SELECTs. The current migration files do not create the reporting source tables or this trigger.

## Dependencies and deployment order

### Enforced relationships

All eight inspected foreign keys use `RESTRICT` for delete and update.

| Parent | Dependent columns and constraint names |
| --- | --- |
| `area_master.AreaID` | `area_mapping.AreaID` via `fk_mapping_area`, `area_mapping.GenstatAreaID` via `fk_mapping_genstat`, and `area_mapping.kwhdiffAreaID` via `fk_mapping_kwhdiff_area` |
| `area_master.AreaID` | `kwh_diff_allocation.SourceAreaID` via `fk_allocation_source`, and `DestinationAreaID` via `fk_allocation_destination` |
| `area_master.AreaID` | `coop_registered_accounts.GenstatAreaID` via `coop_registered_area_fk` |
| `genstat_query_definitions.id` | `genstat_row_query_mappings.query_definition_id` via `genstat_row_query_mappings_ibfk_1` |
| `coop_runs.id` | `coop_applications.run_id` via `coop_applications_ibfk_1`, historical |

### Logical relationships

- Saved reporting SQL joins source rows to `area_mapping` on `(SACode, Area)`. The mapped `GenstatAreaID` joins `area_master.AreaID`. The billing sources have no foreign key to the mapping table. The allocation lookup joins the mapped source area to the latest applicable `SourceAreaID` and groups by destination.
- The COOP source matches an exact active master code to `AcctNo`, then matches the canonical number and month in `billhistory`. The local registry's unique keys on `AcctCode` and nullable `AcctNo` prevent local duplicates. There is no local foreign key to the source database. [lib/coop-registered-accounts.php:26](../lib/coop-registered-accounts.php#L26), [lib/coop-registered-accounts.php:169](../lib/coop-registered-accounts.php#L169), [migrations/005-coop-account-management.sql:10](../migrations/005-coop-account-management.sql#L10)
- Query history and apply receipts relate logically through `template_row_key`, snapshot version and report context. COOP audit entries link logically through their operation ID and JSON details. These links have no foreign key. [lib/query-apply-save.php:63](../lib/query-apply-save.php#L63), [query-settings-api.php:108](../query-settings-api.php#L108), [lib/coop-registered-accounts.php:144](../lib/coop-registered-accounts.php#L144)

### Transfer requirements

1. **Current schema and existing data.** Transfer all six active application tables, then preserve their associated draft and provenance files. Restore definitions before row mappings. Restore `area_master` before registry records. Carry over audit, saved versions, and apply receipts for continuity.
2. **Reporting history and reference data.** Restore `area_master` first, then `area_mapping` and `kwh_diff_allocation`. Restore the five fact tables with their history, indexes and the cancellation trigger as appropriate for the target import process. The configured queries explicitly name `genstat_db`. Keep that schema name or plan a reviewed rebind of the saved, schema-qualified SQL and local configurations. [tools/validate-report-sql.cjs](../tools/validate-report-sql.cjs), [lib/query-settings.php:75](../lib/query-settings.php#L75)
3. **Schema setup.** On a new target, restore the source schema and parent keys, then provision application migrations 001 through 006 in order where the relevant objects are absent. A complete schema restore already containing those effects should be reconciled against the migration contents instead of blindly replayed. Provision the server-side users and grants separately. No table is designated schema-only for a parity migration, though audit/history/receipt tables can start empty for a separately approved clean start. [migrations/001-query-settings.sql](../migrations/001-query-settings.sql), [migrations/006-coop-unresolved-accounts.sql](../migrations/006-coop-unresolved-accounts.sql), [tools/setup-query-settings.php:20](../tools/setup-query-settings.php#L20)
4. **External dependencies.** Keep `master` and `billhistory` at the approved COOP source. HTTPS mode needs the existing hosted API and valid protected local credentials. Direct modes require an independently configured source connection. No GENSTAT source-table copy is required.
5. **Review before making exclusions.** Preserve the five legacy tables and `sales_sync_status` in backups. If carrying legacy tables forward, restore `coop_runs` before `coop_applications`. Obtain owner approval before omitting legacy or potentially unused data. Exclude disposable fixture schemas and their view from the production import.

## Non-database storage

Paths are relative to the project root unless shown as absolute. Only locations and data types were inspected. No secret values or private records are included.

| Data | Location and migration significance | Source |
| --- | --- | --- |
| Annual workbook and backups | `excel-files/active/genstat_2026.xlsx`, with `genstat.xlsx` fallback, plus `excel-files/incoming/`, `archive/`, and `backups/`. Keep active workbook and related drafts together. | [lib/workbook-storage.php:5](../lib/workbook-storage.php#L5), [lib/workbook-storage.php:30](../lib/workbook-storage.php#L30) |
| Summary source and configuration | `excel-files/reference/GENSTAT_BASE.xlsx` is the independent Summary source. `config/layout.json` and `config/inputs.php` support workbook layout and inputs. | [lib/base-report.php:23](../lib/base-report.php#L23), [lib/app.php:30](../lib/app.php#L30), [tools/build-layout.php:4](../tools/build-layout.php#L4) |
| Annual values and formula overrides | `storage/draft-YYYY-MM.json` and `storage/draft-YYYY-MM--<sheet-hash>.json`. June and September draft files were observed. | [lib/app.php:48](../lib/app.php#L48) |
| Summary values, formula overrides and SQL provenance | `storage/base-report-<template-sha256>-YYYYMM.json` with legacy July `storage/base-report-<template-sha256>.json` fallback. July and August scoped drafts and formula-audit sidecars were observed. | [lib/base-report.php:9](../lib/base-report.php#L9), [lib/base-report-view.php:12](../lib/base-report-view.php#L12), [base-report.php:169](../base-report.php#L169) |
| Recovered Sales and its audit trail | `storage/genstat_2026_recovered_sales.xlsx` with `.bak` and `.lock`. Records and audit are stored in workbook sheets, including `RecovBill_Audit`. | [lib/recovered-sales.php:139](../lib/recovered-sales.php#L139), [lib/recovered-sales.php:209](../lib/recovered-sales.php#L209), [lib/recovered-sales.php:316](../lib/recovered-sales.php#L316) |
| Settings and connector configuration | `config/monthly-source.php`, local `config/query-settings.local.php`, and `config/coop-hosted-endpoints.php`. Runtime Summary SQL definitions are in the database. Direct COOP SQL is part of the encrypted COOP settings, separate from the reporting tables. | [config/monthly-source.php:1](../config/monthly-source.php#L1), [lib/query-settings.php:12](../lib/query-settings.php#L12), [coop-phase1-api.php:66](../coop-phase1-api.php#L66) |
| COOP connection profiles, API credentials and encryption key | Windows automatic directory observed at `C:\ProgramData\PALECO-GENSTAT-COOP-745878e1d57004be59665aa3`. Code uses `settings.json` and separate `key` there, or explicit private-file and key environment settings. Profiles, direct SQL, and API key are inside the AES-256-GCM payload. Move only through an authorized secure procedure or reconfigure on the target. | [lib/coop-phase1.php:64](../lib/coop-phase1.php#L64), [lib/coop-phase1.php:94](../lib/coop-phase1.php#L94), [lib/coop-phase1.php:123](../lib/coop-phase1.php#L123) |
| Provenance and formula authorization | `storage/sql-replacement.key` signs SQL replacement approvals. The optional annual formula authorization file is `storage/formula-auth.json`. Its path is defined in code and it was not seen in the storage listing. | [lib/sql-replacements.php:7](../lib/sql-replacements.php#L7), [lib/formula-auth.php:4](../lib/formula-auth.php#L4) |
| Formula audit and operational logs | Summary formula audit uses `storage/base-report-*.json.formula-audit.json`. Runtime PHP errors use Apache logging, with `C:\xampp\apache\logs\error.log` present. COOP registration audits and query history/receipts are database records, listed above. | [lib/base-report.php:114](../lib/base-report.php#L114), [lib/coop-registered-accounts.php:272](../lib/coop-registered-accounts.php#L272) |
| Refresh state, snapshots and backups | Observed `storage/monthly-review.json`, `refresh-review.json`, `template-auto.json`, `workbook-sync.json`, `workbook-comparison.json`, `workbook-migration-report.json`, and `storage/backups/`. Refresh recovery markers and lock files are operational state. | [lib/template-refresh.php:138](../lib/template-refresh.php#L138), [lib/template-sync.php:26](../lib/template-sync.php#L26), [lib/workbook-resync.php:7](../lib/workbook-resync.php#L7) |
| Browser preferences and sessions | Browser `localStorage` key `genstat:appearance:v1` stores display preferences. PHP sessions hold reporting context, CSRF state and transient COOP summary data. These are not business data to import. | [assets/report-appearance.js:3](../assets/report-appearance.js#L3), [base-report.php:7](../base-report.php#L7), [lib/coop-summary.php:64](../lib/coop-summary.php#L64) |

## Unverified findings

- Hosted schema metadata, record counts, indexes and foreign keys were outside this inspection. Only the configured database name supplied for the connection and table/column references in the local code were available.
- The Windows protected COOP settings and key were unreadable to the inspection process. The active connection type, saved direct COOP SQL and any extra source tables or views it names could not be verified without an authorized metadata export. The encrypted settings were not modified and the web report was not opened to infer these values.
- External import jobs and ownership of `sales_sync_status` were not verified. Its non-use by current project code is not evidence that upstream jobs can dispense with it.

## Final counts

| Category | Count | Scope |
| --- | ---: | --- |
| Existing local base tables | **20** | 6 active application + 8 active reporting + 5 legacy + 1 potentially unused |
| Active application tables | **6** | Includes the shared COOP audit once |
| Active reporting source tables | **8** | Includes shared area reference tables once |
| External database tables | **2** | Both identified in source and unverified live |
| Legacy tables | **5** | Local historical COOP tables |
| Potentially unused tables | **1** | Local `sales_sync_status`, upstream ownership unresolved |
| Missing local tables | **0** | Current local app and configured reporting scope |
| Test-only base-table names | **4** | Three independently created names plus one temporary alias. Same-name fixture copies excluded |
| Views | **0 local**, **1 test-only** | Server catalogs excluded |
| Tables required for live deployment | **14 local** | Two more are required at the external COOP source and are outside the local transfer |

## Appendix — inspected local schema

Column types, nullability, and ordered index columns below were read from the actual local database. Sizes are allocated data and index bytes. Foreign keys are listed in the dependency section.

### `area_mapping`

InnoDB | 39 rows | 16,384 data bytes | 65,536 index bytes | 6 columns.

Indexes

- `PRIMARY` (primary) on `id`.
- `uq_sacode_area` (unique) on `SACode, Area`.
- `fk_mapping_area` (nonunique) on `AreaID`.
- `fk_mapping_genstat` (nonunique) on `GenstatAreaID`.
- `fk_mapping_kwhdiff_area` (nonunique) on `kwhdiffAreaID`.

All columns in ordinal order

```text
id int(11) NOT NULL
SACode char(2) NOT NULL
Area char(2) NOT NULL
AreaID int(11) NOT NULL
GenstatAreaID int(11) NOT NULL
kwhdiffAreaID int(11) NOT NULL
```

### `area_master`

InnoDB | 20 rows | 16,384 data bytes | 16,384 index bytes | 2 columns.

Indexes

- `PRIMARY` (primary) on `AreaID`.
- `uq_area_name` (unique) on `AreaName`.

All columns in ordinal order

```text
AreaID int(11) NOT NULL
AreaName varchar(100) NOT NULL
```

### `billadjustment`

InnoDB | 4,461 rows | 8,962,048 data bytes | 589,824 index bytes | 162 columns.

Indexes

- `PRIMARY` (primary) on `SACode, EntryID`.
- `ux_sales_area_rowguid` (unique) on `SACode, rowguid`.
- `ix_sales_area_month` (nonunique) on `SACode, BillMonth`.

All columns in ordinal order

```text
EntryID char(36) NOT NULL
AdjNo varchar(13) NOT NULL
AdjustmentType char(10) NOT NULL
BillMonthRef char(6) NOT NULL
BillMonth char(6) NOT NULL
AcctNo varchar(13) NOT NULL
AcctCode varchar(12) NOT NULL
Area char(2) NOT NULL
Book char(4) NOT NULL
Sequence double NOT NULL
Name varchar(75) NOT NULL
Address varchar(50) NOT NULL
ASBRateCode char(1) NOT NULL
ASBLocationPoint char(1) NOT NULL
ASBBillNo decimal(18,0) NOT NULL
ASBReadingDate datetime(3) NULL
ASBBillDate datetime(3) NULL
ASBDueDate datetime(3) NULL
ASBFlatRate bit(1) NOT NULL
ASBMeterSerial varchar(20) NULL
ASBFeedBackCode char(2) NULL
ASBMultiplierKWH double NOT NULL
ASBMultiplierDemand double NOT NULL
ASBPrsRdngKWH double NOT NULL
ASBPrvRdngKWH double NOT NULL
ASBPrsRdngDemand double NOT NULL
ASBPrvRdngDemand double NOT NULL
ASBKWH double NOT NULL
ASBDemand double NOT NULL
ASBCM char(1) NOT NULL
ASBCMMultiplierKWH double NOT NULL
ASBCMLastRdngKWH double NOT NULL
ASBCMOldRdngKWH double NOT NULL
ASBCMInitialRdngKWH double NOT NULL
ASBCMLastRdngDemand double NOT NULL
ASBCMInitialRdngDemand double NOT NULL
ASBCMKWH double NOT NULL
ASBCMDemand double NOT NULL
ASBGenSysCharge decimal(19,4) NOT NULL
ASBHostCommCharge decimal(19,4) NOT NULL
ASBForexCharge decimal(19,4) NOT NULL
ASBTCDemandCharge decimal(19,4) NOT NULL
ASBTCSystemCharge decimal(19,4) NOT NULL
ASBSystemLossCharge decimal(19,4) NOT NULL
ASBDCDemandCharge decimal(19,4) NOT NULL
ASBDCSystemCharge decimal(19,4) NOT NULL
ASBSCRetCustCharge decimal(19,4) NOT NULL
ASBSCSupplySysCharge decimal(19,4) NOT NULL
ASBMCRetailCustCharge decimal(19,4) NOT NULL
ASBMCSystemCharge decimal(19,4) NOT NULL
ASBICCrossSubsidyCharge decimal(19,4) NOT NULL
ASBUCNPCStrandedDebts decimal(19,4) NOT NULL
ASBUCNPCStrandedContCost decimal(19,4) NOT NULL
ASBUCDUStrandedContCost decimal(19,4) NOT NULL
ASBUCME decimal(19,4) NOT NULL
ASBUCEqTaxesAndRoyalties decimal(19,4) NOT NULL
ASBUCEC decimal(19,4) NOT NULL
ASBUCCrossSubRemoval decimal(19,4) NOT NULL
ASBLifelineDiscSubs decimal(19,4) NOT NULL
ASBPowerActRateRed decimal(19,4) NOT NULL
ASBLoanCondo decimal(19,4) NOT NULL
ASBLoanCondoFix decimal(19,4) NOT NULL
ASBPowerCostAdj decimal(19,4) NOT NULL
ASBGenSysEvat decimal(19,4) NOT NULL
ASBSystemLossEvat decimal(19,4) NOT NULL
ASBTCDemandEvat decimal(19,4) NOT NULL
ASBTCSystemEvat decimal(19,4) NOT NULL
ASBDistributionEvat decimal(19,4) NOT NULL
ASBOtherEvat decimal(19,4) NOT NULL
ASBEvat decimal(19,4) NOT NULL
ASBPowerBill decimal(19,4) NOT NULL
ASBTotalOC decimal(19,4) NOT NULL
ASBTotalOCEvat decimal(19,4) NOT NULL
ASBTotalBill decimal(19,4) NOT NULL
ASBSurcharge decimal(19,4) NOT NULL
ASBSurchargeEvat decimal(19,4) NOT NULL
ASBPPayDiscount decimal(19,4) NOT NULL
ASBvat_sales decimal(19,4) NOT NULL
ASBvat_zerorated decimal(19,4) NOT NULL
ASBvat_exemption decimal(19,4) NOT NULL
RateCode char(1) NOT NULL
LocationPoint char(1) NOT NULL
BillNo decimal(18,0) NOT NULL
ReadingDate datetime(3) NULL
BillDate datetime(3) NULL
DueDate datetime(3) NULL
FlatRate bit(1) NOT NULL
MeterSerial varchar(20) NULL
FeedBackCode char(2) NULL
MultiplierKWH double NOT NULL
MultiplierDemand double NOT NULL
PrsRdngKWH double NOT NULL
PrvRdngKWH double NOT NULL
PrsRdngDemand double NOT NULL
PrvRdngDemand double NOT NULL
KWH double NOT NULL
Demand double NOT NULL
CM char(1) NOT NULL
CMMultiplierKWH double NOT NULL
CMLastRdngKWH double NOT NULL
CMOldRdngKWH double NOT NULL
CMInitialRdngKWH double NOT NULL
CMLastRdngDemand double NOT NULL
CMInitialRdngDemand double NOT NULL
CMKWH double NOT NULL
CMDemand double NOT NULL
GenSysCharge decimal(19,4) NOT NULL
HostCommCharge decimal(19,4) NOT NULL
ForexCharge decimal(19,4) NOT NULL
TCDemandCharge decimal(19,4) NOT NULL
TCSystemCharge decimal(19,4) NOT NULL
SystemLossCharge decimal(19,4) NOT NULL
DCDemandCharge decimal(19,4) NOT NULL
DCSystemCharge decimal(19,4) NOT NULL
SCRetCustCharge decimal(19,4) NOT NULL
SCSupplySysCharge decimal(19,4) NOT NULL
MCRetailCustCharge decimal(19,4) NOT NULL
MCSystemCharge decimal(19,4) NOT NULL
ICCrossSubsidyCharge decimal(19,4) NOT NULL
UCNPCStrandedDebts decimal(19,4) NOT NULL
UCNPCStrandedContCost decimal(19,4) NOT NULL
UCDUStrandedContCost decimal(19,4) NOT NULL
UCME decimal(19,4) NOT NULL
UCEqTaxesAndRoyalties decimal(19,4) NOT NULL
UCEC decimal(19,4) NOT NULL
UCCrossSubRemoval decimal(19,4) NOT NULL
LifelineDiscSubs decimal(19,4) NOT NULL
PowerActRateRed decimal(19,4) NOT NULL
LoanCondo decimal(19,4) NOT NULL
LoanCondoFix decimal(19,4) NOT NULL
PowerCostAdj decimal(19,4) NOT NULL
GenSysEvat decimal(19,4) NOT NULL
SystemLossEvat decimal(19,4) NOT NULL
TCDemandEvat decimal(19,4) NOT NULL
TCSystemEvat decimal(19,4) NOT NULL
DistributionEvat decimal(19,4) NOT NULL
OtherEvat decimal(19,4) NOT NULL
Evat decimal(19,4) NOT NULL
PowerBill decimal(19,4) NOT NULL
TotalOC decimal(19,4) NOT NULL
TotalOCEvat decimal(19,4) NOT NULL
TotalBill decimal(19,4) NOT NULL
Surcharge decimal(19,4) NOT NULL
SurchargeEvat decimal(19,4) NOT NULL
PPayDiscount decimal(19,4) NOT NULL
vat_sales decimal(19,4) NOT NULL
vat_zerorated decimal(19,4) NOT NULL
vat_exemption decimal(19,4) NOT NULL
PreSales bit(1) NOT NULL
UserName varchar(20) NOT NULL
EntryDate datetime(3) NOT NULL
Remarks varchar(300) NULL
ASBSeniorCitizen decimal(19,4) NOT NULL
SeniorCitizen decimal(19,4) NULL
ASBUCMERed decimal(19,4) NOT NULL
UCMERed decimal(19,4) NOT NULL
ASBRPPDKWH decimal(19,4) NOT NULL
ASBRPPDConsumer decimal(19,4) NOT NULL
RPPDKWH decimal(19,4) NOT NULL
RPPDConsumer decimal(19,4) NOT NULL
SACode char(2) NOT NULL
rowguid char(36) NULL
```

### `billhistorycancelled`

InnoDB | 534 rows | 507,904 data bytes | 65,536 index bytes | 90 columns.

Indexes

- `ux_sales_area_rowguid` (unique) on `SACode, rowguid`.
- `ix_sales_area_month` (nonunique) on `SACode, BillMonth`.

All columns in ordinal order

```text
TransID char(36) NOT NULL
BillMonth char(6) NOT NULL
BillMonthRef char(6) NOT NULL
AcctNo varchar(13) NOT NULL
AcctCode varchar(12) NOT NULL
Area char(2) NOT NULL
Book char(4) NOT NULL
Sequence double NOT NULL
Name varchar(75) NOT NULL
Address varchar(50) NOT NULL
RateCode char(1) NOT NULL
LocationPoint char(1) NOT NULL
BillNo decimal(19,0) NOT NULL
ReadingDate datetime(3) NULL
BillDate datetime(3) NULL
DueDate datetime(3) NULL
FlatRate bit(1) NOT NULL
MeterSerial varchar(20) NULL
FeedBackCode char(2) NULL
MultiplierKWH double NOT NULL
MultiplierDemand double NOT NULL
PrsRdngKWH double NOT NULL
PrvRdngKWH double NOT NULL
PrsRdngDemand double NOT NULL
PrvRdngDemand double NOT NULL
KWH double NOT NULL
Demand double NOT NULL
CM char(1) NOT NULL
CMMultiplierKWH double NOT NULL
CMLastRdngKWH double NOT NULL
CMOldRdngKWH double NOT NULL
CMInitialRdngKWH double NOT NULL
CMLastRdngDemand double NOT NULL
CMInitialRdngDemand double NOT NULL
CMKWH double NOT NULL
CMDemand double NOT NULL
GenSysCharge decimal(19,4) NOT NULL
HostCommCharge decimal(19,4) NOT NULL
ForexCharge decimal(19,4) NOT NULL
TCDemandCharge decimal(19,4) NOT NULL
TCSystemCharge decimal(19,4) NOT NULL
SystemLossCharge decimal(19,4) NOT NULL
DCDemandCharge decimal(19,4) NOT NULL
DCSystemCharge decimal(19,4) NOT NULL
SCRetCustCharge decimal(19,4) NOT NULL
SCSupplySysCharge decimal(19,4) NOT NULL
MCRetailCustCharge decimal(19,4) NOT NULL
MCSystemCharge decimal(19,4) NOT NULL
ICCrossSubsidyCharge decimal(19,4) NOT NULL
UCNPCStrandedDebts decimal(19,4) NOT NULL
UCNPCStrandedContCost decimal(19,4) NOT NULL
UCDUStrandedContCost decimal(19,4) NOT NULL
UCME decimal(19,4) NOT NULL
UCEqTaxesAndRoyalties decimal(19,4) NOT NULL
UCEC decimal(19,4) NOT NULL
UCCrossSubRemoval decimal(19,4) NOT NULL
LifelineDiscSubs decimal(19,4) NOT NULL
PowerActRateRed decimal(19,4) NOT NULL
LoanCondo decimal(19,4) NOT NULL
LoanCondoFix decimal(19,4) NOT NULL
PowerCostAdj decimal(19,4) NOT NULL
GenSysEvat decimal(19,4) NOT NULL
SystemLossEvat decimal(19,4) NOT NULL
TCDemandEvat decimal(19,4) NOT NULL
TCSystemEvat decimal(19,4) NOT NULL
DistributionEvat decimal(19,4) NOT NULL
OtherEvat decimal(19,4) NOT NULL
Evat decimal(19,4) NOT NULL
PowerBill decimal(19,4) NOT NULL
TotalOC decimal(19,4) NOT NULL
TotalOCEvat decimal(19,4) NOT NULL
TotalBill decimal(19,4) NOT NULL
Surcharge decimal(19,4) NOT NULL
SurchargeEvat decimal(19,4) NOT NULL
PPayDiscount decimal(19,4) NOT NULL
vat_sales decimal(19,4) NOT NULL
vat_zerorated decimal(19,4) NOT NULL
vat_exemption decimal(19,4) NOT NULL
LateBillMonth char(6) NOT NULL
FB char(10) NOT NULL
Posted bit(1) NOT NULL
Cancelled bit(1) NOT NULL
UserName varchar(20) NOT NULL
CancelledBy varchar(20) NOT NULL
EntryDate datetime(3) NOT NULL
UCMERed decimal(19,4) NOT NULL
RPPDKWH decimal(19,4) NOT NULL
RPPDConsumer decimal(19,4) NOT NULL
SACode char(2) NOT NULL
rowguid char(36) NULL
```

### `billhistorycancelled_2`

InnoDB | 506 rows | 475,136 data bytes | 0 index bytes | 90 columns.

No indexes or declared primary key.

All columns in ordinal order

```text
TransID char(36) NOT NULL
BillMonth char(6) NOT NULL
BillMonthRef char(6) NOT NULL
AcctNo varchar(13) NOT NULL
AcctCode varchar(12) NOT NULL
Area char(2) NOT NULL
Book char(4) NOT NULL
Sequence double NOT NULL
Name varchar(75) NOT NULL
Address varchar(50) NOT NULL
RateCode char(1) NOT NULL
LocationPoint char(1) NOT NULL
BillNo decimal(19,0) NOT NULL
ReadingDate datetime(3) NULL
BillDate datetime(3) NULL
DueDate datetime(3) NULL
FlatRate bit(1) NOT NULL
MeterSerial varchar(20) NULL
FeedBackCode char(2) NULL
MultiplierKWH double NOT NULL
MultiplierDemand double NOT NULL
PrsRdngKWH double NOT NULL
PrvRdngKWH double NOT NULL
PrsRdngDemand double NOT NULL
PrvRdngDemand double NOT NULL
KWH double NOT NULL
Demand double NOT NULL
CM char(1) NOT NULL
CMMultiplierKWH double NOT NULL
CMLastRdngKWH double NOT NULL
CMOldRdngKWH double NOT NULL
CMInitialRdngKWH double NOT NULL
CMLastRdngDemand double NOT NULL
CMInitialRdngDemand double NOT NULL
CMKWH double NOT NULL
CMDemand double NOT NULL
GenSysCharge decimal(19,4) NOT NULL
HostCommCharge decimal(19,4) NOT NULL
ForexCharge decimal(19,4) NOT NULL
TCDemandCharge decimal(19,4) NOT NULL
TCSystemCharge decimal(19,4) NOT NULL
SystemLossCharge decimal(19,4) NOT NULL
DCDemandCharge decimal(19,4) NOT NULL
DCSystemCharge decimal(19,4) NOT NULL
SCRetCustCharge decimal(19,4) NOT NULL
SCSupplySysCharge decimal(19,4) NOT NULL
MCRetailCustCharge decimal(19,4) NOT NULL
MCSystemCharge decimal(19,4) NOT NULL
ICCrossSubsidyCharge decimal(19,4) NOT NULL
UCNPCStrandedDebts decimal(19,4) NOT NULL
UCNPCStrandedContCost decimal(19,4) NOT NULL
UCDUStrandedContCost decimal(19,4) NOT NULL
UCME decimal(19,4) NOT NULL
UCEqTaxesAndRoyalties decimal(19,4) NOT NULL
UCEC decimal(19,4) NOT NULL
UCCrossSubRemoval decimal(19,4) NOT NULL
LifelineDiscSubs decimal(19,4) NOT NULL
PowerActRateRed decimal(19,4) NOT NULL
LoanCondo decimal(19,4) NOT NULL
LoanCondoFix decimal(19,4) NOT NULL
PowerCostAdj decimal(19,4) NOT NULL
GenSysEvat decimal(19,4) NOT NULL
SystemLossEvat decimal(19,4) NOT NULL
TCDemandEvat decimal(19,4) NOT NULL
TCSystemEvat decimal(19,4) NOT NULL
DistributionEvat decimal(19,4) NOT NULL
OtherEvat decimal(19,4) NOT NULL
Evat decimal(19,4) NOT NULL
PowerBill decimal(19,4) NOT NULL
TotalOC decimal(19,4) NOT NULL
TotalOCEvat decimal(19,4) NOT NULL
TotalBill decimal(19,4) NOT NULL
Surcharge decimal(19,4) NOT NULL
SurchargeEvat decimal(19,4) NOT NULL
PPayDiscount decimal(19,4) NOT NULL
vat_sales decimal(19,4) NOT NULL
vat_zerorated decimal(19,4) NOT NULL
vat_exemption decimal(19,4) NOT NULL
LateBillMonth char(6) NOT NULL
FB char(10) NOT NULL
Posted bit(1) NOT NULL
Cancelled bit(1) NOT NULL
UserName varchar(20) NOT NULL
CancelledBy varchar(20) NOT NULL
EntryDate datetime(3) NOT NULL
UCMERed decimal(19,4) NOT NULL
RPPDKWH decimal(19,4) NOT NULL
RPPDConsumer decimal(19,4) NOT NULL
SACode char(2) NOT NULL
rowguid char(36) NULL
```

### `coop_account_lock`

InnoDB | 1 rows | 16,384 data bytes | 0 index bytes | 1 columns.

Indexes

- `PRIMARY` (primary) on `account_no`.

All columns in ordinal order

```text
account_no varbinary(128) NOT NULL
```

### `coop_applications`

InnoDB | 0 rows | 16,384 data bytes | 0 index bytes | 5 columns.

Indexes

- `PRIMARY` (primary) on `run_id`.

All columns in ordinal order

```text
run_id char(32) NOT NULL
actor varchar(190) NOT NULL
draft_revision bigint(20) unsigned NOT NULL
result_hash char(64) NOT NULL
applied_at datetime NOT NULL
```

### `coop_audit`

InnoDB | 49 rows | 16,384 data bytes | 16,384 index bytes | 7 columns.

Indexes

- `PRIMARY` (primary) on `id`.
- `event_operation` (unique) on `operation_id, action`.

All columns in ordinal order

```text
id bigint(20) unsigned NOT NULL
operation_id varchar(80) NOT NULL
action varchar(40) NOT NULL
actor varchar(190) NOT NULL
period char(6) NOT NULL
details longtext NOT NULL
created_at datetime NOT NULL
```

### `coop_consumption_accounts`

InnoDB | 1 rows | 16,384 data bytes | 32,768 index bytes | 11 columns.

Indexes

- `PRIMARY` (primary) on `id`.
- `account_start` (unique) on `canonical_acct_no, effective_from`.
- `effective_period` (nonunique) on `effective_from, effective_to`.

All columns in ordinal order

```text
id bigint(20) unsigned NOT NULL
area_key varbinary(180) NOT NULL
acct_code varbinary(128) NOT NULL
canonical_acct_no varbinary(128) NOT NULL
effective_from char(6) NOT NULL
effective_to char(6) NULL
status varchar(16) NOT NULL
created_at datetime NOT NULL
updated_at datetime NOT NULL
created_by varchar(190) NOT NULL
updated_by varchar(190) NOT NULL
```

### `coop_enrollment_state`

InnoDB | 1 rows | 16,384 data bytes | 0 index bytes | 1 columns.

Indexes

- `PRIMARY` (primary) on `id`.

All columns in ordinal order

```text
id tinyint(3) unsigned NOT NULL
```

### `coop_registered_accounts`

InnoDB | 30 rows | 16,384 data bytes | 65,536 index bytes | 7 columns.

Indexes

- `PRIMARY` (primary) on `ID`.
- `unique_coop_code` (unique) on `AcctCode`.
- `unique_coop_number` (unique) on `AcctNo`.
- `coop_area_code` (nonunique) on `GenstatAreaID, AcctCode`.
- `coop_account_name` (nonunique) on `AccountName`.

All columns in ordinal order

```text
ID bigint(20) unsigned NOT NULL
AcctCode varbinary(128) NOT NULL
AcctNo varbinary(128) NULL
AccountName varchar(255) NULL
GenstatAreaID int(11) NOT NULL
CreatedAt datetime NOT NULL
UpdatedAt datetime NOT NULL
```

### `coop_runs`

InnoDB | 4 rows | 16,384 data bytes | 16,384 index bytes | 12 columns.

Indexes

- `PRIMARY` (primary) on `id`.
- `history` (nonunique) on `period, created_at`.

All columns in ordinal order

```text
id char(32) NOT NULL
actor varchar(190) NOT NULL
period char(6) NOT NULL
template_hash char(64) NOT NULL
enrollment_hash char(64) NOT NULL
draft_revision bigint(20) unsigned NOT NULL
working_hash char(64) NOT NULL
status varchar(20) NOT NULL
account_count int(10) unsigned NOT NULL
result_json longtext NOT NULL
expires_at bigint(20) unsigned NOT NULL
created_at datetime NOT NULL
```

### `genstat_query_apply_operations`

InnoDB | 10 rows | 540,672 data bytes | 32,768 index bytes | 14 columns.

Indexes

- `PRIMARY` (primary) on `operation_id`.
- `sequence_id` (unique) on `sequence_id`.
- `owner_source` (nonunique) on `owner_hash, source_hash, created_at`.

All columns in ordinal order

```text
operation_id char(48) NOT NULL
owner_hash char(64) NOT NULL
template_row_key varchar(180) NOT NULL
source_hash char(64) NOT NULL
draft_revision int(10) unsigned NOT NULL
input_hash char(64) NOT NULL
output_hash char(64) NOT NULL
baseline_hash char(64) NOT NULL
config_hash char(64) NOT NULL
record_hash char(64) NOT NULL
area_hash char(64) NOT NULL
response_json mediumtext NOT NULL
created_at datetime NOT NULL
sequence_id bigint(20) unsigned NOT NULL
```

### `genstat_query_definitions`

InnoDB | 2 rows | 16,384 data bytes | 16,384 index bytes | 8 columns.

Indexes

- `PRIMARY` (primary) on `id`.
- `query_key` (unique) on `query_key`.

All columns in ordinal order

```text
id bigint(20) unsigned NOT NULL
query_key varchar(80) NOT NULL
sql_text mediumtext NOT NULL
area_column varchar(64) NOT NULL
sacode_column varchar(64) NOT NULL
revision int(10) unsigned NOT NULL
created_at datetime NOT NULL
updated_at datetime NOT NULL
```

### `genstat_query_history`

InnoDB | 7 rows | 81,920 data bytes | 16,384 index bytes | 4 columns.

Indexes

- `PRIMARY` (primary) on `id`.
- `template_row_key` (nonunique) on `template_row_key`.

All columns in ordinal order

```text
id bigint(20) unsigned NOT NULL
template_row_key varchar(180) NOT NULL
snapshot_json mediumtext NOT NULL
created_at datetime NOT NULL
```

### `genstat_row_query_mappings`

InnoDB | 2 rows | 16,384 data bytes | 32,768 index bytes | 14 columns.

Indexes

- `PRIMARY` (primary) on `id`.
- `template_row_key` (unique) on `template_row_key`.
- `query_definition_id` (nonunique) on `query_definition_id`.

All columns in ordinal order

```text
id bigint(20) unsigned NOT NULL
template_row_key varchar(180) NOT NULL
template_hash char(64) NOT NULL
row_label varchar(255) NOT NULL
query_definition_id bigint(20) unsigned NOT NULL
value_column varchar(64) NOT NULL
bindings_json text NOT NULL
excluded_json text NOT NULL
enabled tinyint(1) NOT NULL
revision int(10) unsigned NOT NULL
created_at datetime NOT NULL
updated_at datetime NOT NULL
allow_formula_replacement tinyint(1) NOT NULL
calculation_settings_json text NULL
```

### `kwh_diff_allocation`

InnoDB | 3 rows | 16,384 data bytes | 49,152 index bytes | 6 columns.

Indexes

- `PRIMARY` (primary) on `AllocationID`.
- `uq_month_source` (unique) on `BillMonth, SourceAreaID`.
- `fk_allocation_source` (nonunique) on `SourceAreaID`.
- `fk_allocation_destination` (nonunique) on `DestinationAreaID`.

All columns in ordinal order

```text
AllocationID int(11) NOT NULL
BillMonth char(6) NOT NULL
SourceAreaID int(11) NOT NULL
DestinationAreaID int(11) NOT NULL
CreatedAt timestamp NOT NULL
UpdatedAt timestamp NOT NULL
```

### `latebills`

InnoDB | 3,575 rows | 3,686,400 data bytes | 98,304 index bytes | 91 columns.

Indexes

- `ix_latebills_area_period` (nonunique) on `SACode, LateBillMonth`.

All columns in ordinal order

```text
TransID char(36) NULL
BillMonth varchar(6) NULL
AcctNo varchar(13) NULL
AcctCode varchar(12) NULL
Area varchar(2) NULL
Book varchar(4) NULL
Sequence double NULL
Name varchar(75) NULL
Address varchar(50) NULL
RateCode varchar(1) NULL
LocationPoint varchar(1) NULL
BillNo decimal(19,0) NULL
ReadingDate datetime(3) NULL
BillDate datetime(3) NULL
DueDate datetime(3) NULL
FlatRate bit(1) NULL
MeterSerial varchar(20) NULL
FeedBackCode varchar(2) NULL
MultiplierKWH double NULL
MultiplierDemand double NULL
PrsRdngKWH double NULL
PrvRdngKWH double NULL
PrsRdngDemand double NULL
PrvRdngDemand double NULL
KWH double NULL
Demand double NULL
CM varchar(1) NULL
CMMultiplierKWH double NULL
CMLastRdngKWH double NULL
CMOldRdngKWH double NULL
CMInitialRdngKWH double NULL
CMLastRdngDemand double NULL
CMInitialRdngDemand double NULL
CMKWH double NULL
CMDemand double NULL
GenSysCharge decimal(19,4) NULL
HostCommCharge decimal(19,4) NULL
ForexCharge decimal(19,4) NULL
TCDemandCharge decimal(19,4) NULL
TCSystemCharge decimal(19,4) NULL
SystemLossCharge decimal(19,4) NULL
DCDemandCharge decimal(19,4) NULL
DCSystemCharge decimal(19,4) NULL
SCRetCustCharge decimal(19,4) NULL
SCSupplySysCharge decimal(19,4) NULL
MCRetailCustCharge decimal(19,4) NULL
MCSystemCharge decimal(19,4) NULL
ICCrossSubsidyCharge decimal(19,4) NULL
UCNPCStrandedDebts decimal(19,4) NULL
UCNPCStrandedContCost decimal(19,4) NULL
UCDUStrandedContCost decimal(19,4) NULL
UCME decimal(19,4) NULL
UCEqTaxesAndRoyalties decimal(19,4) NULL
UCEC decimal(19,4) NULL
UCCrossSubRemoval decimal(19,4) NULL
LifelineDiscSubs decimal(19,4) NULL
PowerActRateRed decimal(19,4) NULL
LoanCondo decimal(19,4) NULL
LoanCondoFix decimal(19,4) NULL
PowerCostAdj decimal(19,4) NULL
GenSysEvat decimal(19,4) NULL
SystemLossEvat decimal(19,4) NULL
TCDemandEvat decimal(19,4) NULL
TCSystemEvat decimal(19,4) NULL
DistributionEvat decimal(19,4) NULL
OtherEvat decimal(19,4) NULL
Evat decimal(19,4) NULL
PowerBill decimal(19,4) NULL
TotalOC decimal(19,4) NULL
TotalOCEvat decimal(19,4) NULL
TotalBill decimal(19,4) NULL
Surcharge decimal(19,4) NULL
SurchargeEvat decimal(19,4) NULL
PPayDiscount decimal(19,4) NULL
vat_sales decimal(19,4) NULL
vat_zerorated decimal(19,4) NULL
vat_exemption decimal(19,4) NULL
LateBillMonth varchar(6) NULL
FB varchar(10) NULL
Posted bit(1) NULL
Cancelled bit(1) NULL
UserName varchar(20) NULL
EntryDate datetime(3) NULL
UCMERed decimal(19,4) NULL
RPPDKWH decimal(19,4) NULL
RPPDConsumer decimal(19,4) NULL
AcctSeries varchar(4) NULL
LifelineSub decimal(19,4) NULL
LifelineDisc decimal(19,4) NULL
Reinvestment double NULL
SACode char(2) NOT NULL
```

### `overallsalessummary`

InnoDB | 3,257 rows | 2,637,824 data bytes | 229,376 index bytes | 63 columns.

Indexes

- `ux_sales_area_rowguid` (unique) on `SACode, rowguid`.
- `ix_sales_area_month` (nonunique) on `SACode, BillMonth`.

All columns in ordinal order

```text
TransID decimal(18,0) NOT NULL
BillMonth char(6) NOT NULL
SACode char(2) NOT NULL
AreaDefinition char(2) NOT NULL
AreaDefinitionName varchar(50) NOT NULL
Area char(2) NOT NULL
Book char(4) NOT NULL
BookDescription varchar(60) NULL
RateCode char(1) NOT NULL
TotCon double NOT NULL
LocationPoint char(1) NOT NULL
KWH double NOT NULL
Demand double NOT NULL
GenSysCharge decimal(19,4) NOT NULL
HostCommCharge decimal(19,4) NOT NULL
ForexCharge decimal(19,4) NOT NULL
TCDemandCharge decimal(19,4) NOT NULL
TCSystemCharge decimal(19,4) NOT NULL
SystemLossCharge decimal(19,4) NOT NULL
DCDemandCharge decimal(19,4) NOT NULL
DCSystemCharge decimal(19,4) NOT NULL
SCRetCustCharge decimal(19,4) NOT NULL
SCSupplySysCharge decimal(19,4) NOT NULL
MCRetailCustCharge decimal(19,4) NOT NULL
MCSystemCharge decimal(19,4) NOT NULL
ICCrossSubsidyCharge decimal(19,4) NOT NULL
UCNPCStrandedDebts decimal(19,4) NOT NULL
UCNPCStrandedContCost decimal(19,4) NOT NULL
UCDUStrandedContCost decimal(19,4) NOT NULL
UCME decimal(19,4) NOT NULL
UCEqTaxesAndRoyalties decimal(19,4) NOT NULL
UCEC decimal(19,4) NOT NULL
UCCrossSubRemoval decimal(19,4) NOT NULL
LifelineDiscSubs decimal(19,4) NOT NULL
PowerActRateRed decimal(19,4) NOT NULL
LoanCondo decimal(19,4) NOT NULL
LoanCondoFix decimal(19,4) NOT NULL
PowerCostAdj decimal(19,4) NOT NULL
GenSysEvat decimal(19,4) NOT NULL
SystemLossEvat decimal(19,4) NOT NULL
TCDemandEvat decimal(19,4) NOT NULL
TCSystemEvat decimal(19,4) NOT NULL
DistributionEvat decimal(19,4) NOT NULL
OtherEvat decimal(19,4) NOT NULL
Evat decimal(19,4) NOT NULL
PowerBill decimal(19,4) NOT NULL
TotalOC decimal(19,4) NOT NULL
TotalBill decimal(19,4) NOT NULL
Surcharge decimal(19,4) NOT NULL
SurchargeEvat decimal(19,4) NOT NULL
Reinvestment decimal(19,4) NOT NULL
LateBillMonth char(6) NOT NULL
PostedBy varchar(20) NOT NULL
PostedDate datetime(3) NOT NULL
UserName varchar(20) NOT NULL
EntryDate datetime(3) NOT NULL
LifeSubsidy decimal(19,4) NOT NULL
LifeDiscount decimal(19,4) NOT NULL
UploadedBy varchar(20) NOT NULL
UCMERed decimal(19,4) NOT NULL
RPPDKWH decimal(19,4) NOT NULL
RPPDConsumer decimal(19,4) NOT NULL
rowguid char(36) NULL
```

### `sales_sync_status`

InnoDB | 96 rows | 16,384 data bytes | 0 index bytes | 7 columns.

Indexes

- `PRIMARY` (primary) on `SourceCode, TableName, BillMonth`.

All columns in ordinal order

```text
SourceCode varchar(3) NOT NULL
TableName varchar(64) NOT NULL
BillMonth char(6) NOT NULL
SACode char(2) NOT NULL
RowCount bigint(20) NOT NULL
Fingerprint char(64) NOT NULL
VerifiedAt datetime(6) NOT NULL
```
