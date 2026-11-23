# Phase 3 COOP Reviewed Apply and Summary ownership inspection

## 1. Executive summary

**No-go for live Reviewed Apply.** The stored Phase 2 July preview and fresh read-only source lookup agree on one Aborlan record with **1,407.00 KWH**. The working `Jul26!C26` already shows 1,407.00 KWH from the report baseline, and the July draft has no explicit C26 override or verified COOP provenance. The database has no July Apply receipt. The match does not constitute approval of the billing rules or permission to update the report.

The existing Reviewed Apply path can be reused. Before activation, approve the billing contract, bind that approval to each reviewed run, and resolve the overwrite, concurrency and audit gaps described below. Only this document was added.

## 2. Existing Apply architecture

`assets/coop-consumption.js` uses `buttons()` and the Apply click handler to gate the action, request confirmation, compare the active report snapshot to the staged one and submit `run` plus `confirmed: true`. `coop-consumption-api.php` validates report context and current server state, loads stored area proposals under locks, and invokes `base_report_calculate()` in `lib/base-report.php`. `window.genstatReport.applyCoopResult()` in `assets/base-report.js` updates working values and provenance, renders the calculation and sets the unsaved flag. Save Draft is a separate request to `base-report.php`.

## 3. Phase 2 verification

| Check | Observed in this inspection |
| --- | --- |
| Report and area | July 2026, one active matching Aborlan enrollment, code `03-0152-0325`, target `Jul26!C26`. |
| Source | Saved local COOP reader, exact identity resolved from `master`. `coop_bills()` returned one valid July bill, 1,407.00 KWH. No canonical identity was printed. |
| Stored preview | Latest July provisional run returned one billing record, current 1,407.00 and proposed 1,407.00. It was complete, unexpired when inspected, and had no linked application. |
| Browser | Trusted localhost HTTPS reported `localPreviewReady=true`, `ready=false`, `owned=false`, `conflict=false`. Refresh was enabled and Apply was disabled. The live UI reported C26 as 1,407.00. |
| Limits of verification | The browser requested only settings metadata and COOP views. The live preview was verified from existing history and a read-only source query because live Refresh would insert a run and audit records. The end-to-end actions were exercised only against synthetic fixtures. |

## 4. Billing approval gaps

`lib/coop-cloud.php` uses `coop_master()` and `coop_bills()` for exact identity resolution and bound monthly grouping, including count, non-NULL count and raw SUM. `lib/coop-consumption.php` uses `coop_identity()` and `coop_aggregate()` for validation and area roll-up.

| Case | Provisional local | Current reviewed path |
| --- | --- | --- |
| One valid positive record | Candidate; preview only | Included in area total. |
| One zero record | Real zero | Real zero. |
| Missing bills or NULL/invalid KWH | Incomplete | Incomplete. |
| Multiple valid records | Requires billing review; no proposal for that area | Sums all records. |
| Negative KWH | Requires billing review | Included in the signed sum if valid. |
| Corrections, cancellations and replacements | No rule in source or calculation | No rule in source or calculation. |
| Missing, ambiguous or changed identity; duplicate canonical account | Blocks a complete run | Same. Enrollment overlap is also rejected transactionally by `coop_change_enrollment()` in `lib/coop-repository.php`. |
| Retrieval or timeout failure | Failed run and no applicable total | Same. |

Billing must approve the true bill grain, authoritative correction and cancellation source, replacement linkage, signed KWH treatment, and late corrections or reopening of periods. `billhistory` exposes no explicit cancellation, adjustment or replacement field. A sum, row order, equal amounts, or a date-based latest-row rule cannot establish final KWH from this evidence. Fail closed on unresolved cases.

## 5. Review gate analysis

| Gate | Effect |
| --- | --- |
| `GENSTAT_COOP_REVIEWED_TEMPLATE` | Normal Refresh and Apply require an exact match to the layout's SHA-256. The request's workbook hash is independently checked against the current layout for all COOP actions. |
| `GENSTAT_COOP_SCHEMA_REVIEWED=1` | Normal Refresh and Apply require this server flag. Refresh validates actual source column metadata through `coop_cloud_schema()`. The flag does not encode the approved billing rule version. |
| `GENSTAT_COOP_OWNERSHIP=1` | `coop_owned()` enables the ownership predicate globally for that deployment. Apply requires it; Refresh does not. |
| `GENSTAT_COOP_DEV_MODE=1` | `coop_is_local_dev_request()` requires the verified direct loopback listener, addresses and marker, and denies recognized forwarding or identity headers. Approved loopback MySQL also requires `GENSTAT_COOP_LOCAL_PORTS`. |
| HTTPS and source | Local provisional preview and credential actions explicitly require server-verified HTTPS. `coop_connection_input()` and `coop_cloud()` enforce loopback rules or the enabled remote endpoint and CA allowlists, verified TLS, and read-only grants. Reviewed Apply has no independent HTTPS check. |

The dedicated listener has Development Mode and the local port configured. Live billing readiness and ownership are off. Apply rechecks current gates and the enrollment/source fingerprint. Connection version or CA digest changes stale a run. Changing a billing rule or an approval flag off and back on is not itself a version change. A provisional marker is persisted in `coop_runs.result_json` and always rejected by Apply, even after later enabling the review and ownership flags.

## 6. Summary ownership

`base_report_layout()` reads the actual reference workbook. `coop_row()` finds the unique COOP label and `coop_targets()` derives destinations from `report_area_headers()`, requiring unique, editable, unmerged database inputs without effective formulas. The inspected workbook hash was `752c1ad06bd770ea749ef7925a49adbc725ee81b3a7dd6342f5b1585253c3dce`.

The resolver found 15 inputs in `B26:P26`, Aborlan at `C26`, Araceli/Dumaran at `J26`, and Cuyo/Magsaysay at `N26`. `A26` is the static label, `Q26` has `=SUM(B26:P26)`, `R26` has `=+S26+Q26`, and `S26` is manual. While Apply is period-scoped, the existing ownership flag is deployment-wide across report months using the same template.

## 7. SQL Settings conflicts

The live database has no saved mapping for the COOP row and no enabled mapping detected by `coop_conflict()` for its targets. The July draft had no custom formula or SQL replacement overlay on those targets.

`qs_rows()` and `qs_targets()` in `lib/query-settings.php` scope generic SQL targets by report row. When COOP owns the row, `coop_blocks_generic()` suppresses row previews and blocks SQL Apply & Save and receipt recovery in `query-settings-api.php` and `lib/query-apply-save.php`. `coop_conflict()` rejects an enabled COOP row or an enabled binding that intersects COOP destinations. Apply separately rejects runtime formula, SQL replacement, SQL preview or total overlays on a proposed destination. Restore and reapply in `base-report.php` use `qs_period_preview()`, so they inherit the generic row exclusion.

Manual numeric entries remain possible. A manual value already present at Refresh can be overwritten on confirmation. An edit after Refresh changes the working hash and is rejected. A saved SQL-provenance value may remain after disabling its configuration, and COOP currently clears its SQL provenance on replacement. Formula unlock controls effective formulas, gives no additional COOP target rights, and leaves the normal formula-edit policy in effect. Reporting month changes select another draft. Recommend making any remaining manual or SQL-sourced destination value visible in the confirmation and adopting an explicit policy for overwrites and overrides.

## 8. Apply validation flow

| Existing control | Enforcement |
| --- | --- |
| Identity, CSRF, report period and workbook | `coop_actor()`, month-bound CSRF, available reporting month and current workbook hash in `coop-consumption-api.php`. |
| Review, ownership and confirmation | Server-calculated readiness and ownership; boolean explicit confirmation; well-formed run ID. |
| Run eligibility | Stored actor and period, status complete, matching template, unexpired deadline, matching draft revision and no provisional marker. The published run lifetime is 15 minutes. |
| Enrollment and source | Recomputes the hash of effective enrollments, target map and source snapshot under the enrollment and configuration locks. This is an effective snapshot fingerprint, not a separate enrollment revision counter. |
| Working values and destination ownership | Recomputes the submitted working hash and rechecks period preview authorization, SQL conflicts and destination protections. Only current non-null area proposals from the stored result are applied. |

The proposals are server-staged. Apply revalidates current draft and configuration, while the working overlays come from the client and must match the stored input hash or an existing receipt's output hash. Apply does not reread billing rows. Corrections between Refresh and Apply can therefore leave a time-valid but obsolete run. The draft lock serializes same-period draft and SQL Apply operations. The shared connection-file lock blocks in-flight settings replacement. The enrollment singleton and run locks serialize the corresponding writes. SQL mappings are shared across periods, while the draft lock is per period and `qs_load()` performs a nonlocking read; a cross-period or administrative configuration change is not protected by a shared ownership lock. Test this race and use a common lock or revision check before activation. Consider the timing between releasing Apply locks and the later separate Save Draft as a distinct stale-write case.

## 9. Confirmation UI

The shared Tailwind dialog `#report-confirmation` in `base-report.php` and `confirmAction()` in `assets/base-report.js` provide explicit Continue/Cancel. The COOP caller shows the reporting month/year, account count, affected area/cell count, and aggregate KWH. The per-area before, after and difference are in the underlying preview. The confirmation does not repeat them or show provenance, and incomplete or disputed runs cannot reach confirmation.

Extend the existing dialog with the title **Apply COOP Consumption to Summary?**, the chosen period, and a responsive table showing Area, Current KWH, New KWH and Difference. Mark manual or previously SQL-sourced values to be replaced. Keep keyboard focus, Escape/Cancel, explicit confirmation, and the separate Save Draft action.

## 10. Draft and Save Draft

`applyCoopResult()` updates working data, sets `dirty`, and clears the existing restore recovery token. There is no COOP-specific Undo. Existing formula Undo and Restore confirmation are separate. Checkout of another month uses `loadPeriod()` with a discard prompt for unsaved changes, and reload uses `base_report_read_draft()` to recover saved overlays. Save uses an exclusive per-period lock, validates the revision, records provenance, and advances the revision. Apply receipts can exist even when the operator later discards working values.

Export uses the **current working snapshot**, including unsaved applied values, through the report Export action and `base_report_export()`. That is consistent with working-report semantics. Clarify the distinction from Save Draft in the confirmation. Add a regression for closing/reloading without saving and for concurrent revision changes between Apply and Save.

## 11. Formula protection

The Apply loop skips null proposals, checks the current cell mapping and overlay ownership, and updates only numeric COOP inputs. It leaves Q26, R26 and S26 untouched. `base_report_calculate()` recalculates dependencies and export preserves effective formulas. A same-row formula lock state cannot expand the target set. Formula restoration starts from submitted working values and clears formula overlays before reapplying eligible SQL configurations. When ownership is active, the generic row is skipped. However, restore/undo snapshots omit `coopSources` and the opposite-path regression covers only selected value/provenance scenarios. Verify an unsaved and a saved COOP value through Restore, Undo Restore, and Export, including manual changes. Apply also lacks a dedicated check for new calculation errors like the one used by Restore.

## 12. Audit and history

`migrations/005-coop-consumption.sql` defines the three tables. `coop_runs` stores actor, period, template hash, combined enrollment/source hash, draft revision, working hash, result JSON, count, expiry and timestamps. `coop_applications` has one receipt per run, with actor, draft revision, resulting hash and timestamp. `coop_audit()` records action, actor, period, timestamp and JSON details. Existing actions cover refresh, generated/rejected previews, applied areas with before/after values, transactional domain rejections, enrollment transitions, and versioned connection settings operations. Rejections before locking or transaction entry are not uniformly audited. Selecting a reporting period does not emit a COOP audit event.

Duplicate Apply of a still-valid run accepts the original or recorded result working hash and avoids duplicate receipt and apply audit. It still performs the other validity checks. Signed period/cell/run provenance links a saved Summary value to its preview, and the draft distinguishes saved application from manual override. Add structured approval and billing policy IDs, distinct source configuration version and enrollment fingerprint, exact cell and values, and explicit result. Existing JSON fields can carry those details without exposing credentials or customer identities. Treat a working Apply and a saved draft as separate outcomes in history.

The July provisional run history joins to `consumption-refreshed` and `preview-generated` audit actions. No July application receipt was present.

## 13. Security findings

The trusted localhost browser used HTTPS, and the dedicated development identity was accepted. `coop_actor()` validates server identities and roles, `coop_is_local_dev_request()` confines anonymous development access, and the API checks a month-bound CSRF token. Credential administration and local-only preview explicitly require HTTPS. A future reviewed rollout should also require HTTPS for reviewed Refresh and Apply, and use a named operator for real report writes. The global ownership flag by itself does not bypass the current role, CSRF, readiness, provisional-run, ownership conflict or stale checks.

Source credentials are stored outside the web root under authenticated AES-GCM encryption, passwords are omitted from browser responses, and the approved source performs bounded prepared reads under read-only grants. A reviewed run cannot be promoted from a provisional one by a client flag. Revalidate the source policy and policy version at Apply. No credentials or canonical identities were printed.

## 14. Test coverage

| Area | Existing coverage and gaps |
| --- | --- |
| Reviewed, provisional, unauthorized, ownership, idempotent Apply | Covered in `tests/coop-consumption-browser.cjs`, including a provisional run rejected with gates enabled. |
| Missing, zero, multiple, negative, identity resolution | `tests/coop-consumption.php` covers local classification and reviewed aggregation compatibility. Browser fixtures cover incomplete billing, source failure, effective intervals, area totals and leading zeros. The approved correction and replacement contract needs new fixtures. |
| Changed enrollment and source | Browser tests stale out affected previews after enrollment, replacement or removal. |
| Expiry, month, workbook and draft changes | Expired connection-test receipts are covered. Period switching and draft concurrency are exercised separately. Add explicit **Apply** rejection tests for expired preview, cross-period run, changed workbook hash and changed draft revision. |
| Conflicts, formulas, comparator, Save and export | Browser tests cover SQL conflict, generic ownership rejection, formula snapshot preservation, saved draft and formula-preserving export. Add explicit S26 comparator and Q26/R26 effective formula assertions through Apply and Restore/Undo, and a cross-period SQL race. |
| CSRF and concurrent state | Invalid CSRF is covered for view/enrollment. Add a direct invalid-CSRF Apply assertion, two windows with different overlays, billing correction between Refresh and Apply, and rejected Apply audit coverage. |

All five targeted suites passed: COOP policy and aggregation, COOP browser, base report browser, SQL Settings UI, and native MySQL TLS. Write paths ran only against isolated synthetic fixtures.

## 15. Blocking issues

Formal billing grain and correction rules are outstanding. The reviewed path currently accepts multi-record and signed totals under its existing sum policy. There is no run-bound approved billing rule ID, and locally reviewed Apply lacks a separate HTTPS/named-writer requirement. Ownership scope, manual-overwrite handling, SQL concurrency, and the behavior of historical or unsaved values need explicit decisions and regression evidence.

## 16. Recommended Phase 3 implementation

| Workstream | Implementation |
| --- | --- |
| A. Billing rule approval | Agree on authoritative bill identity, correction data, negative KWH and late-change cutoff. Fail closed until the rule is defined. |
| B. Workbook and schema review | Verify current reference hash, source types and source policy. Version the approved billing contract and attach the version to every eligible run. |
| C. COOP Summary ownership | Approve scope for workbook-derived inputs and the reporting periods covered. Resolve conflicting SQL bindings explicitly and synchronize cross-period ownership checks and writes. |
| D. Reviewed Refresh | Reuse `coop_master()`, `coop_bills()` and `coop_aggregate()`; use the approved rule and provenance. Keep the local provisional action distinct and permanently non-applicable. |
| E. Apply confirmation | Reuse the existing Tailwind dialog with the before/after table, operator confirmation, and stale-state recheck. |
| F. Summary cell updates | Reuse Apply's target resolver, hash/lock guards, and staged server result. Require HTTPS and an approved operator for live writes. Reject new conflicts or stale billing rules. |
| G. Draft and formulas | Reuse `applyCoopResult()`, `base_report_calculate()` and per-period Save, preserve Q26/R26 and S26, and test restore, Undo, export and revision boundaries. |
| H. Audit and history | Extend run result and audit JSON, retain idempotent receipt, and distinguish applied-working and saved events. |
| I. Regression | Use synthetic invalid/ambiguous billing, policy-version changes, race and stale tests. Require fresh reviewed-run evidence and one authorized period reconciliation before rollout. |

## 17. Exact files requiring changes

- `lib/coop-cloud.php`, `lib/coop-consumption.php`, and `coop-consumption-api.php` for the approved rule and its run identity, verified-source and Apply policy, checks, and audit details.
- `lib/coop-report.php`, `lib/query-settings.php`, `query-settings-api.php`, and `lib/query-apply-save.php` if ownership scope or SQL locking changes.
- `assets/coop-consumption.js`, `partials/coop-consumption.php`, `assets/base-report.js`, and `base-report.php` for confirmation, provenance or restore safeguards. Reuse `lib/base-report-export.php` unless verification exposes a gap.
- `tests/coop-consumption.php`, `tests/coop-consumption-browser.cjs`, `tests/query-apply-fixture.php`, and `tests/base-report-browser.cjs` for the new cases.

Migration 005 is already in use. Existing JSON fields and the receipt key are sufficient for the first reviewed-rule rollout unless structured cross-run reporting or new transactional ownership metadata requires a dedicated schema later.

## 18. Required approval decisions

- Billing owner approval of the authoritative contract, finality/versioning, and what to do with missing or disputed records.
- Workbook and source-schema reviewer approval of the exact hash, Summary targets and source schema. Only an approved deployment sets `GENSTAT_COOP_REVIEWED_TEMPLATE` and `GENSTAT_COOP_SCHEMA_REVIEWED=1`.
- Report owner approval of the intended period scope, whether existing manual values may be replaced, how SQL conflicts will be resolved, and who may write. Enable `GENSTAT_COOP_OWNERSHIP=1` only after the accepted safeguards and operator verification. Keep development access and remote activation separate.

## 19. Rollback strategy

Disable ownership first to block new Apply, then withdraw billing readiness if reviewed Refresh should stop. Historical provisional runs remain unusable. Keep audit and application receipts. Discard unsaved working changes by reloading. Restore a saved report only through an authorized period-scoped recovery from a verified pre-rollout draft or an audited correction, using the revision checks. Disabling ownership can expose old generic SQL mappings, so verify those mappings before resuming SQL reapplication.

## 20. Final go/no-go

**Go** for synthetic regression and designing the reviewed-rule, ownership and confirmation improvements. **No-go** for enabling live ownership or applying values until formal billing and workbook/schema approvals, concurrency and transport safeguards, and the outlined rollout checks are complete. No live Apply, Refresh, save, ownership change, configuration edit, or database record mutation was performed.
