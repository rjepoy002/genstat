# SQL Query Settings UI modernization

> Current workflow: standalone configuration saving has been replaced by selected-row **Apply & Save**. See [automatic saving, history and recovery](sql-settings-apply-save.md). Earlier workflow descriptions below are historical.
Implemented 2026-11-17 (Asia/Manila).

The existing settings module now uses a responsive Tailwind dashboard layout. Existing element IDs, query endpoints, mapping identities, formula calculations, persistence and explicit confirmation requirements remain in use.

## UI changes

- Modal uses 94% viewport width with a 1440px maximum and 94dvh maximum height; sticky context header, internal content scrolling, compact mobile layout and existing Escape/unsaved-close handling.
- Distinct report configuration, SQL editor, result column, formula, area mapping, result preview and reviewed refresh cards.
- Query editor uses monospaced text, a read-only execution indicator and grouped Test Query / Save Configuration / Configuration History actions. Save remains unavailable without a successful test.
- Value/result label is now **Fallback Value Column**, with the requested explanatory text; the underlying field/validation is unchanged.
- Formula card shows target row 18, presets, monospaced expression, clickable numeric chips, expandable syntax help, separate preview action/status and original calculated values without formatting changes.
- Area mappings use one/two/three columns on mobile/tablet/desktop, subtle matching states, readable labels and Reset to Auto. Approved F18 remains included.
- Result/review tables have horizontal scrolling, bounded table viewports, sticky headers, numeric right alignment/tabular typography, readable long formula/warning cells and explicit empty states. No SQL values are rounded or otherwise changed for presentation.
- Review summary shows changes, unchanged rows and errors. The separate Excel formula confirmation is highlighted and remains mandatory when required.
- An accessible message beside Apply explains the actual blocking condition: operation in progress, no review, expired review, review errors, no changes, standard confirmation or formula confirmation. Valid state is also identified.
- Failed review requests clear the previous client proposal rather than leaving an older proposal available. Switching configuration rows invalidates the client review.

## Expiry metadata

The existing review response additionally exposes `expiresAt`, taken directly from the server-held proposal's existing expiry timestamp. A UI timer updates the disabled explanation when that expiry is reached. No server authorization, expiry duration, SQL validation, record fingerprints, formula evaluation or apply confirmation checks were changed. The server remains authoritative and independently rechecks the proposal on Apply.

## Files modified

- `partials/sql-settings.php`: card layout, labels, grouped actions, summaries and accessible Apply explanation.
- `assets/sql-settings.js`: presentation of tables/mapping states, empty states, review summary and Apply explanations/expiry tracking; existing API/configuration handlers retained.
- `assets/tailwind.input.css`: settings-scoped components and responsive styling; isolated from the workbook grid, including specificity needed to override inherited report table rules.
- `assets/tailwind.css`: regenerated using the existing Tailwind build.
- `query-settings-api.php`: additive read-only `expiresAt` response field only.
- `tests/sql-settings-ui.cjs`: new desktop/tablet/mobile UI regression coverage.
- `docs/sql-settings-ui-modernization.md`: this report.

No dependency, database migration, source table, permission, billing calculation, allocation, saved configuration, Excel formula or workbook structure changes were made. No GitHub push was performed.

## Verification

Passed:

- `npm.cmd run build:css`, JavaScript syntax and PHP syntax checks.
- `tests/sql-settings-ui.cjs`: live read-only Test Query, result column controls, auto mapping including F18, manual override/reset, formula preview, desktop/tablet/mobile layout, table numeric alignment/sticky headers, initial empty state, disabled Apply explanations, ordinary versus formula confirmation behavior, expiry, modal closing/Escape/focus and unchanged report snapshot/workbook checksum.
- Save/history and review-state UI cases in that test use explicit intercepted response fixtures; Apply is never clicked. The UI fixture test does not save any configuration or draft.
- `tests/query-calculation-browser.cjs`: live read-only mapping, overrides, formula presets/preview, cursor insertion, invalid division, F18 visibility and mobile layout.
- `tests/reporting-allowlist-browser.cjs`: ten result columns populate matching/formula controls; workbook/report unchanged.
- `tests/query-calculation-settings.php`: 49 assertions.
- `tests/sql-formula-replacement.php`, `tests/query-settings.php`, `tests/query-parser.cjs`, `tests/base-report.php`, `tests/base-report-view.php`.

The existing persistence browser test (`tests/sql-formula-replacement-browser.cjs`) refused to proceed because saved query configurations are now present. Its empty-namespace safety check was retained; existing settings were not overwritten or removed. Full save/history/apply persistence against current saved configurations was therefore not rerun. Their presentation is covered with fixtures; underlying persistence/authorization code is unchanged.

## Screenshots

Generated and visually inspected:

- [Desktop editor](../tests/sql-settings-modern-desktop.png)
- [Desktop formula](../tests/sql-settings-modern-formula.png)
- [Desktop review](../tests/sql-settings-modern-review.png)
- [Mobile editor](../tests/sql-settings-modern-mobile.png)
- [Mobile formula](../tests/sql-settings-modern-mobile-formula.png)
- [Mobile review and expiry explanation](../tests/sql-settings-modern-mobile-review.png)

Screenshots show clearly labeled read-only query fixtures and simulated review states, not certified business totals. Generated test screenshots remain Git-ignored under the existing project policy.
