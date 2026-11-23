# GENSTAT COOP Consumption module inspection

## 1. Executive Summary

**Verified.** The compact GENSTAT Summary uses the sole reference worksheet, `Jul26`, in `excel-files/reference/GENSTAT_BASE.xlsx`. The label `f.) Coop Consumption` is at **A26**. Its 15 area inputs are **B26:P26**. They are editable, formula-free workbook cells. **Q26 and R26 are formulas**, and **S26 is the last-month comparison**. Local metadata showed no saved row configuration for `genstat-base/Jul26/A26` at inspection time.

**Proposal.** Build COOP as a separate module with effective-dated enrollment, a dedicated TLS-verified cloud reader, fixed prepared billing queries, and a reviewed apply operation into the existing working report. Reuse the report's coordinates, selected period, formula recalculation, Save Draft, and Excel export. Keep the cloud fetch out of routine report loading and restoration. **Risk: High** for billing identity, missing data, competing writers, credential exposure and decimal accuracy.

**Inspection boundary.** Findings came from the actual reference workbook, current source, checked-in migration definitions, and read-only metadata queries to the configured local database after confirming both configured reporting endpoints were loopback. The cloud server was not contacted. The supplied cloud schema and query are requirements pending verification.

## 2. Existing GENSTAT Architecture

| Evidence | Verified behavior | Proposed use | Risk |
|---|---|---|---|
| `index.php:4`, default report routing. `lib/base-report.php:22–50`, `base_report_layout()`. `tools/build-layout.php:13+`, `build_layout()`. | The default compact report uses `Jul26!A8:S47`. The source extends through `AG99`. The reporting period changes the data context while the reference worksheet and coordinates stay fixed. The full workbook route is a separate editor. | Integrate the module with the compact Summary, using the resolved worksheet rather than searching for a tab named Summary. | Medium |
| `base-report.php:168–211`, workbook merge map, headings and cell renderer. `lib/base-report.php:35–50`, worksheet metadata. | The renderer preserves source `data-ref`, `data-excel-row`, Excel letters, merge ownership and explicit hidden rows. The row-number gutter is presentation. Totals can be hidden while retaining their letters. | Resolve targets from workbook metadata on the server. Keep identities independent of visible row order, collapsed columns and the gutter. | High if positional mapping is introduced |
| `base-report.php:16–30, 162–168`, initial/open-period calculations. `assets/base-report.js:162–174, 426–465`, `request()`, `renderCalculation()`, `loadPeriod()`. | PHP combines draft values, formula overrides, SQL replacements and the current preview, then returns formatted calculation cells. The client updates the existing DOM by reference. Initial open requests live Total KWH Sold only. Period switching loads the broader enabled-row preview. | Use the same model-to-render path after reviewed application. Keep cloud reads explicitly triggered or scheduled separately. | Medium |

## 3. Summary Area and Column Mapping

The area names and coordinates below were extracted with `report_area_headers()` from the workbook during inspection. The ID column is the **observed saved A18 Total KWH Sold binding**, not a COOP assignment or a column ordinal. The IDs exist in local `area_master`, and the corresponding IDs occur as `GenstatAreaID` in local metadata. Source references are the header fragments used in the compact view.

| Area Name | Excel Column | GENSTAT Area ID (existing A18 binding) | Source header |
|---|---|---|---|
| Main | B | 1 (Puerto Princesa City) | B8 |
| Aborlan | C | 8 | C8 |
| Narra | D | 9 | D8 |
| Quezon | E | 10 | E8 |
| Brooke's Point | F | 12 (Brooke's Pt.) | F8, F9 |
| Roxas | G | 2 | G8 |
| Taytay | H | 5 | H8, owner of H8:H9 |
| Balabac | I | 15 | I8 |
| Araceli/Dumaran | J | 4 (reporting rollup binding) | J8, J9 |
| San Vicente | K | 3 | K8, K9 |
| El Nido | L | 6 | L8 |
| Cagayancillo | M | 19 | M8 |
| Cuyo/Magsaysay | N | 16 (reporting rollup binding) | N8, N9 |
| Agutaya | O | 18 | O8 |
| Rizal | P | 11 | P8 |

**Evidence and change.** `lib/report-areas.php:4–37`, `report_area_name()`, `report_area_headers()`, `report_area_alias()` combine same-column fragments, skip non-anchor cells and stop at the Total heading. The only special alias in code is Brooke's Point, which checks both master and reporting mapping. `lib/query-settings.php:111–157, 159–163`, `qs_normalize()`, `qs_match()`, `qs_area_metadata()`, `qs_record_config()` validate explicit bindings and distinguish `AreaID`, `GenstatAreaID` and `kwhdiffAreaID`. The observed A18 bindings came from local `genstat_row_query_mappings.bindings_json`. Local master IDs 20 (Dumaran) and 17 (Magsaysay) are not separate Summary headings. Use the existing resolver as the single area inventory, with a reviewed durable mapping identity whose current Excel targets are resolved per template. Do not infer a cloud account's Summary area from its name, an Excel ordinal or an A18 binding alone. **Risk: High** for rollups and future workbook moves.

## 4. COOP Consumption Row Analysis

| Evidence | Verified behavior | Proposed change | Risk |
|---|---|---|---|
| Reference workbook `Jul26!A26:S26`. `lib/base-report.php:32–56`, `base_report_layout()`, `base_report_validate()`. | A26 is the protected label. B26:P26 are `database_input`, already manually editable and formula-free. Q26 is `=SUM(B26:P26)`; R26 is `=+S26+Q26`. S26 is a manual, formula-free last-month comparator. | Apply approved cloud aggregates to B26:P26 only. Preserve the comparator and let existing formulas compute Q26 and R26. | High |
| Reference workbook formula expressions, inspected via `expanded_formulas()` in `lib/formula-engine.php:73–84` and `formula_dependencies()` in `lib/formula-edits.php:35–48`. | Each area formula in B21:P21 references its matching row-26 input. Example: B21 is `=B18-B26-B22`, while F21 is `=+F18-F26`. R18 also references R26. Changing row 26 affects sales subtotals and cumulative reporting through these dependencies. | Show affected totals in preview using the existing calculation engine. Use known input/expected total fixtures before release. | High |
| `lib/query-settings.php:62–90`, `qs_rows()` and `qs_targets()`. `lib/sql-replacements.php:19–42`, approved-row check and replacement signing. | Generic SQL settings can write B26:P26 if configured. Only the approved Total KWH Sold row A18 can replace original Excel formulas through SQL. No A26 mapping was present in the inspected local metadata. | Define a single writer for row 26. Reject competing enabled generic SQL configuration at both entry points once COOP ownership is activated. Keep editing policy for manual inputs explicit. | High |
| `base-report.php:45–143`, sheet unlock, audited manual formula edit and Restore & Recalculate. | Formula unlock affects eligible original formula cells and respects SQL-managed cells. Restore removes formula overlays and reapplies enabled generic SQL configurations. Eligible non-formula inputs use the ordinary value plane. | Use that value plane. No unlock, signed formula replacement or template edit is needed. Restore should retain reviewed COOP overlays and avoid cloud side effects. | High |

## 5. Current Settings Architecture

| Evidence | Verified behavior | Proposed change | Risk |
|---|---|---|---|
| `base-report.php:185–188, 221–235`, toolbar and dialogs. `partials/sql-settings.php:1–57`, Reporting/Appearance and SQL editor. `assets/sql-settings.js:20–31, 56–62, 101–139`, handler, busy UI and apply/recovery. | Settings is a native dialog with Reporting and Appearance navigation. Reporting owns query editing, row mapping, testing, version history and configured-row application. | Add **External Database — COOP Consumption** under Settings for administrators. Keep connection setup distinct from the operational enrollment and preview dialog. | Medium |
| `partials/report-appearance.php:1–30`, `assets/report-appearance.js:3–63`, `assets/tailwind.input.css:1–7, 60–62`, `package.json`. | Appearance uses preview/apply/cancel/reset and browser storage. Tailwind is compiled locally and the dark variant follows the existing `.dark` class. | Reuse modal styling and theme conventions. Cloud settings and enrollment require server-side persistence. | Medium |
| `query-settings-api.php:3–20, 46–57, 74–109`, `migrations/001-query-settings.sql` and `004-query-apply-operations.sql`. | Session-bound CSRF, source hash and draft revision gate settings calls. SQL definitions, mappings, version snapshots and application receipts use local tables. The inspected endpoint does not check an authenticated administrator role. Deployment access controls were not verified. | Require verifiable identity and endpoint-level administrator/operator roles for credentials, enrollment, preview and apply, retaining CSRF/version checks. CSRF is not an authorization mechanism. | High |
| `lib/base-report.php:95–119`, `base_report_append_formula_audit()`. `base-report.php:151–159`, draft SQL replacement history. | Manual formula changes are audited in a report-scoped sidecar. Generic SQL has configuration history and signed apply receipts. None is an enrolled-account lifecycle audit. | Record membership changes, remote batch status and report application as their own event types. Link them using reviewed operation IDs and actor IDs. | High |

## 6. Proposed External Database Connection

**Verified baseline.** `lib/query-settings.php:12–35`, `qs_config()` and `qs_db()`, load the ignored server-side `config/query-settings.local.php`. The root `.htaccess`, `config/.htaccess:1` and `.gitignore` protect local configuration from delivery or check-in. The reporting connector has a connect timeout, SELECT grant validation and a MariaDB-specific statement budget. Its current connect call does not configure cloud CA validation. This local pattern supplies a storage precedent, not a remote connector.

**Proposed.** Use a dedicated cloud reader and a separate server-side configuration outside the web root, preferably a deployment secret provider. Fields are host, port, database, username, write-only password/secret reference, and TLS CA/certificate options. The read response may show host/port/database metadata as permitted, configured status, and last test outcome. Password fields remain blank on load and require explicit replacement. If a UI accepts a new password, submit it over HTTPS to an admin-only endpoint, persist it server-side, and exclude the value from every response, audit event, exception and log. Prefer provisioning the password out of band. Validate destination against an administrator-controlled endpoint allowlist, including DNS/network egress constraints. Require TLS certificate-chain and hostname verification with no plaintext fallback. Use a least-privilege account with SELECT on the approved cloud tables or views only, plus bounded connect, read and server statement deadlines tested against the deployed driver/server. Test Connection should make a minimal authenticated read probe, rate-limit attempts and display sanitized results. **Risk: High.** No endpoint capabilities, TLS certificate or cloud permissions were tested.

## 7. Proposed Account Enrollment Structure

**Verified.** No COOP enrollment schema appears in `migrations/001-query-settings.sql` through `004-query-apply-operations.sql`. Current bindings in `lib/query-settings.php:111–129` represent per-report-row mappings for a query's area result grain. They do not represent effective-dated account membership.

**Proposed.** Use `coop_consumption_accounts` with ID, stable Summary-area identifier, supplied AcctCode, validated canonical cloud AcctNo, effective-from/to reporting months, lifecycle status, creation/update timestamps and actor IDs. Column type, collation and identifier normalization depend on verified remote schema. Preserve leading zeros. Use an inclusive start and exclusive end, or document another convention and enforce it consistently. For each period resolve the active enrollment from the effective interval, not the current UI status alone. Deactivation closes an interval, and reassignment creates a new one. Keep prior versions and masked audit entries. Compare canonical AcctNo as well as AcctCode so aliases cannot enroll the same consumption twice.

**Recommendation pending business sign-off.** The remote account may physically move or change associations across time. Nothing inspected proves that one consumption account belongs to multiple Summary areas in one month. Allow at most one active Summary area per canonical AcctNo per period. Enforce interval overlap checks transactionally with an identity-level lock plus uniqueness constraints for exact duplicate rows. A future split rule would need explicit allocation weights and approval, not duplicate enrollment. Do not reuse the allocation or reporting alias tables as editable enrollment storage. **Risk: High.**

## 8. External Bill History Query Integration

**Supplied, unverified cloud contract.** Host `paleco.net`, schema `palecxzp_pal_db`, `master.AcctCode` resolves to `master.AcctNo`, and `billhistory.AcctNo`, `BillMonth`, `KWH` provide consumption. The supplied inner join computes a subtotal per account and month. It drops unmatched accounts and can multiply bills if master rows or enrolled aliases fan out. Local tables and project docs with similar names establish neither cloud schema nor billing grain.

**Proposed query pipeline.** Resolve enrolled codes in bounded batches with fixed prepared statements and a generated placeholder list, binding every code as a string. Require exactly one canonical AcctNo per code, detect repeated or ambiguous master rows explicitly, and compare against the validated enrollment identity. Compute `SUM(KWH)`, billing row count and non-null KWH count in a second fixed prepared statement grouped by the deduplicated AcctNo list and a bound BillMonth. The two stages prevent a master join from multiplying billing rows. Use the expected input set to report missing identities and missing billing rows. Distinguish recorded zero, missing billing, NULL/invalid KWH, and areas without enrollment. Do not use `SUM(DISTINCT KWH)` to suppress legitimate bills with equal consumption. Identify the bill business key and rules for adjustments, cancellation, rebills and duplicates with the data owner before deduplicating bill rows.

**Performance and precision.** Prefer bounded batching over one round trip per account. Stage all batches and reject an incomplete run. Check index coverage for AcctCode lookup and an AcctNo/BillMonth composite with actual data and query plans in a later authorized environment. Select index order by measured access pattern. Keep account subtotals and area aggregates in verified exact decimal types or decimal strings with exact arithmetic and no SQL-to-JavaScript float conversion. Confirm an agreed workbook precision boundary before applying totals because the existing formula engine and formatting use floating-point calculation and Excel has finite numeric precision. Do not reinterpret a connection failure or omitted result as zero.

**Existing comparator.** `lib/query-settings.php:75–106`, `qs_parse()` and `qs_execute()`, provides parameterized reporting period bindings, approved local tables, a read-only transaction and bounded results. Keep the same security principles. The new fixed cloud statements should use a dedicated service; the local configurable SQL parser, reporting connection and allowlist are the wrong interfaces for them. **Risk: High.**

## 9. Reporting Month Integration

| Layer | Verified behavior and references | Proposed change |
|---|---|---|
| Selector and persistence | `base-report.php:16–30, 167–183` chooses the available `YYYYMM` and remembers it as `baseReportMonth` in the session. `assets/base-report.js:426–473`, `syncSelectors()` and `loadPeriod()`, power the Year/Month selectors, dirty-draft confirmation, loading and URL update. | Show the existing period read-only in the COOP dialog, with no additional selector. Clear preview when that period changes. |
| Validation and query | `lib/base-report-view.php:6–20`, `base_report_valid_month()` and `base_report_csrf()`, validate calendar month and derive a period-bound CSRF token. `lib/query-settings.php:37–56, 91–97` lists periods from local `overallsalessummary`, validates requested periods and binds the current period in query execution. | Validate against the same server list and bind the selected `YYYYMM` directly to cloud BillMonth, pending actual type confirmation. July 2026 passes `202607`. Bind every review token to that period. |
| Draft and export | `lib/base-report-view.php:12–14` scopes by template hash and report period. `lib/base-report.php:8–18` reads the scoped draft and supports a July legacy fallback. `base-report.php:145–159` exports current or saves with revision checks. `lib/base-report-export.php:70+` updates the workbook month heading. | Use the identical draft conversation and export context. Effective enrollment remains independent of navigation, and saved reviewed values remain reproducible after later enrollment edits. |

**Risk: High.** Reject stale proposals after a period switch, draft revision change or template change.

## 10. Modal and Table UI Proposal

**Evidence.** `partials/sql-settings.php:1–15, 53–56` and `assets/sql-settings.js:27–31, 56–62, 101–139` already implement dialog, busy-state, preview and confirmation conventions. `base-report.php:184–195` provides the icon toolbar and accessible labels.

**Proposal.** Open a separate **COOP Consumption** modal from Settings. Add a toolbar shortcut later only if operator usage calls for it. Close or transition the settings dialog before opening the operational modal to avoid competing focus traps. Use the existing Tailwind theme, native dialog focus, keyboard escape, disabled states, and accessible icon labels and tooltips. Show the selected period, workbook-derived area dropdown, account enrollment/deactivation, account KWH and per-area total, current preview status, last successful refresh, and distinct missing/error outcomes. Mask identifiers for unauthorized or general report viewers. Separate Refresh Preview, review and confirmation, Apply to Report, and Save Draft. **Risk: Medium** for UX; **High** if account details are visible without appropriate permissions.

## 11. Summary Formula and Draft Integration

**Verified current flow.** `assets/base-report.js:137–174, 303–348`, `sqlIndicators()`, `setManualValue()`, `snapshot()`, `applySqlResult()` and Save Draft manage coordinate-keyed overlays. Manual changes clear existing SQL source signatures. `lib/query-settings.php:171–192` verifies editable SQL provenance and only displays its SQL badge for configured targets. `query-settings-api.php:61–109` implements modern Test Query, explicit confirmation, matching, validated calculation and Apply & Save **configuration**, with a recoverable receipt. **Save Draft** is still a separate report action. The older “Apply reviewed values” terminology in `docs/sql-query-settings.md:27` describes the pattern rather than the current UI label. `lib/base-report.php:58–85` merges the working values into the dependency engine. `lib/base-report-export.php:30–70` writes numeric overlay values and formula caches into an exported copy.

**Proposed sequence.**

1. Capture the validated report period, source hash, saved draft revision and current working snapshot. Resolve the exact row and the B26:P26 area targets from the authoritative workbook.
2. Resolve effective enrollment, fetch complete cloud account results, validate data quality and create a server-held preview. Stage exact decimal account and area totals with diagnostics. Do not mutate the report as part of a refresh.
3. Show before/after area values and dependent totals. Surface manual overrides and require explicit confirmation before replacing them. A missing account, missing bill, conflicting owner, stale proposal or failed batch blocks application by default.
4. On Apply, validate actor, session, period, draft revision, working overlay, workbook mapping, enrollment version and proposal expiration. Merge only reviewed eligible coordinates into a copy of `values` and use `base_report_calculate()` before returning the new working model.
5. Keep a COOP-specific source/run receipt and audit. Reuse the UI render contract, not generic SQL definition writes or signed formula replacements. Save Draft must persist the report values and their COOP provenance. A manual edit should supersede the per-cell COOP indicator and retain audit context. Keep the apply idempotent, preserve unrelated edits and never replace previously valid data with zero after a cloud error.
6. Export through the current numeric overlay route. Keep the original workbook and formulas as the source of truth. Keep historical saved report values stable until an operator explicitly applies and saves a new reviewed run.

**Compatibility decision.** Initial/open-period calculation and Restore & Recalculate should consume already accepted local values. If COOP has been applied, the restore operation should retain those input overlays and block conflicting generic SQL reapplication. Existing `$values + $preview` composition gives saved/working values precedence over ordinary preview entries. Review explicitly before making any future automatic refresh policy. **Risk: High.**

## 12. Security and Performance Findings

| Risk | Evidence | Proposed control |
|---|---|---|
| High, credential or endpoint exposure | `lib/query-settings.php:12–35`, `config/.htaccess`, root `.htaccess`, `.gitignore`. | Separate secret, verify TLS and hostname, allowlist destination and restrict egress. Redact credentials and database exceptions. Provide rotation and verify direct HTTP file access is denied in deployment. |
| High, unauthorized changes | `query-settings-api.php:3–11` checks CSRF/hash/revision. Role checks were not found in this route. | Authenticate an identifiable actor, authorize independently for settings/enrollment/preview/apply, use HTTPS with secure session cookies and same-origin POST protection. Review what deployment auth is available. |
| High, duplicate sums or stale proposals | `query-settings-api.php:67–109` uses operation IDs, transactions and draft/version checks. `lib/query-apply-save.php:43–59` checks recovery fingerprints. | Require canonical unique enrollment, short-lived proposals, snapshot checks, a durable unique operation receipt and safe retry. Do cloud reads before acquiring local write locks. Recheck versions at commit. Reconcile SQL receipt state with file-based draft saves explicitly rather than treating the two stores as a single transaction. |
| High, privacy and audit | `lib/base-report.php:95–119` and `migrations/001-query-settings.sql:27+` provide existing audit patterns. | Store identifiers only where required; mask account identifiers in UI, logs and diagnostics. Record who, when, period, mapping/draft versions, counts and state transitions. Restrict audit access, define retention and support reviewable supersession. |
| High, outage or partial data | `lib/query-settings.php:91–105, 192–237` provides bounded local execution and a preview error policy. | Separate remote driver budgets from local settings, restrict concurrency, aggregate in bounded batches, keep last successful data separately from latest attempt, and explicitly reject retries with changed inputs. Render sanitized retryable failures. |
| Medium, operational throughput | Cloud indexes, row counts and SQL version were not inspected. | Check driver behavior and cloud query plans with authorized read-only staging access, tune index candidates, rate limits and batch sizes. Use cached run data only with a visible timestamp and explicit acceptance policy. |

## 13. Risks and Compatibility Issues

- **High.** No cloud schema, unique key, account-code collation, BillMonth data type, KWH precision, billing correction grain, access grants or TLS configuration was verified. Obtain a verified schema extract and controlled examples before querying customer data.
- **High.** The combined Summary areas at J and N require business approval for enrollment assignment. Live codes may map differently from the legacy reporting rollups.
- **High.** Generic A26 configuration is absent now, but the code can create one. Enforce ownership on both generic Apply and COOP Apply. Keep independent protections for Q26:R26 and the S26 comparator.
- **High.** Zero, NULL, no account, no enrollment, no bill and failed read have distinct meanings. Default to preserving the prior report unless a documented exception is explicitly reviewed.
- **High.** The application has mixed DB and file persistence. A successful cloud fetch, a reviewed overlay, and a saved report are distinct states. Build recoverable receipts and test stale/duplicate operations rather than assuming one transaction spans all work.
- **Medium.** A future reference workbook can move or rename area headings. An unresolved, changed, merged, hidden or noneditable target must stop application pending mapping review. Fixed A26/B:P coordinates are inspection evidence for the current template, not a migration strategy.
- **Medium.** Current workbook math and export impose numeric limits. Decide scale, allowable signed values, maximum precision and rounding at the report boundary with the data owner. Preserve an exact source snapshot separately where reporting precision is lower.

## 14. Recommended Implementation Architecture

1. **Workbook resolver.** Build on `report_area_headers()`, label discovery, source-cell metadata and report eligibility. Resolve an approved stable Summary-area identity to its exact current input address. Fail closed on ambiguous matches or structure changes.
2. **Cloud reader.** Dedicated fixed prepared queries, verified TLS, least privilege, no shared reporting connection and no access from browser JavaScript. Return typed account results and diagnostics to the service.
3. **Local enrollment and run service.** Separate effective-dated storage, actor-audited changes and immutable period-specific review snapshots. Version inputs and bind each run to the source workbook and enrollment set.
4. **Reviewed application.** A dedicated endpoint and receipt validate draft, working overlays and ownership before staging and recalculating. Reuse the report UI and Save Draft contract. Preserve prior states on any failure.
5. **Operational oversight.** Credential rotation, availability monitoring, limited retries, masked diagnostics, audit review, workbook compatibility checks and reconciliation after partial application/save failures.

**Risk: High** until account identity, permission and ownership policies are agreed. All components above are proposed.

## 15. Proposed Implementation Phases

1. Agree on cloud data contract, TLS/grants, approved roles, combined-area semantics, duplicate/adjustment policy, exact decimal rules and row-26 ownership.
2. Implement and test the resolver, local schema/versioning and security boundary against sandbox data.
3. Build read-only remote preview against an authorized staging endpoint. Validate missing/zero/duplicate scenarios and measure batches/timeouts.
4. Add Settings administration and operational COOP modal. Keep preview read-only as the first rollout milestone.
5. Add reviewed apply, provenance, Save Draft/export and undo or recovery policy. Run regression and failure tests before enabling the writer.

## 16. Files That Will Need Modification

These are existing paths observed in the project and proposed touchpoints. No module-specific filenames are assigned here.

| Existing path and anchor | Proposed work | Risk |
|---|---|---|
| `base-report.php:139–159, 184–236` | Mount the operational dialog or entry point and integrate reviewed provenance with save/restore if required. | High |
| `lib/report-areas.php:8–27`; `lib/base-report.php:22–85` | Reuse workbook area and calculation helpers. Add resolver/validation only where existing contracts are insufficient. | High |
| `partials/sql-settings.php:1–15`; `assets/sql-settings.js:56–62, 101–139` | Link to COOP administration/operations and coordinate draft and dialog guards. Retain SQL editor responsibilities. | Medium |
| `query-settings-api.php:60–109`; `lib/query-settings.php:62–90, 184–237` | Enforce COOP ownership conflict on generic row-26 actions if ownership is activated. Keep the existing local connector and parser. | High |
| `assets/base-report.js:303–348, 426–465`; `assets/tailwind.input.css:1–7, 60–62` | Integrate receipt application, dirty state, provenance and period reset. Include new production UI in the CSS build and regenerate `assets/tailwind.css`. | Medium |
| `lib/base-report-export.php:30–70` | Verify existing numeric export against the agreed precision; change only if required by the approved contract. | High |
| `tests/base-report-browser.cjs`, `tests/query-apply-save-browser.cjs`, `tests/base-report.php` | Extend isolated checks for coordinate/period behavior, calculation, conflict handling, draft save and export. Add remote-reader fixtures without using production accounts. | Medium |

New isolated service, API, UI and migration files will be required. Choose their names during implementation. Provision cloud secrets through the deployment environment.

## 17. Database Tables or Migrations Required

**Verified baseline.** `migrations/001-query-settings.sql` through `004-query-apply-operations.sql` provide generic SQL configuration, calculation settings and operation receipts. They do not represent cloud credentials, enrollment or COOP run history.

**Proposed additive local migrations.**

- **Required:** `coop_consumption_accounts` for effective enrollment and versioned account-to-Summary-area assignment. Add indexes for canonical account and effective periods, exact duplicate constraints and transactional overlap prevention.
- **Required capability:** Durable append-only enrollment events and idempotent refresh/apply receipts. Use dedicated local tables if existing audit storage cannot provide these guarantees. Store run/period/status, actor, template hash, enrollment version, normalized proposal digest, counts, sanitized diagnostics, reviewed aggregate snapshot and draft linkage. Apply proper retention and minimize identifiers.
- **Optional:** Explicit stable area-identity mapping table if the workbook resolver and reviewed reporting-area identifiers cannot safely resolve combined headings across template versions.
- **Deployment prerequisite:** Separate read-only cloud account or views and verified read performance. Any remote grant or index change requires separate review and authorization. No schema change is proposed just to store a database password. **Risk: High.**

## 18. Acceptance Criteria

- Excel-derived area list matches B:P in the current Summary and protects Q26:R26 formulas and S26. Moving a heading in a fixture either resolves through approved identity or rejects the run without touching the report.
- Multiple accounts in one area sum correctly for the selected `YYYYMM`, including July 2026 as `202607`. Combined areas follow the approved grouping. Leading zeros and decimal precision follow the verified contract.
- Duplicate or overlapping enrollments and ambiguous canonical account lookups are rejected. Missing records, legitimate zero, NULL data and failed connection remain distinguishable. Partial batches cannot change report values.
- Cloud credentials never appear in HTML, browser responses or logs. Invalid TLS chains/hostnames fail. Unauthorized administration, enrollment, refresh and apply fail independently of CSRF validation.
- Preview is read-only. Apply validates exact targets, versions, period and reviewed contents. Repeat requests are idempotent. SQL ownership conflicts and stale report changes fail clearly. Concurrent operations cannot apply two conflicting snapshots.
- Reviewed values recalculate B21:P21, Q26 and R26 as expected. Save Draft, period switching, reopening historical reports, Restore & Recalculate and Excel export pass regression checks. A failed refresh preserves previously saved data and audit traces.
- Tailwind dialog works on narrow and desktop layouts and in both themes. Keyboard operation and error focus are verified. The UI shows per-account outcomes only to authorized users and shows the last successful run separately from failures.
