# SQL area matching and report calculations

> Updated policy (2026-11-17): approved row 18 includes Excel formula targets with or without the Formula Bar. See [Approved SQL row overwrite](approved-sql-row-overwrite.md).
Implemented in the existing SQL Query Settings workflow. Every eligible SQL-controlled row supports an optional custom formula. Existing configurations continue using their saved expression or value column.

## Workflow

1. Paste approved SQL and Test Query. Returned columns populate automatic matching options and numeric column tokens, even if mappings are incomplete.
2. Choose a matching column and normalized, exact, or manual matching. AreaName is the default when returned. Complete unresolved mappings manually.
3. Enable Custom Formula for the selected row and enter an expression or choose an available preset. Preview Formula evaluates the tested result without changing report values. With the formula disabled, the mapped Value Column supplies the values.
4. Test the complete configuration and select Apply & Save. This saves the SQL and formula settings together and refreshes the selected period. Save Draft persists the applied report values.

Manual selections survive retesting and matching-column changes. Reset to Auto explicitly clears an override. Previously saved mappings are retained. Matching uses source-discovered writable cells, normalized names, valid master AreaIDs, and existing approved aliases. Combined area names and Main are not inferred. Multiple matching rows, including distinct SACodes for one AreaID, remain ambiguous.

## Expressions and safety

Supported grammar: column identifiers, decimal constants, unary +/-, binary + - * /, and parentheses. Ordinary identifiers use `Sales_KWH`; unusual names use `[column name]`, with `]]` representing a literal closing bracket. Column tokens insert at the cursor without operators.

The PHP parser uses BCMath, never executable expressions or SQL evaluation. NULL, non-numeric SQL types, unknown columns, invalid decimal values, division by zero, functions, and code are rejected. Expressions are bounded to 2048 characters, 256 tokens and 32 nesting levels. Decimal values allow 30 integer and 24 fractional digits. Arithmetic uses 24 fractional digits internally; output rounds half away from zero to 12 fractional digits. The workbook's existing cell formatting controls display. Exponent notation is unsupported. PHP BCMath must be enabled.

Preview Formula and automatic matching read a bounded, server-held tested result (five-minute lifetime). Complete configuration validation uses the same result when available. Saved refresh queries execute through the existing restricted executor, with each formula evaluated independently. Apply & Save re-executes the query and validates the expression and target mappings server-side.

Custom formulas compute values for the existing authorized targets of each row. The approved row-18 workbook formula replacements, including F18, are still allowed. Other protected workbook formulas retain their protection. Template, draft, configuration and mapping checks and test expiration remain enforced. Editing settings clears the test authorization.

## Persistence and database changes

Migration `003-query-calculation-settings.sql` adds nullable `calculation_settings_json` to `genstat_row_query_mappings`. Applied locally using the idempotent `tools/migrate-query-calculation-settings.php`. No existing configuration rows or SQL definitions were rewritten. Fresh setup now includes migrations 002 and 003.

The JSON stores `autoMatchColumn`, `matchingMethod`, `mappingSources` (auto/manual/saved per cell), the legacy `formulaTarget` field, `formulaExpression`, and `formulaEnabled`. Existing bindings retain AreaID and SACode. History snapshots preserve this field; loading old history defaults to formula disabled. Report formulas are opt-in; cancelled KWH is never added automatically.

The user approved aligning the reader's SELECT grants with the existing allowlist: removed access to `overallsalessummary_x` and `billadjustment_1`, added access to `overallsalessummary` and `billadjustment`. SQL validation and the allowlist were not broadened. No billing, allocation, area master/mapping, or workbook source data changed.

## Files

- `lib/report-expression.php`: restricted decimal evaluator and automatic matching.
- `lib/query-settings.php`: result numeric types, calculation validation, matching and persistence.
- `query-settings-api.php`: bounded tested-result cache, automatic matching and formula preview.
- `partials/sql-settings.php`, `assets/sql-settings.js`, `assets/tailwind.css`: compact controls, overrides, tokens and history.
- `migrations/003-query-calculation-settings.sql`, `tools/migrate-query-calculation-settings.php`, `tools/setup-query-settings.php`: additive schema support.
- `tests/query-calculation-settings.php`, `tests/query-calculation-browser.cjs`, `tests/query-calculation-lifecycle.cjs`: evaluator, mapping, live preview and lifecycle regressions. Browser screenshots are generated under `tests/query-calculation-*.png`.

## Validation

- New PHP suite: 43 assertions, including live read-only SQL and protected F18 behavior.
- New live browser suite: automatic matching, manual retention/reset, presets, decimal preview, division rejection, cursor insertion, formula protection and mobile width.
- New lifecycle suite: formula persistence/history/restore, computed review/application, stale/confirmation rejection, protected formulas, and optional Save Draft/reload. The local persistence check was skipped because an existing user draft was present; it was preserved.
- Existing SQL parser, query-settings PHP/browser, formula-replacement PHP, base-report PHP and base-report-view PHP suites.
- PHP syntax, JavaScript syntax and Tailwind build.

Lifecycle tests require an empty configuration namespace, use reserved test query names, and remove only their fixture configurations/history. The persistence test skips writing when a user draft already exists and removes its own verified temporary draft. Workbook preservation checks compare source content/checksums. No GitHub push is performed.
