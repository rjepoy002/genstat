# Batch A safe cleanup - completion report

Completed 2026-11-17 (Asia/Manila). Scope: approved Batch A only. No Batch B or C refactoring, complete source-file deletion, migration, production database change, commit or push was performed.

## Files modified and exact removals

| File | Removed | Preserved |
| --- | --- | --- |
| `index.php` | Four `ui_icon()` dictionary entries: `shield`, `info`, `sheet`, `copy` | Function, every called icon, routes, annual editor, worksheet navigation, hidden `#period` and report markup |
| `assets/app.css` | Retired selector occurrences for `#formula-setup-command`, `.setup-content`, `.setup-note`, `.setup-content > #formula-copy-setup` and the `.report-switcher` family, including responsive rules | Every other selector/declaration and their order; shared dialog, button, accessibility and report styling |
| `lib/formula-auth.php` | Only `require_formula_authorization()` and `unlock_formulas()`, including their unused function bodies | The file, `formula_authority_state()`, `formula_authority()`, `formula_auth_status()`, exception class, CLI setup/status implementation and active per-cell confirmation module |
| `query-settings-api.php` | Only the write-only `$_SESSION['queryTests'][$token]` assignment | Token generation and `testToken` response, result cache, SQL validation, proposal storage, fingerprints, confirmations, Apply & Save, history and recovery |

CSS removal affected **13 selector occurrences**: 12 complete obsolete rules and the `.setup-content` member of one shared selector group. The shared group still contains `dialog form` with its original declarations. The generated Tailwind CSS was not rebuilt or changed because its source was not changed.

This report (`docs/batch-a-cleanup.md`) is the only documentation addition. Existing test suites also regenerated their ignored screenshot artifacts; no test source was changed.

## Verification

Passed:

- PHP syntax checks: **66 files**, including configuration files.
- JavaScript syntax checks: **32 files**.
- No runtime/tool references remain to the removed functions, retired selectors or `queryTests` session storage.
- All literal `ui_icon()` calls still resolve to retained dictionary entries; hidden `#period` remains.
- Structural CSS comparison against the pre-cleanup snapshot confirms every retained selector group and declaration block is unchanged and in the same order.
- `tests/query-settings.php`: real read-only SQL, mappings, limits, timeout and denied source UPDATE privileges.
- `tests/query-calculation-settings.php`: **49 assertions**, including formulas, mappings, approved F18 and outside-row protection.
- `tests/sql-formula-replacement.php`, `tests/brookes-point.php`, `tests/base-report-view.php`.
- `tests/auth-setup.php`: retained authorization-status helpers checked using disposable temporary state, without writing live authentication files.
- `tests/query-parser.cjs`: seven-table allowlist and unsafe SQL rejection preserved.
- `tests/query-apply-save-browser.cjs`: real isolated lifecycle, configuration/history persistence, rollback on an injected write failure, duplicate request replay, lost-response recovery, session isolation, confirmations, F18 replacement, row independence, disable/restore, Save Draft and Restore Template Formula.
- `tests/sql-settings-ui.cjs`: current desktop/tablet/mobile layout, controls, matching, preview, confirmation/expiry explanations and focus/close behavior. Mocked review/history states do not save live configuration.
- `tests/query-calculation-browser.cjs`: live read-only query/formula preview, manual mapping/reset, cursor insertion, F18 and responsive layout.

The isolated integration suite used a disposable writer database/account, application session directory and draft storage. It removed its own fixtures after completion. No state-changing test was run against live workbooks or saved configurations. Live annual editor loading/sync/export was deliberately avoided because that route may trigger workbook refresh. Its markup, called icon definitions and all retained CSS rules were verified unchanged; the focused report and SQL Settings were exercised in browser tests.

## Stored-state verification

Pre/post SHA-256 comparisons confirmed:

- All **171 existing workbook, draft, backup, key, lock-content and configuration files** are byte-identical.
- No new or deleted stored-state files were found under the inspected workbook/storage/configuration locations.
- All four live SQL configuration tables retained identical row counts and content hashes: 2 query definitions, 2 row mappings, 2 history rows and 2 operation receipts.
- The source reader remained read-only; production permissions and the seven-table allowlist were not edited.

Only disposable test database objects/state were created and removed. Live billing tables, allocation rules, area mappings, saved SQL and Excel formulas were untouched.

## Dependencies and scope limits

No unexpected active runtime dependency was found. The historical `tests/ui.cjs` still mentions the retired password-setup UI selector; this was already identified in the inspection report. It was not rewritten or used as a current-UI regression because that would enter Batch B/C. No authentication behavior in the active application was changed.

Configuration History, annual workbook editing, synchronization/undo, internal save services, rejected standalone-save guard, current validation token presentation, review snapshots and recovery receipts remain in place. No further cleanup was added.

## Diff summary

Relative to the exact pre-cleanup working-tree snapshot:

| File | Added lines | Removed lines | Bytes reduced |
| --- | ---: | ---: | ---: |
| `index.php` | 0 | 4 | 232 |
| `assets/app.css` | 5 | 5 | 987 |
| `lib/formula-auth.php` | 0 | 21 | 1706 |
| `query-settings-api.php` | 1 | 1 | 98 |
| **Code total** | **6** | **31** | **3023** |

The CSS/API additions above are replacement lines after removing substrings from existing compact lines, not new functionality.

Git's tracked-file diff for these paths reports **3 files, 7 insertions and 30 deletions** because `index.php` already contained two unrelated added routing lines before this task, and `query-settings-api.php` was already untracked. The task-relative snapshot figures above isolate Batch A accurately. Existing unrelated working-tree changes and untracked status were preserved; nothing was staged, committed or pushed.
