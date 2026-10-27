# Synchronization investigation

- Source: genstat.xlsx only; no Composer manifest, database, yearly workbook registry, or additional source workbooks in this checkout.
- Master SHA-256 at investigation: 9f341f3d0cc46f52862a55665defbc92b4b24de9f2813a4b7c409b4a081c6e78.
- B7 is blank, style 8, unmerged, nonformula, and protected in both current source and baseline. It is not an approved input. The last cached source may differ.
- Exact false-positive: auto_template_compatible rejects any changed nonempty nonformula protected value while structural_signature differs. This does not establish field identity or movement.
- Auto refresh also inherits single-sheet and label-coordinate restrictions from refresh_snapshot, which was designed for manual migration, not reconciliation.
- Inputs: 17 comment-backed coordinates in config/inputs.php. Draft keys are A1 coordinates within the first report sheet, stored in storage/draft-2026-06.json. Existing sourceCells snapshots protect conflicting defaults and formula overlays.
- Renderer and formula payload assume the first report sheet; additional worksheet viewing needs explicit handling. Formula engine can resolve other actual sheets; export retains opaque external links including RecovBill.
- Other mandatory-review paths: input-file changes, sheet count/name changes, merges, removed cells, input ownership, unsupported changed formulas, draft/default conflicts, incomplete CLI transaction marker. CLI commands remain optional recovery tooling; automatic sync must not demand approval.
- Implementation approach: hash on every load/check, cached parsed layout when unchanged, dedicated sync lock, comment/context reconciliation, read-time draft projections with immutable backups, one sync status area and explicit retry. Source files and stored draft bytes will not be changed by synchronization.
