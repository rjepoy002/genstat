# GENSTAT final cleanup

2026-11-18 · Asia/Manila

## A. Executive summary

Batch C2 Phase 2 closes the GENSTAT cleanup initiative. It consolidates regression coverage, documents the current application, and explicitly defers the conditional retirement of the legacy formula CLI. The compact report, annual workbook editor, and SQL settings lifecycle remain supported.

## B. Legacy authorization decision

| Retained file | Dependency and purpose |
| --- | --- |
| `lib/formula-auth.php` | Provides legacy authorization status helpers imported by the CLI and its isolated compatibility test. |
| `tools/formula-admin.php` | Exposes the manual `status` and `setup` commands. Setup writes legacy password state and backs up an existing copy. |
| `tests/auth-setup.php` | Exercises legacy status outcomes using disposable state and remains in the unit manifest. |

Repository tracing covered PHP imports, CLI entrypoints, the test manifest, package scripts, local workflow and maintenance files, and documentation. The CLI and compatibility test are the only executable callers of the legacy library. The Windows scheduled tasks visible to the current account had no matching action. The available PowerShell history contains one bare CLI invocation without a subcommand. Project storage contains neither a legacy password configuration nor its backups. Current manual or external use remains unconfirmed, so all three files are retained. Earlier retirement approval covered the two Phase 1 SQL fixtures only. [Batch C inspection](batch-c-inspection.md) · [Phase 1 retirement](batch-c2-phase1-cleanup.md)

The annual renderer and API load `lib/formula-confirmation.php` via `lib/app.php`. The compact report uses its own CSRF-protected, session-bound cell confirmation. SQL replacement additionally uses server-reviewed row eligibility, explicit confirmation, and signed replacement records.

## C. Test suite improvements

| Added to the standard manifest | Coverage and isolation |
| --- | --- |
| `tests/edit-history.cjs` | Undo and redo transitions in memory. |
| `tests/formula-references.cjs` | In-memory reference tokenization, including quoted, nested, cross-sheet, and invalid expressions. |
| `tests/workbook-storage.php` | Incoming workbook handling, synchronized formulas and values, conflict preservation, and timestamped backups. The root workbook, June draft, and archived workbook are read as inputs. Writes stay in the temporary tree. |
| `tests/headers-browser.cjs` | Header pinning, zoom, scroll geometry, and overlay stacking on three annual sheets. Uses a copied workbook and drafts, private PHP sessions, and checks for zero POST requests. |
| `tests/base-report-compact.cjs` | Hidden rows, preserved formula metadata, readable numbers, desktop fit, sticky headers, mobile scrolling, and resize behavior on a disposable compact server. |
| `tests/base-report-browser.cjs` | Compact recalculation, manual per-cell confirmation, invalid CSRF rejection, draft persistence, stale revisions, and scrolling. Saves only to a disposable draft directory. |

All five existing compact SQL UI suites now use disposable loopback PHP fixtures under the runner, with private workbooks, drafts, sessions, and screenshot output. These fixtures allow settings inspection and review requests. Settings writes and recovery are exercised by the existing lifecycle fixture with its own schema and account. Annual interaction and Resync fixtures now also use private PHP session directories. The five compatibility aliases continue to resolve to two canonical suites. Manifest uniqueness and normalization of slash variants are asserted.

Historical browser scripts excluded and left unexecuted are `auto-refresh-browser.cjs`, `browser.cjs`, `editable-focus.cjs`, `formula-browser.cjs`, `sync-workbook-browser.cjs`, `workbook-storage-browser.cjs`, and `zoom-browser.cjs`, all under `tests/`. They carry historical routes, password flows, or fixture assumptions outside this verification. The old standalone export checkers are retained as helpers. The standard annual suite calls `tests/check-annual-export.php`, and the SQL lifecycle suite calls `tests/query-apply-fixture.php`.

## D. Files modified and removed

| Action | Exact inventory |
| --- | --- |
| Updated documentation | `README.md` |
| Updated runner | `tools/run-tests.cjs` |
| Updated tests | `tests/base-report-browser.cjs`, `tests/base-report-compact.cjs`, `tests/brookes-point-browser.cjs`, `tests/cell-editing-browser.cjs`, `tests/headers-browser.cjs`, `tests/query-calculation-browser.cjs`, `tests/reporting-allowlist-browser.cjs`, `tests/sql-settings-style.cjs`, `tests/sql-settings-ui.cjs`, `tests/test-runner.cjs`, `tests/undo-resync-browser.cjs` |
| Created | `docs/genstat-final-cleanup.md` |
| Removed | None |

The historical README section and the earlier cleanup reports were preserved. No Git checkpoint, commit, or push was created.

## E. Security verification

| Area | Verified behavior |
| --- | --- |
| Reporting SQL | The parser enforces SELECT-only queries, the exact seven-table allowlist, bound reporting month, permitted grammar and functions, and rejection of comments, hints, and multiple statements. The configured reader has only global USAGE and seven table-level SELECT grants. It can read all seven tables and is denied UPDATE. Integration tests verified the 500-row preview cap and three-second MariaDB timeout setting. Output column, field, and response size limits were reviewed in the executor. |
| Formula protection | The annual and compact suites exercise explicit cell confirmation and server-side protection. The SQL lifecycle exercises approved row 18 replacement, the separate formula confirmation, persistence of signed provenance, rejection of changes outside authorized targets, and explicit Restore Template Formula. |
| Apply & Save and recovery | Tests cover Test Query, Review, expired or stale proposals, required confirmations, configuration persistence, History, Restore and Disable, receipt failure rollback, duplicate operations, lost responses, recovery in the initiating session, and rejection across sessions. Invalid CSRF requests are rejected by both compact endpoints. |
| Workbook operations | Tests cover independent monthly drafts, formula calculations, Save Draft, export preservation, workbook synchronization, Undo/Redo, and version-bound Resync confirmation. Conflict and recovery cases check preservation of active copies, drafts, and backups. |

## F. Regression results

| Verification | Result |
| --- | --- |
| PHP syntax | 65 files passed, zero failures |
| JavaScript syntax | 35 files passed, zero failures |
| Unit group | 20 of 20 passed |
| Browser group | 11 of 11 passed |
| Final supported total | 31 of 31 passed, zero failures or skips in the manifest |
| Manifest and aliases | 31 distinct canonical suites. All five alias names combined with their targets resolve to two executions. |
| Reporting access probe | SELECT passed for all seven reporting tables. Both saved queries parsed and each returned 15 rows for July 2026. |

The initial combined run passed 24 of 31 suites. All seven failures were in the new compact fixture hosting path. PHP interpreted the Windows short-path tilde in an unquoted session setting and truncated the directory. Session warnings broke CSRF-dependent requests and changed the compact viewport height. A targeted diagnostic run reproduced the failure. The runner and annual fixtures now set the session path relative to their own working directory. All original layout and authorization assertions were retained.

## G. Data integrity

SHA-256 comparisons covered 367 pre-existing project files outside Git internals and installed dependencies. The 354 files outside the modification inventory matched byte for byte. The inventory accounted for 13 edits, one new report, and zero removals. Matching files included the active and reference workbooks, incoming and archived files, drafts, locks, backups, recovery markers, source configuration, database credential files, and signing key.

Before and after snapshots matched the row counts and content hashes of all 12 production tables, including the seven reporting tables, the four settings/history/receipt tables, and `sales_sync_status`. Reader, settings writer, and configured administrative connection grant fingerprints matched. The temporary lifecycle schemas and accounts were absent on both snapshots. The tests used a disposable verification copy, private PHP session directories, and isolated settings databases for state changes.

The dependency junction was detached. Automatic tool review blocked deletion of the outer verification copy, including a narrower attempt to remove its copied database configuration and signing key. The copy remains in the current Windows user's temporary directory under `genstat-final-2381b3854f064553a3e15e45318367a6`.

## H. Remaining known limitations

Legacy CLI use outside the available repository and local evidence remains unresolved. The workbook export checks verify the XLSX package and preservation of unrelated parts. Interactive recalculation in desktop Excel was outside this verification. The historical browser scripts listed in section C were skipped.

## I. Final project status

**STABLE WITH LIMITATIONS.** The supported regression coverage passed and the integrity audit matched production data and grants. The remaining limits are the unresolved external use of the compatibility CLI and the verification boundaries listed above.

## J. Development readiness

GENSTAT is ready for feature development. Batch C2 Phase 2 and the cleanup initiative are closed.
