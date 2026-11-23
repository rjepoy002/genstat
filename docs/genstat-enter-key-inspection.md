# GENSTAT Enter-key inspection

## 1. Executive summary

The reported symptom matches a confirmed gap in the **older compact report**, present in commit `28f8285`. In-cell text changed in the browser, but the compact grid only copied it into the working report on native `change`. Enter handling existed for the formula editor and formula bar. Clicking away produced the missing `change` event. The gap applied to the shared in-cell editor, including Row 39 and Disconnected Consumers. Evidence: `28f8285:assets/base-report.js:241`, `:259–265`, and `:299–307`.

The current checkout contains two newer versions of the keyboard implementation. Local `HEAD` (`b074a3c`) already handles Enter directly for eligible in-cell inputs. The working tree additionally routes both worksheet views and their editors through a shared helper. A fresh failure in the user's running browser is **unconfirmed**. No browser session, live database, draft, formula, or configuration was changed during this inspection. Evidence: `b074a3c:assets/base-report.js:265–302`; [compact grid handler](../assets/base-report.js#L265); [shared helper](../assets/worksheet-keys.js#L2).

## 2. Confirmed root cause

In the older compact code, there was a missing key-to-commit connection for the inline input. The table's `change` listener called `setManualValue` and `recalculate`. The separate table `keydown` handler required the event target to be the formula cell itself. A key from `<input data-ref="B39">` bubbled to the table but could not pass that guard. The other Enter listener was attached specifically to `#formula`, outside the grid. This is a source-confirmed historic cause. Evidence: `28f8285:assets/base-report.js:241`, `:252–265`, and `:299–307`. The same split is visible in `e0a14b8:assets/base-report.js:211–212`, `:222–235`, and `:270–278`.

The current code closes that gap. In `HEAD`, the table checks the input’s eligibility, calls `preventDefault()` for Enter, commits, and then optionally focuses a candidate. The working-tree version delegates the key to `handleWorksheetEditKey`, which likewise calls `commit()` before moving focus. Neither version makes commit conditional on finding a destination. Evidence: `b074a3c:assets/base-report.js:265–302`; [current delegation](../assets/base-report.js#L274), [commit before navigation](../assets/worksheet-keys.js#L18).

## 3. Affected cell types

The historic compact-report gap applied to eligible text inputs rendered inside the grid. It was independent of row number, the blue fill, and whether a numeric workbook value already existed. The renderer uses one input branch for eligible cells. The formula bar and protected editors follow separate paths. Evidence: [compact layout editability and metadata](../lib/base-report.php#L34), [inline HTML renderer](../base-report.php#L218), `28f8285:assets/base-report.js:241` and `:259–265`.

- **Manual and database-eligible inputs.** Both use the same inline editor. The `database_input` classification marks eligibility. SQL provenance is determined separately from the working values, signed SQL sources, preview values, replacements, and the controlled total. Evidence: [classification](../lib/base-report.php#L43), [SQL source indicators](../assets/base-report.js#L164).
- **Unlocked original formulas and custom formula overrides.** The compact editor opens the formula bar for eligible formulas and uses the validated `edit-formula` endpoint, with audit and undo. It does not render an ordinary input for an original formula. Existing overrides are tracked separately in `data.formulas`. Evidence: [formula editor and commit](../assets/base-report.js#L305), [formula endpoint](../base-report.php#L65), [audit append](../base-report.php#L89).
- **SQL and manual overrides.** A normal value cell can still be editable under the existing permissions even when a SQL preview or previously SQL-sourced value is displayed. A manual commit writes a value overlay and removes its SQL source marker. SQL-controlled totals, signed SQL formula replacements, and Recovered Sales-owned cells are blocked by `canEditCell`. Evidence: [value precedence](../assets/base-report.js#L57), [manual override](../assets/base-report.js#L184), [permissions](../assets/base-report.js#L214).
- **Annual worksheet view.** The default route uses the compact report. A `sheet` or `legacy` route loads a different renderer and policy. At `HEAD`, annual inline inputs update local draft state on `input` and schedule autosave; their Enter handler moves through the flat input list. That is a separate flow, so the older compact missing-`change` diagnosis does not establish an annual-editor failure. The working tree changes annual inline inputs to a commit buffer and shares directional navigation. Evidence: [route selection](../index.php#L3), [annual edit policy](../lib/workbook-policy.php#L69), `b074a3c:assets/app.js:84–88`, [current annual handlers](../assets/app.js#L82).

## 4. Row 39 event flow

I read the XLSX layout with the existing PHP reader, without invoking the HTTP report or database. The compact report loads `excel-files/reference/GENSTAT_BASE.xlsx` with `Jul26` as the template. On that sheet:

| Reference | Workbook evidence and compact editor classification |
|---|---|
| `A39` | `TX/SS Losses In KWH-Grid`. Static label, rendered as the row selection control. |
| `B39:P39` | Editable, non-formula database inputs. Example `B39` is Main and has a source value of `988655.69`. `C39` is Aborlan and is a valid editable input with a blank source. |
| `Q39` | Protected area-total formula `=SUM(B39:P39)+0.01`. The extra `0.01` is already in the July template. |
| `R39` | Protected formula `=+R28`. |
| `S39` | Editable manual historical input. Its total-group column can be visually collapsed. |

The template-derived area and input permissions come from [layout and row classification](../lib/base-report.php#L23) and [report area definitions](../lib/report-areas.php#L8). [Row visibility and rendering](../base-report.php#L207) control which elements can be focused. The reported area and reporting month were unspecified. `Jul26!B39` is the concrete example covered by the existing browser test. The separate annual workbook also contains Row 39 in `Jun26` and `Jul26`. Its June `Q39` formula is `=SUM(B39:P39)`, so the July offset should not be assumed for other workbook sheets. Workbook evidence: `excel-files/reference/GENSTAT_BASE.xlsx → Jul26!A39:S39` and `excel-files/active/genstat_2026.xlsx → Jun26!A39:Q39, Jul26!A39:Q39`. Reader: [build_layout](../tools/build-layout.php#L13).

The input and formatted value are separate elements. While the cell has focus, the text input is visible and its display span is hidden. The initial input uses a controlled total, a draft override, or the template value; other periods suppress July numeric defaults. The browser’s working value can also come from an authorized SQL preview. A typed number visible inside the focused input is therefore evidence of a DOM edit, before any calculation response. Evidence: [input and display span](../base-report.php#L220), [focus CSS](../assets/base-report.css#L76), [working value resolution](../assets/base-report.js#L59).

Current compact flow for an eligible `B39` edit:

1. Focusing the input selects the cell and updates the formula bar. Typing changes the input buffer. The grid does not copy each keystroke into `data.values`. [Selection and focus](../assets/base-report.js#L227), [commit and key listeners](../assets/base-report.js#L268)
2. Enter calls the same `commitGridInput` function as native `change`. The eligibility and unchanged-value checks run first. The commit records the new working value, clears prior SQL provenance for that cell, marks the working report dirty, and queues `calculate`. [Commit and deduplication](../assets/base-report.js#L265), [manual state update](../assets/base-report.js#L184), [calculation queue](../assets/base-report.js#L250)
3. The PHP request validates session, source hash, revision, input permissions, and overlays. The existing engine applies the input and renders the worksheet with the existing number formats. July dependencies include `B28 = +B39`, `Q39 = SUM(B39:P39)+0.01`, and formulas downstream such as `B43 = +B39+B30` and `Q43 = Q30+Q39`. The browser updates display spans from the latest applicable response. [Request boundary](../base-report.php#L10), [validation](../base-report.php#L40), [calculation engine and formatting](../lib/base-report.php#L63), [response rendering](../assets/base-report.js#L195)
4. `Save Draft` separately queues the save and writes the period-scoped draft under a revision check and file lock. Enter commits to the working report. It does not itself persist the draft. [Client save queue](../assets/base-report.js#L390), [draft scope](../lib/base-report-view.php#L12), [locked atomic write](../base-report.php#L162)

The prior compact browser test treats Main `B39` as a bottom-boundary case for its fixture. It enters `10`, presses Enter, waits for report work, then asserts that focus is still on `B39` and the working state contains `10`. A successful commit can therefore appear stationary at this location. [B39 regression](../tests/base-report-browser.cjs#L58)

## 5. Comparison with working cells

| Case | Evidence and interpretation |
|---|---|
| Disconnected Consumers, e.g. `B24` | Same database-input renderer and commit path. The regression verifies Enter moves to `B25`, updates `Q24`, and sends just one calculation request. [Test](../tests/base-report-browser.cjs#L20) |
| Standard manually editable inputs, e.g. `S39` | Same text-input path when visible. Row classification affects permissions and provenance. It does not select a Row 39-specific key handler. [Classification](../lib/base-report.php#L42), [shared grid listener](../assets/base-report.js#L290) |
| Unlocked compact formulas | The grid opens a separate bar. Enter in that bar already called the formula commit path in the historic version, explaining why a bar edit could work while an inline value edit did not. The working tree shares the navigation helper and retains server authorization/audit. `28f8285:assets/base-report.js:253–265,299–307`; [working-tree bar buffer](../assets/base-report.js#L342) |
| SQL-configured or manually overridden input | A SQL indicator alone does not prove a cell is locked. The guard checks managed totals, signed replacements, Recovered Sales ownership, and input editability. A rejected protected edit is expected behavior and distinct from a missing event. [SQL indicators](../assets/base-report.js#L164), [edit guard](../assets/base-report.js#L214), [protected test](../tests/base-report-browser.cjs#L63) |
| Annual worksheet | The working tree binds native `change` and the shared keyboard helper to annual inputs and has a separate formula bar authorized per cell. Its calculation preview is read-only. Audit and Save Draft run on the existing persistence path. [Annual commit and keyboard](../assets/app.js#L82), [formula bar](../assets/app.js#L210), [annual endpoint](../api.php#L34) |

## 6. Relevant files and line numbers

| Area | Source |
|---|---|
| Worksheet selection and layout | [index.php:3](../index.php#L3), [lib/base-report.php:23](../lib/base-report.php#L23), [base-report.php:207](../base-report.php#L207) |
| Input creation and original data | [base-report.php:218](../base-report.php#L218), [base-report.php:257](../base-report.php#L257), [index.php:81](../index.php#L81) |
| Compact events and state | [assets/base-report.js:184](../assets/base-report.js#L184), [assets/base-report.js:246](../assets/base-report.js#L246), [assets/base-report.js:265](../assets/base-report.js#L265), [assets/base-report.js:342](../assets/base-report.js#L342) |
| Shared key guards and navigation | [assets/worksheet-keys.js:2](../assets/worksheet-keys.js#L2) |
| Calculation, authorization, persistence | [base-report.php:40](../base-report.php#L40), [base-report.php:65](../base-report.php#L65), [lib/base-report.php:54](../lib/base-report.php#L54), [base-report.php:153](../base-report.php#L153), [api.php:34](../api.php#L34), [lib/app.php:62](../lib/app.php#L62) |
| Historic compact gap | `28f8285:assets/base-report.js:241,259–265,299–307` |
| Direct grid fix already in local HEAD | `b074a3c:assets/base-report.js:265–302` |
| Prior browser cases | [tests/base-report-browser.cjs:20](../tests/base-report-browser.cjs#L20), [tests/base-report-browser.cjs:58](../tests/base-report-browser.cjs#L58), [tests/base-report-browser.cjs:69](../tests/base-report-browser.cjs#L69), [tests/base-report-browser.cjs:98](../tests/base-report-browser.cjs#L98) |

## 7. Why clicking another cell works

For a changed text input, leaving focus normally emits native `change`. In the affected compact revision, that event was the first point where the code read the edited value and wrote it into the manual overlay. There was no direct grid `blur` commit listener. Focus leaving the cell also lets its calculated display span show again. In current compact code the `change` listener is still present and calls the same function as Enter; equality against the previously committed working value avoids a second mutation. Evidence: `28f8285:assets/base-report.js:241`; [display CSS](../assets/base-report.css#L76); [current comparison and listeners](../assets/base-report.js#L268).

The current annual view has both events. `change` commits and `blur` refreshes its formatted preview. The formula bar commits its own pending edit on blur where authorized. Evidence: [annual input handlers](../assets/app.js#L112), [annual formula bar](../assets/app.js#L220), [compact formula bar](../assets/base-report.js#L342).

## 8. Why Enter does not work in the reported case

**Historical case confirmed by source.** Enter from an inline input had no applicable commit handler. The formula-cell key listener ignored that target and the formula-bar listener was on a different element. No focus move was commanded for input Enter, so the edit could remain only in the DOM and no `calculate` request would be queued until click-away. The grid input is not part of a submit form. I found no relevant worksheet `keyup` commit or evidence of form submission causing the historic symptom. Evidence: `28f8285:assets/base-report.js:241,259–265,299–307`; [table input markup](../base-report.php#L207).

**Current checkout.** The working grid delegates eligible input Enter to a shared helper that ignores previously canceled events, IME composition, and Ctrl/Alt/Meta combinations. Normal Enter is canceled with `preventDefault()`, committed, and optionally moves focus. Shift changes direction. No worksheet-wide Enter interceptor was identified in the inspected code. The current compact document key handler is for Ctrl/Meta+Z, and its formula-cell handler requires the cell itself as target. Evidence: [grid delegation](../assets/base-report.js#L290), [formula key guard](../assets/base-report.js#L313), [document shortcut](../assets/base-report.js#L509), [shared key handler](../assets/worksheet-keys.js#L2).

**Hypotheses for a continuing symptom, in diagnostic order:**

1. The affected browser may be serving older compact JavaScript or a different deployment. Confirm the exact URL/route, reporting month, cell, loaded script response, and Git revision before attributing it to Row 39. The former deployed asset has not been identified.
2. If the working-tree templates and editor JavaScript are served but `assets/worksheet-keys.js` is missing or stale, the Enter call can fail before commit while native `change` can still reach `commitGridInput`. This follows from the script dependency and separate `change` listener. No missing script or browser error was observed in this inspection. [Script order](../base-report.php#L257), [delegation and independent change listener](../assets/base-report.js#L274).
3. At the last eligible row, focus remains on the same input by design. Check the working snapshot and `calculate` request rather than relying on focus movement. If the request fires and returns an error, investigate the validation or revision response as a downstream issue. [Boundary behavior](../assets/worksheet-keys.js#L18), [calculation error handling](../assets/base-report.js#L250).

**Runtime evidence limits.** I reviewed an earlier isolated compact browser run (`genstat-test-results-YoOrWW`), which passed the keyboard test containing `B39` and related regressions. I also reviewed the prior isolated annual editor run (`genstat-test-results-ca33Bm`). I did not rerun either suite, interact with the live application, reproduce the historical commit in a browser, or verify which asset the reporting user received. The historical mechanism and local workbook classification are confirmed by source and workbook inspection. The current live failure remains unverified.

## 9. Recommended global fix

First reproduce the reported case on the affected URL and capture the loaded asset versions, active element, Enter and change events, and any calculation request/response. If the browser runs the older version, review and deploy matching templates and scripts together through the normal release process. Treat cache invalidation as a deployment check after the asset mismatch is established. The current `HEAD` already implements direct in-cell Enter commit, and the working tree already contains broader shared navigation. Evidence: `b074a3c:assets/base-report.js:265–302`; [working-tree script dependency](../base-report.php#L257).

If the fault reproduces on matching current assets, fix the demonstrated guard or wiring issue in the shared editor. Retain the single commit path for Enter and native change, an explicit boundary behavior, calculation sequencing, and separate draft persistence. Preserve SQL and formula permissions. No further implementation is proposed for approval until the failing runtime is identified.

## 10. Risks and dependencies

- Mixed template and JavaScript versions can leave the keyboard dispatcher without its helper. The compact and annual templates reference the helper separately. [Compact scripts](../base-report.php#L257), [annual scripts](../index.php#L31)
- The same worksheet coordinate can have different workbook formulas or eligibility across views and months. The selected period, source hash, revision, and actual editor must be part of any reproduction. [Compact period request](../base-report.php#L10), [annual edit policy](../lib/workbook-policy.php#L69), [annual request](../assets/app.js#L76)
- Adding a second commit listener or forcing blur can duplicate writes or move focus at a boundary. Preserve the equality guard and existing queue/revision protection. [Deduplication](../assets/base-report.js#L268), [draft queue](../assets/base-report.js#L390), [server locking](../base-report.php#L162)
- A visible formatted value can differ from the raw input. Workbook display formats, formula protection, SQL provenance, and the existing `+0.01` in July `Q39` are independent of the keyboard event. [Classification](../lib/base-report.php#L43), [display formatting](../lib/render.php#L86), `GENSTAT_BASE.xlsx → Jul26!Q39`.

## 11. Suggested regression tests

1. In an isolated fixture, type a different number into July `B39` and blank-source `C39`. Press Enter and confirm the working state updates, the correct dependent values recalculate, and a boundary retains focus when applicable. Compare with click-away and assert one calculation per commit. Extend the existing [B39 test](../tests/base-report-browser.cjs#L58) and [request-count test](../tests/base-report-browser.cjs#L20).
2. Compare `B24`, another ordinary manual cell, an unlocked original formula, an existing custom override, and SQL-protected targets. Cover Shift+Enter, Tab, Shift+Tab, Escape, zero, negative decimals, clearing a cell, and skipping hidden or ineligible cells. [Existing compact cases](../tests/base-report-browser.cjs#L31)
3. Test rapid edits during calculation and a draft save, then reload to distinguish the saved draft from newer pending edits. Check revisions and protect formula audit/undo. [Draft-race regression](../tests/base-report-browser.cjs#L98)
4. Verify both routes and multiple reporting periods. Confirm the annual formula preview works without writing the draft before Save Draft and that modal keyboard behavior is unaffected. [Annual edit tests](../tests/cell-editing-browser.cjs#L52), [annual calculation endpoint](../api.php#L34)
5. Reproduce with the exact deployed assets that the user received. Record the script response, console errors, request count, active target, and whether the working value changed. Repeat against matching current assets in a disposable fixture to distinguish a deployment mismatch from an editor defect.
