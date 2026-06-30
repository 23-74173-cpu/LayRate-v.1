# LayRate UI/UX Audit — Design & HCI Improvement Plan

> Audited: June 2026 — LayRate Poultry Farm Management System
> Stack: Laravel 12 · Tailwind CSS (CDN) · Vanilla JS · Lucide Icons (CDN) · Chart.js (CDN)
> Context: Raspberry Pi serving desktop + tablet/kiosk browsers

---

## PART 0 — EXISTING DESIGN TOKENS (Baseline)

The system already has a coherent core palette. Make this explicit and enforced via a shared config.

| Role        | Value                 | Used For                                     |
| ----------- | --------------------- | -------------------------------------------- |
| Brand Navy  | `#102A4C`             | Sidebar background, primary buttons, headers |
| Accent Blue | `#002D5E`             | Secondary actions, active nav, links         |
| Cream BG    | `#F5F5F0`             | Page background (body)                       |
| Card White  | `#FFFFFF`             | Card surfaces                                |
| Border      | `#D9D9D9`             | Card/input borders                           |
| Ink         | `#333333`             | Primary text                                 |
| Muted       | `#6B7280`             | Secondary text, labels, captions             |
| Cage-A      | `#2D7D46`             | Green — CAGE-A                               |
| Cage-B      | `#1D4E8F`             | Blue — CAGE-B                                |
| Cage-C      | `#C2703E`             | Orange — CAGE-C                              |
| Cage-D      | `#6B4C8A`             | Purple — CAGE-D                              |
| OK Green    | `#D5E8D4` / `#2D6A4F` | Normal sensor, in-range thresholds           |
| Watch Amber | `#FFF3CD` / `#856404` | Watch state                                  |
| Alert Red   | `#F8D7DA` / `#721C24` | Alert, danger, mortality                     |

**Typography:** `system-ui, ui-sans-serif, sans-serif` throughout. Heading scale: `text-xl` (page title) → `text-lg` (section) → `text-base` (subsection) → `text-sm` (card label) → `text-xs` / `text-[10px]` (captions). Consistent but informal — fine for a farm tool.

**Spacing rhythm:** `p-5` on `<main>`, `gap-4` / `gap-5` between cards, `p-4` / `p-5` inside cards, `space-y-4` in forms. Reasonably consistent but some screens have `gap-3`, others `gap-5` with no clear logic.

---

## PART 1 — LAYOUT ARCHITECTURE & INFORMATION DENSITY

### The Core Layout Problem

The current layout is a **fixed sidebar + fixed header + scrollable content area**. This is a standard admin panel pattern, but it creates two problems for a farm operator context:

1. **Sidebar wastes horizontal space on a 10" tablet** — at 13rem (~208px) wide, operators lose ~20% of screen width to navigation they use once per session
2. **The content area has no visual breathing room** — most pages use `p-5` with `space-y-5`, meaning everything stacks with generous gaps but no sense of grouping or priority

### Page-by-Page Layout Assessment

#### Dashboard (`dashboard.blade.php`)
**Current:** 4 metric cards (row 1) → Cage Overview cards (row 2) → [Feed | Mortality | Alerts] (row 3) → [Env Summary | Live Readings] (row 4)

**Problems:**
- **Information overload on first load** — 4 rows of cards + 2 charts, ~14 distinct data regions compete for attention
- **Visual weight is flat** — all cards are `border border-[#D9D9D9] p-4`. No card is visually "more important" than another
- **The header says nothing** — "Dashboard" is generic. An operator scanning a screen in poor lighting cannot quickly find what they need
- **Live Readings section** shows sensor data as a dense grid of tiny cards — on a tablet, this easily scrolls off-screen

**Information hierarchy principle:** The most time-sensitive action on the dashboard should be the topmost thing visible without scrolling. For a farm, that is: **"Are there any alerts?"** and **"Did we collect eggs today?"** — not a summary of all 60 slots.

**Proposed layout reorganization:**
```
┌─────────────────────────────────────────────────────┐
│ HEADER: "Tuesday, June 30 — Morning Check" [+ date] │
│  (breadcrumb: Dashboard)                             │
├─────────────────────────────────────────────────────┤
│ ROW 1: ALERT BANNER (if any) — red strip, full-width│
│        "3 alerts: CAGE-B temp above threshold"       │
├─────────────────────────────────────────────────────┤
│ ROW 2: PRIMARY METRICS (3 cards, horizontal)         │
│   [Eggs Today]  [Today's HDEP + delta]  [Mortality] │
│   (larger, bolder, scan-at-a-glance)                │
├─────────────────────────────────────────────────────┤
│ ROW 3: TWO-COLUMN                                    │
│   [Cage Overview — compact 2×2 grid] [Feed Today]    │
├─────────────────────────────────────────────────────┤
│ ROW 4: Live Readings — collapsed by default,          │
│         expandable "View Sensor Details →"            │
│ (moved below the fold — not primary information)     │
└─────────────────────────────────────────────────────┘
```

**Key changes:**
- Move Alerts to a **persistent banner** (not buried in a card at bottom right)
- Reduce metric cards from 4 to 3 by merging Eggs + Coop Environment (or dropping Coop to a secondary card)
- Cage Overview: use a **2×2 compact grid** instead of full-width cards — saves vertical space
- Live Readings: **collapsed by default** with a "Expand sensor details" toggle — operators only need this when diagnosing a problem
- Add a **time-of-day greeting** in the header ("Good morning — 6:00 AM"), which sets context without adding cognitive load

---

#### Egg Logging (`egg-logging.blade.php`)
**Current:** Cage filter → 4 summary cards → slot cards grid (grouped by cage) → Log Entry form → Recent Logs table (paginated)

**Problems:**
- **The log entry form is BELOW the fold** on most screens. An operator who has selected a slot must scroll down to see the form. On a tablet, this creates a two-handed workflow: thumb to scroll, finger to tap.
- **Summary cards and slot grid compete for attention** — 4 summary cards at top + all 60 slot cards fill the screen before the form
- **The cage dropdown groups are good** but the slot cards inside are visually identical to the cards in the cage management page — no differentiation between "logging a slot" and "viewing a slot's status"

**Proposed layout reorganization:**
```
┌─────────────────────────────────────────────────────┐
│ Cage Filter (compact, top-left)   [Today: 142 eggs ▲] │
├──────────────────────┬────────────────────────────────┤
│                      │                                │
│  SLOT GRID (left)    │  LOG ENTRY FORM (right, sticky) │
│  Collapsed by cage    │  Appears when slot selected    │
│  ~55% width          │  ~45% width                    │
│                      │                                │
│  [CAGE-A ▼]          │  Slot: A-2-3 · 4 hens         │
│    [slot][slot][...]  │  Egg count: [____]             │
│  [CAGE-B ▼]          │  [Save Record]                 │
│    [slot][slot][...]  │                                │
│                      │                                │
├──────────────────────┴────────────────────────────────┤
│ RECENT LOGS TABLE (paginated)                         │
│ [collapsible — operators rarely need to scroll back]  │
└─────────────────────────────────────────────────────┘
```

**Key changes:**
- **Log Entry form becomes sticky-right panel** — always visible after a slot is selected, no scroll needed
- **Slot grid moves to left column** — scrollable within its column, form stays fixed
- **Summary metric (today's total) moves to header area** — not its own card, just a number in the top bar
- **Recent Logs is collapsed by default** with "Show recent logs ↓" — operators rarely need this, it's for verification

---

#### Cage Management (`cages/index.blade.php`)
**Current:** Page header + Add Cage button → 2-column grid of cage cards → each card has: meta, slot grid, actions

**Problems:**
- **Cage cards are too tall** — a 3×5 slot grid + metadata + action buttons in a single card makes the card ~500px tall. Operators must scroll to compare cages.
- **Slot grid is purely visual** — there is no quick way to "see all eggs today" or "see mortality" from this view
- **Modals (Add/Edit) are extremely complex** — the Battery Cage Configuration modal has live preview, real-time math, and safety validation. On a first-time setup, this is fine. On daily use (editing a location), the complexity is noise.

**Proposed layout reorganization:**
```
┌─────────────────────────────────────────────────────┐
│ [Add Cage] button                [4 cages · 60 slots]│
├─────────────────────────────────────────────────────┤
│ TAB BAR: [All] [CAGE-A] [CAGE-B] [CAGE-C] [CAGE-D]  │
├─────────────────────────────────────────────────────┤
│                                                      │
│  ┌─────────────┐  ┌─────────────┐                   │
│  │ CAGE-A      │  │ CAGE-B      │                   │
│  │ 3×5 grid    │  │ 3×5 grid    │   ← 2-column     │
│  │ (small)     │  │ (small)     │      layout      │
│  └─────────────┘  └─────────────┘                   │
│                                                      │
│  ┌─────────────┐  ┌─────────────┐                   │
│  │ CAGE-C      │  │ CAGE-D      │                   │
│  └─────────────┘  └─────────────┘                   │
│                                                      │
│  [View Details →] expands inline instead of new page  │
└─────────────────────────────────────────────────────┘
```

**Key changes:**
- **Slot grids become mini-grids (~5×5 icons)** — not full card grids. Clicking a cage opens an **inline detail panel** (not a separate page, not a modal). Shows hens, recent eggs, mortality — all in context.
- **Tab bar for cage filtering** — operators who only care about CAGE-A don't want to visually parse 4 cards
- **Add Cage complexity stays in modal** — but Edit becomes an **inline edit panel** (like the slot expand in the current cages/index), not a modal

---

#### Feed & Nutrition (`feed.blade.php`)
**Current:** 3 metric cards → tab bar (Batches | Consumption) → tab panels

**Problems:**
- **Tabs use a toggle function with inline JS** — no visual indication of which tab is active beyond color change
- **"Add Feed Batch" button is top-right** but the form opens in a modal in the center — the flow is: look top-right, see modal in center. Spatial disconnect.

**Proposed:** Keep current layout but make the active tab visually distinct (underline + bold weight, not just bg-color change). Consider moving "Add Batch" inside the tab panel header rather than globally.

---

### Sidebar Navigation Assessment

**Current:** 13rem wide, collapsible to 3.5rem (icon-only). Items: Dashboard, Cages, Chickens, Egg Logging, Environment, Feed, Analytics, Forecast, Reports, Settings, Profile.

**Problems:**
- **13rem is wide for a tablet** — on an iPad in landscape, that's ~20% of the 1024px width lost to nav
- **Icon-only collapsed state hides labels** — for Cages vs Chickens vs Eggs, icons alone are ambiguous (`feather` for cages, `bird` for chickens, `egg` for eggs — two birds look similar)
- **"Profile" is in the nav** but there's no profile page — it links to `#`
- **No active-state label** — when collapsed, operators see only icons. On tablet held in portrait, the sidebar may be hidden entirely (collapsed by default on small screens?)

**Proposed:**
```
NORMAL (expanded):        COLLAPSED (icon-only):
┌──────────────┐          ┌────┐
│ 🐦 LayRate   │          │ 🐦 │
│              │          ├────┤
│ 🏠 Dashboard │          │ 🏠 │
│ 📦 Cages     │          │ 📦 │
│ 🐔 Chickens  │          │ 🐔 │
│ 🥚 Egg Log   │          │ 🥚 │
│ 🌡 Environ.  │          │ 🌡 │
│ 🍃 Feed      │          │ 🍃 │
│ 📊 Analytics │          │ 📊 │
│ 📈 Forecast  │          │ 📈 │
│ 📋 Reports   │          │ 📋 │
│              │          ├────┤
│ ──────────── │          │ ⚙️ │
│ ⚙️ Settings  │          │ 👤 │
│ 👤 Profile   │          └────┘
└──────────────┘

On tablet portrait: sidebar auto-collapses
On desktop: sidebar starts expanded
```

**Key changes:**
- **Collapse to 4rem (not 3.5rem)** — 3.5rem is too tight for icons to breathe; 4rem allows an icon + 1 character
- **Add tooltip on hover** when collapsed: `<span class="absolute left-full ml-2 bg-[#102A4C] text-white text-xs px-2 py-1 rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none">Egg Logging</span>`
- **Remove "Profile" nav item** — replace with a user avatar + name at the bottom of the sidebar (shown when expanded, avatar when collapsed)
- **Add section dividers** — separate primary nav (daily ops) from secondary nav (analytics/reports) from system (settings)

---

## PART 2 — VISUAL CONSISTENCY

### HIGH: Cage color matching duplicated in 8+ files

**Files:** `egg-logging.blade.php`, `mortality.blade.php`, `dashboard.blade.php`, `cages/index.blade.php`, `feed.blade.php`, `forecast.blade.php`, `chickens/index.blade.php`, `environment.blade.php`

Every file reinvokes the same `match()` expression. When cage A's color changes, all 8 files must be updated manually — and someone will miss one.

**Fix:** Add to `app/Models/Cage.php`:
```php
public function getColorAttribute(): string
{
    return match($this->cage_code) {
        'CAGE-A' => '#2D7D46',
        'CAGE-B' => '#1D4E8F',
        'CAGE-C' => '#C2703E',
        'CAGE-D' => '#6B4C8A',
        default  => '#6B7280',
    };
}
```
Replace every `match($cageCode...)` and `style="color:{{ $cage->color }}..."` with `$cage->color`. **Single source of truth, zero duplication.**

---

### HIGH: Status color sets duplicated in 5+ files

**Files:** `mortality.blade.php`, `dashboard.blade.php`, `environment.blade.php`, `cages/index.blade.php`, `egg-logging.blade.php`

Three separate hardcoded arrays: mortality reasons, HDEP thresholds, sensor statuses — all using identical green/amber/red hex pairs but with different key names.

**Fix:** Create `app/Helpers/StatusHelper.php`:
```php
class StatusHelper {
    static function hdepColors(float $hdep): array  // green/amber/red + text color
    static function sensorColors(string $status): array  // green/amber/red
    static function reasonColors(string $reason): array  // for mortality reasons
    static function reasonLabel(string $reason): string   // human-readable label
}
```
Replace all inline arrays with `StatusHelper::reasonColors($log->reason)['bg']`, etc.

---

### MEDIUM: Pagination HTML copy-pasted in 4 views

**Files:** `egg-logging.blade.php`, `mortality.blade.php`, `chickens/index.blade.php`, `feed.blade.php`

**Fix:** Extract to `resources/views/partials/paginator.blade.php`:
```blade
{{-- @param \Illuminate\Pagination\LengthAwarePaginator $paginator --}}
@if($paginator->hasPages())
<div class="px-4 py-3 border-t border-[#F0F0F0] flex items-center justify-between text-xs text-[#6B7280]">
    <span>Showing {{ $paginator->firstItem() }}-{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>
    <div class="flex items-center gap-1">
        @if($paginator->onFirstPage())
        <span class="px-2 py-1 text-[#9CA3AF]">‹ Prev</span>
        @else
        <a href="{{ $paginator->previousPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">‹ Prev</a>
        @endif
        @foreach($paginator->getUrlRange(1, $paginator->lastPage()) as $page => $url)
            @if($page == $paginator->currentPage())
            <span class="px-2 py-1 font-medium text-[#002D5E]">{{ $page }}</span>
            @elseif($page >= $paginator->currentPage() - 1 && $page <= $paginator->currentPage() + 1)
            <a href="{{ $url }}" class="px-2 py-1 hover:text-[#002D5E]">{{ $page }}</a>
            @endif
        @endforeach
        @if($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}" class="px-2 py-1 hover:text-[#002D5E]">Next ›</a>
        @else
        <span class="px-2 py-1 text-[#9CA3AF]">Next ›</span>
        @endif
    </div>
</div>
@endif
```
Then: `@include('partials.paginator', ['paginator' => $logs])` in each view.

---

## PART 3 — HCI PRINCIPLES

### HIGH: Slot-card click targets are icon-only and inaccessible

**File:** `egg-logging.blade.php` — slot cards have `onclick="selectSlot(this)"` with no keyboard support, no `aria-label`, and no visible text. Screen reader users cannot discover slots. Keyboard users cannot tab to them.

**Fix:**
```blade
<div class="slot-card ..."
     role="button"
     tabindex="0"
     aria-label="Select {{ $cage->cage_code }} slot {{ $slot->row_number }}-{{ $slot->column_number }}, {{ $slot->current_occupancy }} hens"
     onclick="selectSlot(this)"
     onkeydown="if(event.key==='Enter'||event.key===' ') selectSlot(this)">
```
Add to layout CSS: `.slot-card:focus { outline: 2px solid #002D5E; outline-offset: 2px; }`.

---

### HIGH: No visible focus indicators on any interactive element

**Files:** All views. Default browser outlines are suppressed inconsistently.

**Fix — add to `layouts/app.blade.php` `<style>` block:**
```css
:focus-visible {
    outline: 2px solid #002D5E;
    outline-offset: 2px;
    border-radius: 4px;
}
```

---

### HIGH: Modals lack ARIA roles, keyboard dismiss, and backdrop handling

**Files:** `cages/index.blade.php`, `egg-logging.blade.php`, `feed.blade.php`, `chickens/partials/move-modal.blade.php`, `chickens/partials/remove-modal.blade.php`

**Fix — add to layout JS:**
```js
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.fixed:not(.hidden)').forEach(modal => {
            if (modal.getAttribute('role') === 'dialog')) {
                modal.classList.add('hidden');
            }
        });
    }
});
```
And add `role="dialog" aria-modal="true"` to each modal root. Add `onclick="this.classList.add('hidden')"` on each backdrop div.

---

### HIGH: Form validation — error on input vs. error on border

**Files:** All forms. Invalid inputs get a red message below but no red border on the input itself. Users must hunt for which field failed.

**Fix:** Create `resources/views/partials/input-error.blade.php`:
```blade
@error($name)
<div class="flex items-center gap-1 mt-1">
    <p class="text-[11px] text-red-500">{{ $message }}</p>
</div>
@enderror
```
And apply `class="{{ $errors->has($name) ? 'border-red-400 ring-1 ring-red-200' : '' }}"` to each input. The red border + message together make errors scannable in one glance.

---

### HIGH: Operators doing repetitive egg logging need larger touch targets

**File:** `egg-logging.blade.php` — log entry form inputs at `py-2.5` on a tablet with gloved hands or a kiosk are borderline. The Save button is `py-2.5 text-sm`.

**Fix:** Bump to `py-3 text-base` for both inputs and buttons. Minimum touch target: 44×44px. On a kiosk/tablet used by operators in a barn, this is not optional — it's a usability requirement.

---

### HIGH: Dashboard "Eggs Collected" — misleading subtitle

**File:** `dashboard.blade.php:29`

```blade
<div class="text-xs text-[#6B7280] mt-1">auto-counted via IR sensor</div>
```

The Arduino outputs serial data that is never consumed by PHP. This label creates false trust in manual data. Until the pipeline is implemented, change to `"manual entry · logged by operator"`.

---

### MEDIUM: `confirm()` dialogs are jarring

**Files:** `cages/index.blade.php`, `mortality.blade.php`, `chickens/index.blade.php`

`onsubmit="return confirm('Delete?')"` produces a native browser dialog. Replace with a themed confirm partial — consistent look, no browser chrome.

**Fix:** Create `resources/views/partials/confirm-modal.blade.php` (see full implementation in Part 6 below).

---

### MEDIUM: No loading/saving feedback on form submissions

**Files:** Egg logging, mortality, feed consumption — no spinner, no button disable, no feedback during page reload.

**Fix — add to each form:**
```js
form.addEventListener('submit', function() {
    const btn = form.querySelector('[type=submit]');
    btn.disabled = true;
    btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> Saving…';
    lucide.createIcons();
});
```

---

### MEDIUM: No tooltips for operators on first-time actions

**Files:** Egg logging override PIN, feed CP% badges, forecast horizon picker.

**Fix:** Add `title` attributes or info icons with explanatory popovers:
```blade
<span class="relative group inline-flex items-center gap-1">
    <i data-lucide="help-circle" class="w-3.5 h-3.5 text-[#9CA3AF]"></i>
    <span class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 hidden group-hover:block bg-[#102A4C] text-white text-xs px-2 py-1 rounded whitespace-nowrap pointer-events-none">
        PIN required to override sensor lock
    </span>
</span>
```

---

### LOW: No keyboard shortcut for power users

**File:** `layouts/app.blade.php`

Operators doing daily egg logging would benefit from `Ctrl+E` to jump to Egg Logging from anywhere.

**Fix:**
```js
document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
        e.preventDefault();
        window.location.href = '{{ route('egg-logging') }}';
    }
});
```

---

## PART 4 — USER-TYPE CONSIDERATIONS

| User                                          | Priorities                                   | Current Pain Points                                          | Fix                                                          |
| --------------------------------------------- | -------------------------------------------- | ------------------------------------------------------------ | ------------------------------------------------------------ |
| **Operator** (daily egg logging)              | Speed, large touch targets, minimal clicks   | Small inputs, must scroll to form after slot selection, no keyboard shortcuts | Larger touch targets, sticky form panel, Ctrl+E shortcut     |
| **Operator** (mortality logging)              | Quick entry, clear cage selection            | 4-section form, must select cage before entering count       | Pre-select cage from URL param (`?cage_id=X`), collapse form to 3 fields |
| **Admin** (cage setup)                        | Safe destructive actions, clear consequences | Delete confirmation is good; resize safety is fragile (session flash) | Move resize error inline into modal, auto-re-open modal via JS |
| **Admin** (thresholds)                        | Understanding impact of changes              | No explanation of what "Watch" vs "Alert" threshold means    | Add threshold explanation text + visual range indicator      |
| **Occasional** (farm owner reviewing reports) | Quick scanability, printable reports         | Reports page is dense, CSV is only export option             | Add a "Print View" button that reformats for A4/Letter       |

---

## PART 5 — SPECIFIC AREAS

### HIGH: Cage slot-grid — modal complexity and information density

**File:** `cages/index.blade.php`

**Problems:**
1. Add modal and Edit modal share ~90% of markup — two nearly identical modals
2. Layout Preview updates on every `oninput` — causes layout thrashing on slower Pis
3. Resize safety error reappears via session flash — clever but fragile (user navigates away, error is gone)
4. Each cage card is ~500px tall — comparing cages requires scrolling

**Fixes:**
- Merge add/edit into one `CageModal` — add a hidden `_method` field (POST for add, PUT for edit), populate form via JS based on mode
- Debounce layout preview: `oninput="clearTimeout(this._debounce); this._debounce=setTimeout(updatePreview, 200)"`
- Move resize error to inline alert inside the modal (not session flash): render the error server-side when `session('edit_cage_id')` is set, and call `openEditModal(...)` automatically on page load via a small JS block
- Make cage slot grids **miniaturized** (5×5 icon grid) as the default view, expandable inline to the full detail panel

---

### HIGH: Dashboard — inconsistent card anatomy and buried alerts

**File:** `dashboard.blade.php`

Four metric cards with different internal structures: one has a delta, one doesn't, one has sub-readings, one has a status badge. All four compete equally for visual weight.

**Proposed card anatomy (unified):**
```
┌──────────────────────────────┐
│ LABEL (tracking-wider, 10px) │
│ PRIMARY VALUE (3xl, tight)   │
│ [STATUS BADGE]  (optional)   │
│ DELTA or FOOTNOTE (xs)      │
└──────────────────────────────┘
```
Every card: label → value → optional badge → optional delta. Apply uniformly. Remove "auto-counted via IR sensor" (change to "manual entry").

Move alerts from a card at bottom-right to a **full-width dismissible banner at the top of the content area**, directly below the header. This is where operators will look first when something is wrong.

---

### MEDIUM: Chart.js — color accessibility and legend styling

**Files:** `analytics.blade.php`, `forecast.blade.php`

CAGE-D color `#6B4C8A` may fail WCAG AA for users with color vision deficiencies. Chart legends use Chart.js defaults (small, mismatched font).

**Fix — use colorblind-safe palette:**
- CAGE-A: `#1B8A3E` (green)
- CAGE-B: `#2563EB` (blue)
- CAGE-C: `#EA580C` (orange)
- CAGE-D: `#7C3AED` (violet)

Configure Chart.js global options in the layout or in each view:
```js
Chart.defaults.color = '#333333';
Chart.defaults.font.family = 'system-ui, sans-serif';
Chart.defaults.plugins.legend.labels.font.size = 12;
```

---

### MEDIUM: Feed CP% badges are unexplained

**File:** `feed.blade.php`

CP% badges use `$batch->cpColor` and `$batch->cpText` accessors — no legend. Operators see red/amber/green badges with no label explaining what the color means.

**Fix:** Add above the batches table:
```blade
<div class="flex items-center gap-4 text-[10px] text-[#6B7280] mb-2">
    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded" style="background:#2D6A4F"></span> Optimal (16–18%)</span>
    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded" style="background:#856404"></span> Watch (&lt;16% or &gt;18%)</span>
    <span class="flex items-center gap-1"><span class="w-2 h-2 rounded" style="background:#721C24"></span> Critical</span>
</div>
```

---

### LOW: Login page — no styled error state

**File:** `auth/login.blade.php`

Failed logins redirect back with `$errors->has('email')` but no visible error banner. User may not understand why they were sent back.

**Fix:** Add above the form:
```blade
@if($errors->any())
<div class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg text-sm text-red-700 flex items-center gap-2">
    <i data-lucide="alert-circle" class="w-4 h-4 shrink-0"></i>
    {{ $errors->first() }}
</div>
@endif
```

---

## PART 6 — RECOMMENDED REUSABLE COMPONENTS

| Component                                          | File                                                | What it replaces                              |
| -------------------------------------------------- | --------------------------------------------------- | --------------------------------------------- |
| `<x-cage-color :cage="$cage" />`                   | `resources/views/components/cage-color.blade.php`   | All 8+ `match($cage->cage_code)` blocks       |
| `<x-status-badge status="Normal" type="sensor" />` | `resources/views/components/status-badge.blade.php` | All inline status color arrays                |
| `<x-paginator :paginator="$logs" />`               | `resources/views/partials/paginator.blade.php`      | 4 manual paginator copies                     |
| `<x-input-error name="email" />`                   | `resources/views/partials/input-error.blade.php`    | `text-[11px] text-red-500` scattered in forms |
| `<x-confirm-modal />`                              | `resources/views/partials/confirm-modal.blade.php`  | All `onsubmit="return confirm()"`             |
| `<x-slot-card :slot="$slot" :cage="$cage" />`      | `resources/views/components/slot-card.blade.php`    | Slot card markup in egg-logging + cages       |
| `<x-alert-banner :alerts="$alerts" />`             | `resources/views/components/alert-banner.blade.php` | Alert rendering in sidebar, dashboard         |

---

## PART 7 — QUICK WINS (Low Effort, High Impact)

| #    | Change                                                       | Files                                          | Impact                                |
| ---- | ------------------------------------------------------------ | ---------------------------------------------- | ------------------------------------- |
| 1    | Add `$cage->color` accessor to `Cage` model                  | `app/Models/Cage.php`                          | Eliminates 8 duplicate match() blocks |
| 2    | Change "auto-counted via IR sensor" to "manual entry"        | `dashboard.blade.php:29`                       | Prevents false trust in data          |
| 3    | Add `:focus-visible` CSS to layout                           | `layouts/app.blade.php`                        | Keyboard accessibility everywhere     |
| 4    | Add `role="button" tabindex="0"` + keydown handler to slot cards | `egg-logging.blade.php`                        | Screen reader + keyboard support      |
| 5    | Apply red border class to invalid form inputs                | All form inputs                                | Immediate error localization          |
| 6    | Add CP% legend above feed batches table                      | `feed.blade.php`                               | Reduces operator confusion            |
| 7    | Add login error banner                                       | `auth/login.blade.php`                         | Reduces support load                  |
| 8    | Replace `confirm()` with themed confirm partial              | `cages/index.blade.php`, `mortality.blade.php` | Branded, consistent UX                |
| 9    | Extract paginator to partial                                 | 4 blade files                                  | Single-source pagination              |
| 10   | Add `aria-label` to icon-only Lucide buttons                 | All views                                      | Screen reader usability               |
| 11   | Add tooltip on collapsed sidebar nav items                   | `layouts/app.blade.php`                        | Collapsed nav becomes usable          |
| 12   | Collapse Live Readings on dashboard by default               | `dashboard.blade.php`                          | Reduces information density           |
| 13   | Pre-select cage in mortality log via `?cage_id=` URL         | `MortalityController`                          | One fewer click for daily logging     |

---

## PART 8 — LONGER-TERM REFACTORS

| #    | Item                                                         | Why                                                          |
| ---- | ------------------------------------------------------------ | ------------------------------------------------------------ |
| 1    | Implement sticky Log Entry form in Egg Logging (2-column layout) | Operators select slot → form appears without scroll; faster daily workflow |
| 2    | Replace cage detail view with inline expandable panel        | Operators compare cages without page navigation; less context switching |
| 3    | Merge Add/Edit cage into single dynamic modal                | ~200 lines of duplicated markup eliminated                   |
| 4    | Add a toast notification system (replaces all flash messages) | Allows auto-dismiss, undo actions, stacked notifications     |
| 5    | Implement real-time sensor data pipeline (Arduino → PHP → DB) | Justifies "auto-counted" and "live readings" claims; changes the dashboard's value proposition entirely |
| 6    | Systematic accessibility audit (WCAG 2.1 AA)                 | Color contrast on cage-D purple, touch target sizes on all interactive elements, screen reader labels on all icon buttons |
| 7    | Responsive audit for tablet portrait (768px)                 | Sidebar, slot grids, and metric cards all degrade differently at narrow widths — needs a unified responsive strategy |
| 8    | Add "Print View" for Reports                                 | Farm owner reviewing reports wants A4-formatted printout, not a web table |

---

## PART 9 — INFORMATION OVERLOAD PRINCIPLES (Summary)

The system's biggest layout risk is **showing everything at once**. Every page currently tries to show all data all the time. The fix is a consistent **progressive disclosure** pattern:

1. **Default view = what matters today** — alerts (if any), eggs logged, cage health
2. **"Show more" = historical context** — recent logs, charts, full inventory
3. **Drill-down = specific detail** — clicking a cage shows that cage's full data

This applies consistently across:
- **Dashboard**: Collapse Live Readings by default
- **Egg Logging**: Recent Logs collapsed by default
- **Cage Management**: Mini-grid default, expand for detail
- **Mortality**: Today's summary prominent, full log collapsed
- **Analytics**: Summary cards + 1 chart default, full charts on tab/accordion

The sidebar nav should follow the same principle: **show the 3-4 most-used sections (Dashboard, Cages, Egg Logging, Mortality) prominently**, with Analytics/Reports/Forecast in a secondary section that can collapse.
