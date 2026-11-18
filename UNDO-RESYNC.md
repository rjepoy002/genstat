# Undo, Redo and Resync

## User behavior

Undo and Redo now cover ordinary cell values and formula edits. Use the icon buttons beside the formula editor, Ctrl+Z, or Ctrl+Y (Ctrl+Shift+Z also redoes). The current worksheet session retains at most 50 input changes. Autosave and Save Draft preserve that history; a page reload or explicit Open Report begins a new history session. A new edit clears the redo branch.

Undo/Redo saves the restored state back to the draft immediately, including removal of an override when restoring the original Excel value. Formula history requires the same cell-specific confirmation as ordinary formula editing. Cancel leaves the command available and changes no values. Formula confirmation is scoped to the workbook version, worksheet and cell, and restored expressions still pass existing validation. A save/version conflict restores the browser state, retains history and preserves the newer server draft.

**Sync Now** keeps its existing meaning: write approved saved/browser changes into Excel, with conflict checks, a verified backup and atomic replacement.

**Resync from Excel** and **Reload updated workbook** both show a confirmation dialog. Resync reads the current validated active workbook; it never writes draft data to Excel or restores an older backup over a newer workbook. The dialog shows the last successful workbook write-back timestamp when recorded, and explicitly warns about saved draft overrides, newer saved changes and unsaved browser changes that will be discarded from the selected worksheet.

Cancel creates no draft, cache, workbook, checkpoint or backup changes. Preview is read-only and does not invoke automatic upload promotion. Automatic browser refresh polling and autosave pause while the Resync dialog is open. Opening the dialog does not autosave a pending draft into Excel.

On confirmation, the server rechecks the workbook hash, configuration/layout identity and every saved draft fingerprint. It rejects expired, missing, consumed or stale confirmation tokens. New draft revisions or external Excel changes require a fresh confirmation. The selected saved draft and cache are backed up and verified, a verified copy of the active workbook is retained, and only the selected draft is reset with a new revision. Other worksheets' drafts remain unchanged. The browser then reloads the workbook. Existing backups are never deleted.

Workbook write-back now records its successful timestamp in private `storage/workbook-sync.json`. Existing draft `synchronizedAt` timestamps remain available as a fallback. Resync leaves this checkpoint unchanged; it does not claim that a new Excel write-back occurred. Synchronization rollback also checks for external modifications after publication and preserves any newer Excel version rather than overwriting it with a rollback.

## Files

Created:

- `assets/edit-history.js`: bounded session command history.
- `lib/workbook-resync.php`: read-only preview, version-bound confirmation and backed-up draft reset.
- `tests/edit-history.cjs`, `tests/undo-resync-browser.cjs` and their result logs.
- This report.

Modified:

- `assets/app.js`: ordinary/formula Undo/Redo, persistent history across saves, confirmation-aware restoration and Resync dialog flow.
- `assets/app.css`, `index.php`: compact icon history controls and Resync confirmation markup.
- `api.php`, `lib/formula-confirmation.php`: scoped recovery confirmations and protected Resync endpoints.
- `lib/app.php`: successful write-back checkpoint and protection against overwriting external edits during rollback.
- `tests/cell-editing-browser.cjs`: formula Undo now confirms before restoring/saving.
- `tests/sync-reconciliation.php`: browser fixture includes the new history module.
- README and the earlier editing report link to this update.

The active workbook modification and existing `before-sync-20261027-055239-fea371fadfcd.xlsx` backup present before this task were preserved; they are not implementation changes made by these tests.

## Automated verification

`node tests/edit-history.cjs` verifies the 50-edit limit, undo/redo ordering, redo invalidation, no-op handling and session reset.

`node tests/undo-resync-browser.cjs` uses an isolated temporary application, workbook and drafts. It verifies:

- Ctrl+Z/Ctrl+Y and icon Undo/Redo after autosave, with restored values persisted.
- Unsaved edits cannot reappear through a stale autosave timer; new edits invalidate redo.
- Formula history requires confirmation on Undo and Redo; Cancel preserves draft bytes and history.
- A concurrent draft revision is not overwritten.
- The Resync timestamp and newer-draft warning are displayed.
- Cancel changes no workbook, draft, cache, checkpoint or backup.
- Resync requires a server confirmation token; newer drafts or Excel versions invalidate it.
- Confirmed Resync leaves the active workbook byte-for-byte unchanged, resets only the selected draft and preserves the other worksheet draft.
- Verified workbook/draft backups are retained; consumed tokens cannot be replayed.
- Undo/Redo fits the mobile editing toolbar; no browser JavaScript errors occur.
- The production workbook and draft remain byte-for-byte unchanged by the tests.

The existing workbook-storage, automatic-refresh and structural-reconciliation suites also pass. Formula/cell-editing browser regressions and worksheet zoom are checked separately in their existing result logs.
