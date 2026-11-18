# GENSTAT row-level SQL formula replacement

> Updated policy (2026-11-17): approved row 18 includes Excel formula targets with or without the Formula Bar. The prior opt-in/protected-F18 descriptions below are historical. See [Approved SQL row overwrite](approved-sql-row-overwrite.md).
Implemented 13 November 2026, Asia/Manila.

## Root cause and solution

The source `Jul26!F18` is genuinely a formula (`=3011664+767072`), with cached result **3,778,736.00**. Its earlier exclusion was correct under the previous non-formula-only SQL policy. The new requirement explicitly authorizes a different policy for individually configured SQL rows.

The solution adds a **default-disabled row permission** and a separate approved SQL replacement overlay. It does not reclassify the source formula as an ordinary writable cell, modify the template, unlock manual editing, or change the approved SQL. F18 is eligible for a tested candidate configuration only when its row opts in, and eligible for actual application only when that tested permission and its area mapping have been saved and the replacement is explicitly confirmed.

## Row permission and database change

Migration `migrations/002-row-formula-replacement.sql` adds only:

```sql
ALTER TABLE genstat_row_query_mappings
 ADD COLUMN allow_formula_replacement BOOLEAN NOT NULL DEFAULT FALSE;
```

The migration has been applied locally. Existing rows default to false; their SQL, bindings, values, enabled state and timestamps are not rewritten. No new tables, users, roles or authentication features were added. Existing configuration history snapshots that lack the field load as false.

For another installation, run the existing initial setup first if needed, then:

```powershell
php tools/migrate-row-formula-replacement.php
npm.cmd run build:css
```

The migration CLI is idempotent. It uses the existing administrative setup connection only for this additive schema operation. Normal queries still use the constrained SELECT-only account; configuration changes still use the existing configuration-only writer.

## SQL settings and eligibility

The modal now includes **Allow SQL to replace formulas in this row**. It defaults off and becomes available when an enabled row mapping has a query identifier and SQL candidate. Checking it is a candidate for Test Query, not permission to alter report data. It persists only after a successful test and Save Configuration. Changing the flag invalidates the previous successful test.

The normal row dropdown remains derived from rows having ordinary writable area inputs. Formula-only calculated rows and the five hidden rows remain excluded. Within an opted-in mixed row, ordinary/shared formula cells may be mapped if they have an actual area heading, valid master-area binding, valid numeric SQL result and valid merge ownership. Explicit read-only cells, covered merged placeholders, array formulas and data-table ranges remain protected. Other rows retain their own independent default-off permission.

The existing header parser and explicit aliases resolve **Brooke's Point → AreaID 12**. For Total KWH Sold this produces **F18**, without a cell-specific exception. With the flag on, there are 15 eligible targets. **G18 remains Roxas / AreaID 2**. Original source metadata, formula indicators and manual-edit policy remain unchanged.

Save Configuration persists the flag with the row binding and preserves the previous complete configuration snapshot. Reusable query SQL definitions are not modified merely by changing this row permission. Opening settings, testing, saving a setting and ordinary page loading never apply values or refresh the source workbook.

## Preview and confirmed application

Test Query still validates the read-only SQL, bound worksheet month, output columns, all areas, duplicates and numeric values. It now also shows target comparisons and formula-replacement warnings. No replacement is performed during testing. Unauthorized formula targets retain the earlier protected-target error.

Review Proposed Changes displays:

- Cell, GENSTAT row, area, current value, proposed value and difference.
- Existing working formula where present, with the original source formula retained separately in metadata.
- **Normal SQL update**, **Manual value conflict**, **Formula replacement**, or **Unchanged**.

For the supplied example, F18 shows **3,778,736.00 → 3,776,446.00**, difference **−2,290.00**, existing formula `=3011664+767072`, change type **Formula replacement**.

The user must check both the ordinary update confirmation and the additional formula warning:

> This update will replace an existing Excel formula with a SQL value in the generated report.

The server separately requires `confirmFormulaReplacement=true`; hiding/enabling a browser button cannot bypass it. Before application it checks the proposal token/expiry, template, current live overlays, saved draft revision, complete saved configuration snapshot, current area metadata and each target's saved row permission/binding. A disabled/changed/stale permission requires another review. The entire batch is validated before any overlay is built; errors produce no partial report/draft change. SQL is not executed again during Apply.

An untouched source formula is still a replacement action even if the numeric SQL result happens to match its calculated value. A previously approved SQL value that matches a new result is Unchanged and is not silently reapplied.

## Working calculation and manual protection

Normal SQL inputs continue to use the existing `values` draft map. Approved formula replacements use a distinct `sqlReplacements` map, never the ordinary manual-value map and never a fabricated Excel formula such as `=3776446`.

The calculation adapter makes an in-memory working copy, substitutes the approved numeric value and recalculates dependents through the existing formula engine. Shared formulas are materialized in that working copy before removing a shared anchor, preserving sibling calculations. Source layout/formula metadata is untouched; the global engine and annual workbook calculations were not changed.

After application, the report shows an SQL indicator, the numeric value in a read-only formula bar and the retained template formula in the tooltip. It does not create a manual input editor or grant formula-unlock permission. Unrelated formulas remain protected. Changing a replacement's numeric payload through an ordinary request is rejected.

Each replacement has signed provenance: source hash, worksheet/ref, stable row key, query identifier, mapping revision, raw numeric value, original template formula, formula being replaced, approval time and approval ID. The server signs it with a private key in ignored/HTTP-protected `storage/sql-replacement.key`. Clients cannot manufacture or edit approval metadata. New/changed submitted records also require the confirming session grant or an identical already-saved record; an old removed approval cannot simply be replayed.

## Draft persistence, history and restoration

Application updates the existing **live working report**, as before. It marks the report unsaved; **Save Draft** persists it. Leaving unsaved work still triggers the existing unload warning. No new autosave or Excel synchronization behavior was introduced.

Save Draft writes the ordinary values, manual formula overlays, `sqlReplacements` and `sqlHistory` together under the existing revision lock and atomic JSON replacement. Existing unrelated draft fields are retained. Successful persistence consumes the transient approval grants. Reloading, including with a fresh browser session, validates the signed saved metadata and recalculates using the approved numeric value; it does not restore the source formula over it or run SQL automatically.

Committed replacements/restorations append audit entries with previous/new provenance, cell, action, timestamp and draft revision. Configuration history independently retains the row flag for reviewed rollback.

Selecting an SQL-applied cell enables **Restore template formula**. The action requires confirmation, removes only that cell's replacement, restores the formula from the clean source and recalculates. Save Draft persists that restoration and appends a history entry. Removing a saved replacement by simply omitting it from an API request is rejected. Manual formula editing requires restoration first and then the existing per-cell formula-unlock confirmation.

Disabling the row flag prevents future SQL replacements but does not retroactively discard approved/saved SQL values. If its unchanged query still returns a protected area's result, the ordinary mapping validation remains active; users must explicitly adjust exclusions/mappings and retest rather than having the application silently discard that area. Tests disabled the flag with an explicit AreaID 12 exclusion. Explicit template restoration remains available even after disabling the row mapping.

Back up `storage/sql-replacement.key` with signed drafts when moving/restoring the application. Losing that key produces a validation error, not silent fallback to source formulas.

## Tests performed

`php tests/sql-formula-replacement.php` passes:

- All 15 targets with opt-in; F18 / AreaID 12 and normal G18 / AreaID 2.
- Default-off behavior and formula-only row exclusion.
- Numeric F18 = 3,776,446.00 and recalculated Q18 = 38,724,209.00.
- Preservation of manual B11 input outside the sold row and original formula metadata.
- Manual formula overwrite rejection, unrelated row protection, tampered approvals and conflicting manual formula overlay rejection.
- Array/data-table and covered-merge restrictions.
- Shared-anchor replacement preserving its sibling formula calculation.
- Byte-identical source workbook and unchanged source styles/classifications/formulas.

`node tests/sql-formula-replacement-browser.cjs` passes the full live lifecycle using a reserved read-only fixture query, not a replacement for the approved business calculation:

1. Default off, blocked preview when off, 15-row preview when on and automatic F18 → 12 binding.
2. Successful-test invalidation when the flag changes; saving does not alter F18.
3. Review shows the exact original formula and −2,290.00 difference; unchanged Roxas is identified.
4. Ordinary confirmation alone is insufficient; the server rejects missing formula confirmation.
5. Explicit application changes only F18 for this fixture, preserves other sold values/manual inputs and recalculates dependencies.
6. SQL indicator/read-only manual behavior; Save Draft includes signed provenance and audit history.
7. Reload and a fresh browser session retain 3,776,446.00.
8. Disabling the flag blocks future formula targets while preserving the approved value; configuration history loads the earlier flag into the editor without applying it.
9. Explicit restoration, saving and reload restore the source formula; replaying the removed approval is rejected.

Desktop review/applied screenshots were visually reviewed. Temporary fixture configurations/history and the temporary test draft were removed; existing configurations/drafts are protected by the test's pre-existing-data checks.

Regression checks passed: the existing query-settings lifecycle, SQL parser/security/mapping tests, Brooke's Point aliases, base-report editing/draft/confirmation tests, source/cache/formula tests, hidden dependency tests, all requested desktop/mobile compact layouts, editing-policy, formula-reference and edit-history tests. PHP/JS syntax, Tailwind compilation and whitespace checks passed. The migration's second run correctly made no changes.

The untouched source still has **402 worksheet formulas / 312 report formulas**, with all **312 baseline report cache comparisons matching** and **96 database-input classifications**. An approved F18 replacement removes that one formula only from the in-memory working calculation; source counts remain unchanged. Existing external recovered-sales caches and off-table source `#REF!` issues were not changed.

## Excel export compatibility status

The focused base report still has **no Excel export action**. As explicitly permitted by the request, no speculative exporter was added. The existing annual editor/export implementation remains unchanged and uses a different source/draft namespace.

The necessary export metadata is now persisted. Its future adapter must:

1. Read the clean base XLSX and the **saved, reviewed** draft; never execute SQL during export.
2. Validate each signed replacement against the source hash, worksheet/ref and source formula.
3. Patch approved replacement cells as numeric OOXML values: for F18, remove `<f>` and write `<v>3776446.00</v>` as a numeric cell, preserving style 93/number format. Do not write inline text or `=3776446.00`.
4. Preserve all other workbook formulas, cell references, sheet names, styles, merges and layout. Shared-group replacements need the existing shared-formula preservation machinery. Do not export the calculation-only materialized layout as if it were an untouched OOXML source.
5. Request Excel recalculation and create a separate download/output, never overwrite the source workbook.

Thus numeric overlay calculation/persistence is verified, but an actual focused-report XLSX export/reopen test remains a future-phase requirement. No claim is made that the annual exporter already consumes these new base-report overlays.

## Files and remaining setup

Modified:

- `lib/base-report.php`, `base-report.php` — working calculation, protected persistence, audit/restoration and initial page metadata.
- `lib/query-settings.php`, `query-settings-api.php` — scoped eligibility, flag persistence/history, review types, snapshot checks and confirmed batch application.
- `assets/base-report.js`, `assets/base-report.css` — SQL-applied indication, read-only behavior and restoration.
- `assets/sql-settings.js`, `partials/sql-settings.php`, generated `assets/tailwind.css` — row opt-in and explicit review/confirmation.
- Documentation links in `README.md`, `docs/sql-query-settings.md`, `docs/brookes-point-mapping.md`.

Created:

- `lib/sql-replacements.php` — signed provenance, submission checks and calculation-only working copy.
- `migrations/002-row-formula-replacement.sql`, `tools/migrate-row-formula-replacement.php` — additive migration.
- `tests/sql-formula-replacement.php`, `tests/sql-formula-replacement-browser.cjs` — focused unit/integration checks.
- `docs/row-sql-formula-replacement.md` — this report.
- Runtime-only ignored `storage/sql-replacement.key` — private approval key, never printed or committed.

Local migration is complete; no business row was opted in automatically. Supply/retain the approved genstat_kwh SQL, enable the flag for Total KWH Sold, verify its 15 area mappings, Test Query, Save Configuration, review and confirm both update controls, then Save Draft. No persisted approved genstat_kwh SQL was available to certify its production arithmetic; it was not reconstructed or modified. The clean base source still supplies only July; other annual worksheet routes remain unchanged.

No source workbook, existing area/source records, administrator authentication, Git branches, commits or pushes were changed.
