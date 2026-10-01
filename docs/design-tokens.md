# LayRate Design Tokens

Single source of truth: `resources/css/app.css` `@theme` block (Tailwind v4
generates utilities like `bg-success`, `text-danger`, `border-warning` from
these). Brand/status triples also documented in `DESIGN-SYSTEM.md` §2.
Rule: **one meaning = one color everywhere.** Never hardcode a hex that a
token already covers.

## Brand

| Token | Value | Use |
|---|---|---|
| `secondary` | `#213183` | Deep navy: confirm-modal neutral, step icons |
| `sidebar-bg` | `#1a2342` | Deepest navy: sidebar, banners, login/landing navy |
| `navy` | `#002d5e` | Primary buttons, tabs, headings on light |
| `primary` / `primary-active` | `#0075de` / `#005bab` | Primary actions, links, focus rings, active states |
| `cage-b-soft` / info tint | `#dcebfa` | Icon tiles, selected states, info backgrounds |

## Semantic status (solid / tint / border — all WCAG AA with text shade)

| Meaning | Solid | Tint (bg) | Border | Text | Use |
|---|---|---|---|---|---|
| success / ok | `#1f6b3a` | `#e8f5ec` | `#cfe8d6` | `#1f6b3a` | Healthy, positive trends, saved |
| warning / watch | `#8a5a00` | `#fdf3e0` | `#f3e3bf` | `#8a5a00` | Near threshold, needs attention |
| danger / alert | `#9b1c24` | `#fbe4e6` | `#f3cdd0` | `#9b1c24` | Errors, critical, destructive, negative trends |
| info | `#1d4e8f` | `#dcebfa` | `#b8d4fe` | `#1d4e8f` | Neutral hints, empty states |
| neutral | `#615d59` | `#f0f0f0` | `#e6e6e6` | `#615d59` | Inactive, unknown, no data |

Components that consume these: `x-status-badge` (bucket resolver),
`x-notification-toast` (`STYLES` map), `.trend-pill-up/down`, modal
`.modal-icon--*` / `.modal-btn--*`, `x-button` danger/warning variants,
flash messages, `x-input-error`, notes client validation.

## Surfaces & text (light; app has no dark mode)

Page `#F0F0F0` · card `#ffffff` · border `#e6e6e6` (#D9D9D9 inputs) ·
heading `#1f1f1f` · body `#333333`/`#31302e` · muted `#615d59` · faint `#a39e98`.

## Chart series palette (fixed order, colorblind-safe)

`--color-chart-1 #0075de` → eggs / production ·
`--color-chart-2 #009e73` → HDEP ·
`--color-chart-3 #e69f00` → temperature ·
`--color-chart-4 #56b4e9` → humidity ·
`--color-chart-5 #8a6bbf` → feed ·
`--color-chart-6 #d55e00` → mortality.
A metric must keep its color on every chart/legend; never rely on color
alone — pair with labels/icons. Applied via `public/js/chart-colors.js`
(CSS-var backed, hardcoded fallbacks) in: dashboard temp/humidity/eggs/
feed/HDEP/mortality charts, heat-stress severity bands (success/warning/
danger), production-history forecast overlay, reports eggs/HDEP/feed/temp/
humidity/deaths/stock charts, and all chart tooltips.

## Validation messages

Server: `lang/en/validation.php` (friendly tone, field names via
`attributes`). Client: `public/js/form-validation.js` auto-attaches to every
form (delegated capture-phase submit, Turbo-aware, `data-native-validation`
opt-out, `data-error-message` override, stale errors purged on
`turbo:before-cache`) with inline errors matching `x-input-error`. Also
included on the standalone login page. Native controls restyled in CSS
(select chevron, date inputs, hidden number spinners, brand `accent-color`);
steppers via `x-number-input`. Native `title=""` bubbles auto-upgraded by
`public/js/tooltip.js` (hover + focus, Esc, viewport-aware).

## Quick-actions dock

- Bottom-right row: checklist button (left) + "+" page-actions button
  (right). Both 48px navy circles (`--color-navy`), badge in danger token.
- Idle transparency: `--dock-idle-opacity: 0.4` (65% when tasks pending,
  badge counter-scaled to stay readable); full opacity on hover, focus,
  touch, open panels, scrolling fades to 25%.
- "+" menu items: white pills with one icon tint (`bg-info-bg` +
  `text-navy`); pages feed them via `@stack('dock-actions')`.
- Checklist panel: white system card (16px radius, hairline border, soft
  shadow), progress bar (primary fill), rows with success/warning status
  tokens; bottom sheet on mobile.

## Type scale (classes in `resources/css/app.css`)

| Class | Size / weight | Color | Use |
|---|---|---|---|
| `.page-title` | 22–24px, bold | white | Banner title (`x-page-header`) |
| `.page-subtitle` | 13px | white 78% | Banner subtitle |
| `.section-label` | 12px, uppercase, 0.08em | muted + navy icon, hairline divider extends right | Section starts (`x-section-label`): PRODUCTION, FEED & NUTRITION… |
| `.card-title` | 15px, semibold | navy | Card titles (`x-card-header`, `x-card`), TITLE CASE |
| `.card-subtitle` | 13px, one line | muted | What is shown + period from active filters; omit if none |
| `.kpi-label` | 11px, uppercase | `#5b6472` | KPI labels (unchanged treatment) |
| `.kpi-number` / `.kpi-value` | 26–28px, bold | dark slate | KPI values (unchanged) |

Rules: only section + KPI labels are uppercase; card titles are Title Case
navy, never gray. One 36px brand-blue `.card-icon-tile` per card
(`--color-info-bg` + navy icon); colors elsewhere reserved for status
meaning only. Card headers share `min-height: 56px` so neighbors align
with or without a subtitle.

## Cursor rules (single source: `app.css` "Global cursor rules")

Tailwind v4 shows the default arrow on `<button>`; the pointer is restored
globally — do not scatter `cursor-pointer`.

| Element | Cursor |
|---|---|
| `button:not(:disabled)`, `[role="button"]`, `a[href]`, `summary`, `label[for]`, `select`, checkboxes/radios, submit/button/file inputs, date/month/time inputs (+ picker indicators), `[role="tab"]`, `[role="menuitem"]`, `[role="option"]`, `[onclick]`/`[data-action]`/`[data-confirm]`/`[data-toggle]` (not on forms), `[data-nav]`, `[data-row-nav]` | pointer |
| `:disabled`, `[aria-disabled="true"]`, `.disabled` links/buttons, disabled controls + their labels | not-allowed (never `pointer-events: none` — it hides the cursor) |
| text/search/number/password/email/tel/url inputs, `textarea`, `[contenteditable]` | text |
| plain text, headings, cards, non-interactive surfaces | default arrow |
| `button.is-loading`, `form.is-submitting button[type=submit]` | wait |
| `body.is-loading` (background loading) | progress |
| cage tiles / drag handles (grab utilities), `html.cage-dragging` while dragging | grab / grabbing |
| `.hint-icon` (hover-only tooltip, no click — e.g. HDEP explainer) | help |
| Chart.js legend labels (toggle datasets; set in `__applyChartDefaults`) | pointer on hover |
| modal/overlay backdrops (all close on click here) | pointer via `[onclick]` |
| textarea resize handles | native resize |
| icon-only buttons (`x-icon-button`: cage add/flip/edit/renumber/print/delete, table actions) | pointer (global `<button>` rule) + shared tooltip |

Deliberate exceptions kept: `cursor-grab` on draggable cage tiles/handles,
`cursor-not-allowed` on non-button disabled states (slot cards, calendar
days, dropzone prompts). No `aria-disabled` usage exists yet — when
introduced, pair it with a click guard (no `pointer-events: none`).

## Recommended next steps (skipped: need visuals or broad logic)

- Test-only baseline farm helper (`tests/Concerns/SeedsBaselineFarm.php`)
  covers cages/slots/hens/batches; suites needing exact demo sums
  (FeedBatch month sums), pool-limit validation behavior, and UI-drift
  assertions (cage-performance counts, relay card, threshold badges) still
  fail from pre-existing causes — see test report.
- Navy evidence: `var(--color-*)` kept as literals (`#002d5e`) inside canvas
  color paths and JS alpha-concatenation (canvas cannot resolve CSS vars):
  `analytics/_charts`, `forecast/_results`, production-history gradient
  fallbacks. Deliberate — do not "collapse" these.
- Per-category bars (per cage/breed/cause) keep local categorical ramps —
  they encode categories (labels distinguish), not metrics.
- Hardware API-key panel, forecast skipped-rows, feed estimate/Direct pills,
  FCR legend dots: mapped to warning/success tokens.
- Remaining amber/gray pockets with distinct local semantics (forecast
  skipped table kept warning; feed hardware key panel warning).
