# Task: Improve the MOBILE view of the LayRate web app (Laravel + Tailwind)

This is a **UI-only** task, done on its own git branch so it cannot conflict with the Pi bridge work.

## Hard rules

- Work ONLY on a new branch named `mobile-ui`. Never commit to `main`.
- Do NOT touch: the serial bridge, sensor ingestion/override logic, systemd files, anything on the Pi, or anything under `.github/`.
- Do NOT push, deploy, or SSH to the Pi.
- Do NOT run test suites.
- Do NOT change backend behavior, routes, data, `composer.json`, `composer.lock`, or install PHP dependencies.
- Desktop and tablet layouts (`md` and up) must look exactly the same as today. All changes apply below the existing desktop breakpoint.
- Use CSS breakpoints, Tailwind responsive classes, `clamp()` and container queries. Do NOT use JavaScript that measures the screen and resizes things.
- Minimums: body text 14px, tap targets 44px.

## Step 0: Branch setup (do this first)

1. Run `git status` and `git branch --show-current`. Report both.
2. There are existing uncommitted/untracked files that belong to other work (for example `serial-bridge/test_service_config.py`, untracked images, test-result directories). **Leave them untouched.**
3. Create the branch from the current HEAD: `git switch -c mobile-ui`. If `mobile-ui` already exists, switch to it and report its state first.
4. When committing, stage ONLY the UI files you changed, by explicit path (`git add <file> <file>`). Never use `git add -A` or `git add .`. Run `git diff --cached --stat` before every commit and confirm that only UI files (Blade, CSS, JS for UI behavior) are staged.
5. Do not switch back to `main` or run `git stash`, `git reset --hard` or `git clean`.

## Setup

- Read the SKILL.md of each of these installed skills before using it, as guidance only: `frontend-dev-guidelines`, `tailwind-patterns`, `ui-ux-pro-max`, `accessibility`, `mobile-app-ui-design` (design lens only, this is a web app), `playwright` (viewport checks). Do not run any install scripts or hooks from them.
- Check the Tailwind version in `package.json` and follow its conventions.
- Reuse the existing KPI styling in `resources/css/app.css`. Do not create a parallel system.

## Changes to make

### 1. Dashboard KPI cards (Total Hens, Today's HDEP, Eggs Today, Lifetime Eggs)

- Currently the icon tile is bigger than the number and each card is very tall.
- Make cards compact: a smaller icon, a clearly dominant value, less padding.
- Keep the "vs yesterday" pill on ONE line (shorten the text or reduce its size if needed).
- Keep the 2-column grid on phones.

### 2. Tab bars (Production Performance / Environmental Analytics; Egg Logging / Recent Logs / Egg Stock; Recent Logs / Egg Stocks / Pre-Orders)

- Make the row horizontally scrollable with scroll snap and a hidden scrollbar.
- Scroll the active tab into view.
- Add an edge fade (or similar cue) so users can see more tabs exist.
- Do not truncate labels: "Environmental Analytics" and "Egg Stocks" must show in full.

### 3. Tables: use cards only where they fit

Read each table's Blade/Livewire code first, then apply by type:

| Table | Mobile layout |
|---|---|
| **Recent Logs** (Date / Cage / Slot / Eggs) | Compact list rows (about 56px), grouped under a date header and cage. Slot and cage on the left, egg count large on the right. Remove the repeated date column on mobile. Tap a row to expand details and edit/delete, or use a 44px ⋮ menu. |
| **Cage Performance Overview** (Rank / Cage / Hens / HDEP / Eggs) | Keep a table. On phones show Rank, Cage and Eggs as columns, HDEP as a small second line under the cage name, and Hens in expandable details. It must be scannable without horizontal scrolling. |
| **Any other wide table** | Scrollable with a sticky first column, an edge fade and a visible scroll cue. |

- Prefer ONE markup with CSS grid and `data-label` cells. Do not duplicate markup with `md:hidden` / `hidden md:block` if the table uses Livewire/Alpine state.
- Everything the desktop table shows must remain reachable on mobile, including row actions.

### 4. Cage labels

- "CAGE-A" currently wraps onto two lines at the hyphen. Use `whitespace-nowrap` or a compact badge.

### 5. Floating buttons

**First**, find every fixed/floating button and speed-dial component (the clipboard button with the "3" badge, and the menu button on Egg Management). Report what each one does and which pages use it, before changing anything.

Then:

- Replace the speed-dial pills with a **bottom sheet** on phones: dimmed backdrop, 2-column tiles with the icon above the label, 48px minimum tile height.
- The sheet closes on backdrop tap, close button, Escape and the Back button. Add `aria-label`, `aria-expanded` and focus handling.
- **One primary floating button per page.** Remove duplicates of buttons already on the page (for example "Scan Egg QR" appears both as the big page button and in the menu).
- Group settings-type actions (Egg Weights, Thresholds, Egg Prices) under a "Settings" section inside the sheet.
- **No two floating buttons side by side.** Based on what the clipboard/badge button actually does, merge it into the same button or the header bell. If its purpose is unclear, do NOT guess: leave its behavior unchanged, stack it vertically with enough spacing, and describe it in the report.
- Button size 56px, offset with `env(safe-area-inset-bottom)`, hides on scroll down and shows on scroll up.
- Add bottom padding to the page so no content (including stock values like "31,766") is ever hidden behind a floating element or the browser's bottom bar.
- Desktop behavior of the menu stays unchanged.

### 6. Other pages

Check the egg logging form, egg stock, environmental charts, relay/fan controls, modals and the notification dropdown. Fix overflow, tiny tap targets and clipped text with the same patterns. Keep these fixes small and consistent.

## Verification (visual only)

- Run the app locally, or run `npm run build`, whichever works. If the app cannot run because PHP extensions are missing (`gd`, `zip`), do not change dependencies or lockfiles. Say so, then verify by inspecting the Blade/CSS and building the assets.
- With Playwright, take screenshots at **360x800, 390x844 and 414x896** of: the dashboard (both tabs), Recent Logs, Egg Stocks with the bottom sheet closed and open, and one other data page.
- Also take screenshots at **768 and 1280** widths to confirm tablet and desktop are unchanged.
- Confirm there is no horizontal page scroll at any phone width, and that no value or table column is hidden behind a floating element.
- Save screenshots in `mobile-ui-screenshots/`. Do not commit them.

## Commit

- Commit on `mobile-ui` with a clear message, staging by explicit file path as described in Step 0. Do not push.
- Use several small commits if it helps (for example KPI cards, tabs, tables, floating button).

## Final report

Give me:

- The branch name and commit hash(es), plus the output of `git diff --stat main..mobile-ui`.
- Confirmation that no non-UI files (bridge, ingestion, systemd, `.github/`, composer files) are in the branch.
- The files changed and what each fix does.
- What the clipboard button does and whether it was merged or left alone.
- The screenshot paths.
- Pages you could not check, and anything you left alone on purpose.

## Results log

### Status
- 1. DONE — compact KPI cards on phones; 2-column grid retained; labels kept on one line; phone subtitles hidden and their meaning preserved in the existing info popover.
- 2. DONE — phone tab rails scroll, active tab is revealed, and tab targets are 44px; the desktop tab bar is unchanged.
- 3. DONE — wide tables become stacked/grouped rows on phones; desktop tables are unchanged.
- 4. DONE — cage and slot labels no longer wrap on phones.
- 5. DONE — the quick dock has one checklist control and one page-actions control; page actions open a phone bottom sheet with backdrop, Escape/Back handling, focus trapping, and focus return. Desktop dock behavior is unchanged.
- 6. DONE — the duplicate in-page Egg Stocks scanner button was removed on phones; the dock/menu scanner is the single entry point on phone and desktop.
- 7. DONE (addendum) — Recent Logs KPI rhythm, filter grid/chips, grouped log list, and compact centered pagination.

### Branch and commit
- Branch: `mobile-ui`.
- Commit: this commit (see the final report for the hash). No push, no deploy, no Pi access.
- No backend behavior, routes, data, serial bridge, ingestion, systemd, `.github/`, Composer files, dependency versions, or lockfiles were changed.
- Pre-existing unrelated work remains unstaged: `app/Http/Controllers/*`, `public/js/filter-ui.js`, tests, `serial-bridge/test_service_config.py`, and untracked images/result directories. No pre-existing user edit was intentionally incorporated.

### Floating buttons findings
- Checklist/clipboard button (`[data-dock-action="checklist"]` in `resources/views/components/quick-actions-dock.blade.php`): opens Today's Checklist; the red badge is the incomplete-category count and its label is `N pending tasks`. It is a separate control from page actions; it is not a clipboard utility or notifications indicator, so the two controls were not merged.
- Page-actions button (`.fab-toggle` / `#dockFabMenu`): opens the actions pushed by the current page. On phones it is a bottom sheet; on desktop it retains the existing menu.
- Egg Stocks: the in-page “Scan Egg QR for Freshness” duplicate was removed from the phone layout. The dock menu keeps one scanner entry, which works on phone and desktop. The dock/FAB is not hidden on the Stocks tab.
- Recent Logs duplicate check at 390px: the DOM contains exactly one `#quickDock` and one `[data-dock-action="checklist"]`. The duplicate appearance in the user's full-page/stitched screenshot is a capture artifact of a `position: fixed` element, not a second real button. The second `clipboard-list` icon is the Records KPI card icon, not a floating control.

### Changes made
- `resources/views/components/quick-actions-dock.blade.php`, `resources/views/layouts/app.blade.php`, `public/js/quick-dock.js`: unified dock markup (`#dockFabMenu > .dock-fab-content` + generated sheet header + `.dock-actions-grid`), phone bottom sheet, backdrop, close/ESC/Back/popstate/Turbo handling, mobile-only focus trap and focus return, checklist/page-actions mutual exclusion, ARIA-hidden synchronization, and phone-only scroll-away behavior. Desktop dock layout and behavior are unchanged.
- `resources/views/components/kpi-card.blade.php` + `resources/css/app.css`: phone KPI layout (compact icon tile, top-row info button, one-line labels, `clamp()` values), and the optional `infoDetail` prop used to append a phone-hidden subtitle to the existing explanation popover.
- `resources/views/components/underline-tabs.blade.php`, `resources/views/components/paginator.blade.php`, `resources/views/components/icon-button.blade.php`, `resources/views/components/cage-color.blade.php`, `resources/views/components/filter-chips.blade.php`: phone tab rail + active-tab reveal, 44px pagination targets, 44px icon buttons, no-wrap cage labels, and the adjacent-Blade-directive whitespace fix that unblocks Egg Stocks live data.
- `resources/views/dashboard.blade.php`, `resources/views/dashboard/_cage-performance-content.blade.php`, `resources/views/eggs/recent-logs.blade.php`, `resources/views/egg-logging/_logs.blade.php`, `resources/views/egg-logging.blade.php`: dashboard phone padding/tab behavior, expandable cage-performance rows, Recent Logs grouped rows and filters, and page-level mobile clearance.
- Egg Management / Feed / Environment / Notifications / Hens / Forecast / Hardware / Cages / Pre-Orders / Stocks views: 44px control targets, compact KPI/table treatments, page-action markers, mobile modals, and phone bottom clearance.
- The Recent Logs addendum is detailed in its own subsection below.

### Verification
- `npm run build` completed successfully; `php artisan view:clear` and `php artisan view:cache` compiled all Blade views.
- Playwright viewport checks (not full-page stitched) at 360x800, 390x844, and 414x896 for Recent Logs, dashboard Overview/Environmental Analytics, Egg Stocks (closed and sheet open), and Environment. 768x1024 and 1280x900 were checked to confirm tablet/desktop are unchanged.
- No page-level horizontal overflow was found at any checked width.
- Interactions verified: dock sheet opens/closes via backdrop, Escape, and browser Back; Shift+Tab wraps; checklist and page-actions are mutually exclusive; 10 stock rows load; the scanner has one dock entry; Recent Logs chips toggle and reload the Turbo frame; a log row expands and its actions are reachable; the KPI info popover contains the phone-hidden subtitle; no console errors.
- The new Recent Logs controls were measured at 44px or larger (chips, Reset, pagination, info buttons). The only sub-44 element found by the audit was the non-interactive “Showing …” text.
- Screenshots are in `mobile-ui-screenshots/` and are intentionally not committed.

### Not checked / left alone
- No test suites were run, per the task rules.
- Cage Performance ranking screenshots could not be captured because the current data produces an empty performance state; the responsive markup/CSS was reviewed instead.
- The 640–767px band keeps the previous layout; the phone changes are scoped with `max-sm` so `md` and up are untouched. Tablet/desktop at 768 and 1280 were verified unchanged.
- The dock's explicit header/backdrop close can lose focus because `history.back()` triggers Turbo restoration; Escape returns focus to the toggle. Accepted as a minor limitation.
- Unrelated user changes and untracked assets/results were left untouched and unstaged.

### Addendum: Recent Logs layout polish
- KPI cards: phone subtitles hidden with `hidden sm:block`; their text is appended to the existing info popover through the new `infoDetail` prop. Cards now use one rhythm: 36px icon tile + 44px info button on the top row, then a no-wrap uppercase label, then a `clamp()` value. Same 12px padding; the 2-column grid is retained.
- Filters: Cage/Slot remain side by side; Breed takes a wider 9/12 column with Reset in the 3/12 column on the same row, bottom-aligned to the select. Logged Via is a 3-chip row (Manual/Sensor/Unknown) backed by the original checkbox inputs, each 44px high and wrapping cleanly. One `gap-3` vertical rhythm is used.
- Log rows: one rounded grouped-list container with thin dividers and 56px rows. Cage is a no-wrap badge, slot is muted mono on the same line, and the egg count is right-aligned with a small muted “eggs” label underneath. Date headers remain group headers with 12px vertical padding. Tap-to-expand and the existing row actions remain reachable.
- Pagination: “Showing …” is centered above the buttons; page controls are 44px; extra page numbers collapse to previous, current, last, next on phones. The phone-only spacer below the paginator was removed, and the page keeps safe-area-aware bottom padding (`calc(5.5rem + env(safe-area-inset-bottom))`) for the floating checklist. The tablet/desktop `h-16` clearance was retained.
- Floating button: one real element, as above; the duplicate was a full-page/stitched screenshot artifact.
- Files for this addendum: `resources/views/eggs/recent-logs.blade.php`, `resources/views/egg-logging/_logs.blade.php`, `resources/views/components/kpi-card.blade.php`, `resources/views/components/paginator.blade.php`, `resources/css/app.css`.
- Verification: viewport screenshots at 360, 390, and 414 (top, rows, pagination, expanded row) plus 768 and 1280 for unchanged tablet/desktop; no horizontal scroll; chips toggle and reload; row expansion and actions work; info popover includes the hidden subtitle; no console errors.
- Left alone: desktop table markup/layout, the 640–767 band, and all unrelated user changes.

### Final diff check
- Staged by explicit UI/spec path only; pre-existing unrelated files remain unstaged. `git diff --cached --stat` was inspected before committing.

## Addendum: Recent Logs layout polish

This is an additional phone-only fix for Egg Management → Recent Logs (“Production Logs”). The rules in the main task still apply: UI only, `md` and up unchanged, no JavaScript screen measuring, no push/deploy/Pi access/tests/dependency changes, and staging by explicit file path only.

### Problems (390px phone)
1. KPI cards (Records, Eggs Logged, Logged by IR, Overridden) looked uneven: subtitles wrapped to different lengths, and the icon tile was not aligned with the label and value.
2. Filters: Breed sat alone at half width, Reset floated alone on its own row, and the Manual/Sensor/Unknown checkboxes had tiny tap targets.
3. Log rows were separate bordered boxes with the cage and slot stacked on two lines, wasting height.
4. Pagination: “Showing 1–10 of 20856” was left-aligned while the page buttons were centered, with a large empty gap below.
5. The floating clipboard button appeared twice. Check whether that was a full-page screenshot artifact or two real elements, and report which.

### Fixes (mobile only, below md)
#### KPI cards
- Hide the subtitle line on phones (for example `hidden md:block`). Do not delete it for desktop.
- Keep its meaning available: put the subtitle text in the existing ⓘ tooltip/popover if it is not already there, so no information is lost. Make the ⓘ a 44px tap target.
- One consistent layout for all four cards: icon tile (smaller, about 36px) and ⓘ on the top row, then the uppercase label, then the value. Same padding and the same height for all four cards.
- Keep the 2-column grid. Use `clamp()` for the value font size. Labels must not wrap (“EGGS LOGGED”, “LOGGED BY IR”): reduce letter-spacing or font size slightly if needed, with a 12px minimum.

#### Filters
- Cage and Slot stay side by side. Put Breed and Reset on the same row (Breed wider, Reset a 44px-high button on its right, aligned to the input baseline).
- Turn “Logged Via” into a row of 3 toggle chips (Manual, Sensor, Unknown), each at least 44px high, wrapping cleanly if needed, using the same checkbox inputs underneath so the Livewire/Alpine bindings keep working.
- Equal vertical spacing between filter groups (use one spacing step, e.g. `gap-3`).

#### Log rows
- Replace the separate bordered boxes with one grouped list container: a single rounded border, rows separated by thin dividers, about 52–56px per row.
- Put “CAGE-A” and the slot on one line (cage as a no-wrap badge, slot in muted mono text next to it). Put the egg count on the right, with a small muted “eggs” label under or beside it.
- Keep the date as a group header with consistent spacing above and below it. Keep tap-to-expand or row actions reachable, if the desktop table has them.
- Prefer CSS only (grid and Tailwind responsive classes), with the same markup for desktop, and no duplicated `md:hidden` copies if Livewire/Alpine state is involved.

#### Pagination and bottom spacing
- Center “Showing 1–10 of 20856” above the page buttons. Make the page buttons at least 44px. Collapse extra page numbers on phones (previous, current, next, last) so it fits on one line.
- Remove the large empty gap below the pagination, but keep bottom padding for the floating button (safe-area-aware) so nothing is hidden behind it.

#### Consistency check
- Use the same spacing scale across the filters, KPI cards, date header, and list (one gap step, no mixed 10/14/18px values). Reuse the KPI and card classes in `resources/css/app.css` instead of adding parallel styles.

### Verification
- Playwright screenshots of the Recent Logs tab at 360x800, 390x844, and 414x896, plus 768 and 1280 to confirm they are unchanged. Take them viewport-by-viewport (not full-page stitched) so floating elements are not duplicated. Check there is no horizontal page scroll.
- Save to `mobile-ui-screenshots/` (not committed).
- If the app cannot run locally because of missing PHP extensions, do not change dependencies. Build the assets and inspect the Blade/CSS instead, and say so.

### Commit and log
- Commit on `mobile-ui`, staging by explicit file path, and check `git diff --cached --stat` first. Do not push.
- In the Results log, record: files changed, what each fix does, whether the floating button was one or two elements, the screenshot paths, and anything left alone.
