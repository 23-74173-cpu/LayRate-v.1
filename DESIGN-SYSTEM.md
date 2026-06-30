# LayRate Design System

> **Status:** Phase 0 deliverable — authoritative visual reference for all later redesign phases.
> **Sources:**
> - `DESIGN-notion.md` — Notion's extracted design language (warm minimalism, paper-soft canvas, single structural blue, decorative sticker palette, hairline elevation).
> - `UI-UX-AUDIT.md` Part 0 — LayRate's existing baseline tokens (Brand Navy, Accent Blue, Cream BG, status/cage colors).
> - `resources/views/layouts/app.blade.php` — current Tailwind CDN config + sidebar chrome.
>
> **Scope of this document:** define how Notion's tokens map onto LayRate's functional requirements. No Blade views are modified in this phase.

---

## 1. Design Philosophy

LayRate is a **farm operations tool** used by operators in barn lighting on tablets/kiosks, and by admins at a desk. Notion's "warm minimalism" — a paper-soft canvas, near-black Inter type, one confident blue, hairline elevation — translates well, with three deliberate adaptations:

1. **Warm paper canvas replaces clinical cream.** LayRate's `#F5F5F0` cream → Notion's `#f6f5f4` warm paper. Same calm intent, slightly warmer.
2. **A persistent dark sidebar is a functional necessity**, not a marketing hero. Notion reserves its deep indigo "night" band for a single hero moment; LayRate requires an always-visible nav surface. We adopt a deep navy for the sidebar as a **documented, pragmatic exception** to Notion's guardrail (see §10).
3. **Functional status colors are required.** Notion carries status decoratively via its sticker palette and has no semantic ramp. LayRate needs OK/Watch/Alert and per-cage identity. We derive a **muted/pastel semantic ramp** from Notion's sticker hues, applied as soft surface fills (never as bright structural CTAs), preserving Notion's discipline that color decoration never paints structure.

The guiding principle: **the chrome stays quiet (greys, hairlines, one blue), and color carries meaning only where an operator must read status at a glance.**

---

## 2. Color System

### 2.1 Surface & Text (adopted from Notion)

These replace LayRate's `cream #F5F5F0`, `Card White #FFFFFF`, `Border #D9D9D9`, `Ink #333333`, `Muted #6B7280`.

| Token | Hex | Replaces (LayRate) | Use |
|---|---|---|---|
| `canvas-soft` | `#f6f5f4` | Cream BG `#F5F5F0` | Page background (body). The warm paper canvas. |
| `surface` | `#ffffff` | Card White `#FFFFFF` | Cards, panels, form fields, nav bar. Pure white on warm canvas creates gentle figure/ground. |
| `hairline` | `#e6e6e6` | Border `#D9D9D9` | 1px card borders, dividers, table row borders. Slightly softer than the old border. |
| `ink` | `#1f1f1f` | Ink `#333333` | Primary headings & body. Notion's near-black (rendered ~95% alpha for soft true-black); darker than LayRate's old ink for tighter contrast. |
| `ink-secondary` | `#31302e` | — | Secondary body copy. Warm charcoal. |
| `ink-muted` | `#615d59` | Muted `#6B7280` | Supporting/muted copy, labels, captions. Warmer than the old cool grey. |
| `ink-faint` | `#a39e98` | — | Captions, metadata, placeholder text. Ash. |

> **Note:** LayRate's old `muted #6B7280` was a cool grey; Notion's `ink-muted #615d59` is warm. The warm shift is intentional and consistent with the paper canvas.

### 2.2 Structural Accent (adopted from Notion)

Notion's discipline: **exactly one structural accent**, reserved for the primary action, inline links, and the active/focus signal. LayRate adopts this directly.

| Token | Hex | Replaces (LayRate) | Use |
|---|---|---|---|
| `primary` | `#0075de` | Accent Blue `#002D5E` (for links/active) + primary-button navy | Primary CTA fill, inline links, active nav indicator, focus ring. **The only color that paints an action.** |
| `primary-active` | `#005bab` | — | Pressed state of primary CTA. |
| `secondary` | `#213183` | Brand Navy `#102A4C` (for the dark hero/sidebar surface) | Deep indigo. Used for the dark sidebar surface and any full-bleed inverted band. |

> **Reconciliation decision:** LayRate's old `Brand Navy #102A4C` did double duty — sidebar background *and* primary button fill. We split these: primary buttons adopt Notion's `#0075de` blue (brighter, more obviously "the action"), while the sidebar adopts a deep navy derived from Notion's `secondary #213183` line (see §2.3). This removes the old ambiguity where a navy button on a cream page read as "another piece of the header."

### 2.3 Dark Surface — Sidebar (documented exception)

The sidebar is a persistent dark surface. Notion's guardrail reserves the deep indigo "night" treatment for a single hero moment. A persistent nav surface is a functional requirement for an admin tool, not a marketing flourish, so we adopt a deep navy as a **pragmatic, documented exception**.

| Token | Hex | Use |
|---|---|---|
| `sidebar-bg` | `#1a2342` | Sidebar background. A deep navy blended toward Notion's `secondary #213183` but slightly darkened from LayRate's old `#102A4C` for warmth. |
| `sidebar-active` | `rgba(255,255,255,0.12)` | Active nav row indicator (translucent white), with a 2px `primary` left-edge accent. |
| `sidebar-text` | `rgba(255,255,255,0.85)` | Nav link text. |
| `sidebar-text-hover` | `#ffffff` | Hovered nav link. |
| `sidebar-divider` | `rgba(255,255,255,0.10)` | Section dividers within the sidebar. |

> The active nav row uses **`primary` blue as the indicator** (a 2px left accent bar), keeping Notion's rule that the structural blue is the active/focus signal — even inside the dark surface.

### 2.4 Functional Status — Muted/Pastel Ramp (LayRate-specific)

LayRate requires OK/Watch/Alert semantics that Notion does not provide. Per the Phase 0 brief, these keep their **functional meaning** but are restyled with Notion's "soft surface" treatment: **muted/pastel background fills with deeper-toned text**, never bright saturated blocks. Each status is a paired `bg` + `text` token so it renders as a soft pill/badge, not a hard color block.

| Status | `bg` (soft fill) | `text` (deep) | Border | Meaning |
|---|---|---|---|---|
| **OK** | `#e8f5ec` | `#1f6b3a` | `#cfe8d6` | Normal / in-range / healthy |
| **Watch** | `#fdf3e0` | `#8a5a00` | `#f3e3bf` | Caution / near-threshold |
| **Alert** | `#fbe4e6` | `#9b1c24` | `#f3cdd0` | Danger / out-of-range / mortality |

**Derivation:** these are desaturated, high-lightness tints of the same hue families as Notion's sticker green/orange/pink, pushed toward pastel so they read as soft surfaces on the warm canvas. They are **functional, not decorative** — they never paint a CTA or structural fill (Notion guardrail preserved).

**Replaces** the old audit tokens:
- OK Green `#D5E8D4` / `#2D6A4F` → OK `#e8f5ec` / `#1f6b3a`
- Watch Amber `#FFF3CD` / `#856404` → Watch `#fdf3e0` / `#8a5a00`
- Alert Red `#F8D7DA` / `#721C24` → Alert `#fbe4e6` / `#9b1c24`

### 2.5 Cage Identity — Muted Sticker-Derived Accents

Cage colors are **functional identifiers** (which cage is which), not decoration. Notion's sticker palette is decorative-only, but LayRate needs stable per-cage identity. We map the four cages onto muted versions of Notion's sticker hues, tuned for distinguishability and WCAG AA contrast on white (addressing the audit's colorblind-safety concern in Part 5).

| Cage | Old hex | New `cage` hex | New `cage-soft` hex | Notion sticker source |
|---|---|---|---|---|
| **CAGE-A** | `#2D7D46` | `#2a9d6a` | `#d6f0e3` | accent-green `#1aae39` (muted) |
| **CAGE-B** | `#1D4E8F` | `#3b7bd9` | `#dcebfa` | accent-sky `#62aef0` (deepened) |
| **CAGE-C** | `#C2703E` | `#d97a3e` | `#fae3d0` | accent-orange `#dd5b00` (muted) |
| **CAGE-D** | `#6B4C8A` | `#8a6bbf` | `#e9e0f5` | accent-purple `#d6b6f6` (deepened) |

**Usage rules:**
- `cage` (full tone): used for thin accents — a 3px left border on a cage card, a legend dot, a chart line, a slot-grid header underline. Never a large fill.
- `cage-soft` (pastel): used for soft surface fills — a selected slot's background, a cage tab's active tint, a chart series' fill area.
- Cage color is sourced from a single `Cage::color` / `Cage::colorSoft` accessor (audit Part 2 quick win #1) — never re-derived in views.

### 2.6 Sticker Palette (decorative, reserved)

Notion's full sticker palette is retained for **decorative use only** — empty-state illustrations, icon tiles, onboarding stickers. These never paint structure, CTAs, or status.

| Token | Hex |
|---|---|
| `accent-sky` | `#62aef0` |
| `accent-purple` | `#d6b6f6` |
| `accent-pink` | `#ff64c8` |
| `accent-orange` | `#dd5b00` |
| `accent-teal` | `#2a9d99` |
| `accent-green` | `#1aae39` |
| `accent-brown` | `#523410` |

---

## 3. Typography

### 3.1 Font Family

Adopt **Inter** (open-source substitute for Notion's proprietary `NotionInter`) with Notion's negative tracking applied explicitly at display sizes. Replaces LayRate's `system-ui, ui-sans-serif, sans-serif` stack.

```css
font-family: "Inter", -apple-system, system-ui, "Segoe UI", Helvetica, Arial, sans-serif;
font-feature-settings: "lnum", "locl";
```

> Inter is loaded via CDN (e.g. `https://rsms.me/inter/inter.css` or Google Fonts). The negative letter-spacing values in §3.2 must be applied explicitly — Inter at default tracking reads looser than Notion's tuned cut.

### 3.2 Hierarchy (adopted from Notion, mapped to LayRate use)

| Token | Size / Weight / LH / Tracking | LayRate use | Tailwind equiv. |
|---|---|---|---|
| `display-1` | 64 / 700 / 1.0 / −2.125px | (reserved — not used in ops tool) | `text-[64px] font-bold leading-none tracking-[-2.125px]` |
| `display-2` | 54 / 700 / 1.04 / −1.875px | (reserved — not used in ops tool) | `text-[54px] font-bold leading-[1.04] tracking-[-1.875px]` |
| `heading-1` | 40 / 700 / 1.1 / −1px | (reserved — not used in ops tool) | `text-[40px] font-bold leading-[1.1] tracking-[-1px]` |
| `heading-2` | 26 / 700 / 1.23 / −0.625px | Page title (e.g. "Cage Management") | `text-[26px] font-bold leading-[1.23] tracking-[-0.625px]` |
| `heading-3` | 22 / 700 / 1.27 / −0.25px | Section heading within a page | `text-[22px] font-bold leading-[1.27] tracking-[-0.25px]` |
| `title` | 20 / 600 / 1.4 / −0.125px | Card title, modal title, callout | `text-xl font-semibold leading-[1.4] tracking-[-0.125px]` |
| `body-md` | 16 / 400 / 1.5 / 0 | Default body copy, primary form labels | `text-base font-normal leading-[1.5]` |
| `body-sm` | 15 / 400 / 1.33 / 0 | Dense body, table rows, nav, card meta | `text-[15px] font-normal leading-[1.33]` |
| `button` | 16 / 500 / 1.5 / 0 | Button labels | `text-base font-medium leading-[1.5]` |
| `caption` | 14 / 400 / 1.43 / 0 | Captions, footnotes, table cell secondary | `text-sm font-normal leading-[1.43]` |
| `eyebrow` | 12 / 600 / 1.33 / +0.125px | Pill badges, metric labels, small labels | `text-xs font-semibold leading-[1.33] tracking-[0.125px]` |

> **Replaces** the audit's informal scale (`text-xl` page title → `text-lg` section → `text-base` subsection → `text-sm` card label → `text-xs`/`text-[10px]` captions). The new scale is explicit and enforced via the Tailwind extend in §9.
>
> **Display sizes** (display-1, display-2, heading-1) are reserved and intentionally unused — LayRate is a dense ops tool, not a marketing site. They remain in the token set for completeness but should not appear in Blade views.

### 3.3 Principles (from Notion)

- Headlines are **tight, heavy, quiet-confident**: weight 700 with negative tracking.
- Body stays at **400 / 1.5 line-height** for readability.
- The contrast between a heavy headline and calm body is the primary expressive lever — no decorative typography.
- **Don't** set body copy in a heavy weight; 400 belongs to body, 700 to headlines.

---

## 4. Spacing Scale

Adopt Notion's 8px-base scale, replacing the audit's inconsistent `p-5` / `gap-3` / `gap-5` mixing.

| Token | Value | Tailwind | Use |
|---|---|---|---|
| `xxs` | 4px | `p-1` / `gap-1` | Tight gaps: inside a badge, between an icon and its label |
| `xs` | 8px | `p-2` / `gap-2` | Small card internal gaps, form field icon padding |
| `sm` | 12px | `p-3` / `gap-3` | Table cell padding, list row padding, compact card gaps |
| `md` | 16px | `p-4` / `gap-4` | Default card internal padding, form field vertical rhythm, standard card gaps |
| `lg` | 24px | `p-6` / `gap-6` | Card interior padding (feature-card), section gaps |
| `xl` | 28px | `p-7` / `gap-7` | Larger section gaps |
| `xxl` | 32px | `p-8` / `gap-8` | Page-level section separation, modal padding |

**Rules:**
- `<main>` page padding: `md` (16px) on tablet, `lg` (24px) on desktop.
- Card interior: `lg` (24px) for feature cards, `md` (16px) for dense data cards.
- Card-to-card gap: `md` (16px) within a row, `lg` (24px) between sections.
- Form field vertical rhythm: `md` (16px) between fields.
- **Replaces** the old `p-5` (20px) main padding and the `gap-3`/`gap-5` mixing — now every gap resolves to a named token.

---

## 5. Border Radius

Adopt Notion's scale. The contrast between pill CTAs and tight inputs is intentional.

| Token | Value | Tailwind | Use |
|---|---|---|---|
| `xs` | 4px | `rounded` | Form fields, small tags, inline chips, text inputs |
| `sm` | 5px | `rounded-[5px]` | Menu items, list rows, status pills |
| `md` | 8px | `rounded-lg` | Utility/nav buttons, smaller cards, cage tabs |
| `lg` | 12px | `rounded-xl` | Feature cards, illustration frames, content tiles, slot cards |
| `xl` | 16px | `rounded-2xl` | Large containers, modals, image wells, auth card |
| `full` | 9999px | `rounded-full` | Marketing/primary CTAs, badges, circular icon buttons, focus-safe pills |

**Rules:**
- Primary CTA: `full` (pill).
- Secondary/utility button: `md` (8px).
- Form inputs: `xs` (4px) — **never** pill. (Notion guardrail.)
- Cards: `lg` (12px) for feature cards, `md` (8px) for dense data cards.
- Modals: `xl` (16px).

---

## 6. Elevation & Shadows

Adopt Notion's "barely-there" philosophy — many near-transparent layers, never a hard cast. Most cards rely on a hairline alone.

| Level | Treatment | Tailwind class | Use |
|---|---|---|---|
| **0 — Flat** | 1px `hairline` border, no shadow | `border border-hairline` | Default cards on the warm canvas |
| **1 — Soft** | Layered micro-shadow (4-stop) | `shadow-soft` (custom, see §9) | Raised feature cards, floating buttons, focused inputs, sticky panels |
| **2 — Elevated** | Deeper 5-stop stack | `shadow-elevated` (custom, see §9) | Modals, popovers, dropdowns |

**Shadow definitions (CSS):**
```css
.shadow-soft {
  box-shadow:
    rgba(0,0,0,0.01) 0 0.175px 1.041px,
    rgba(0,0,0,0.02) 0 0 0.8px 2.925px,
    rgba(0,0,0,0.027) 0 2.025px 7.847px,
    rgba(0,0,0,0.04) 0 4px 18px;
}
.shadow-elevated {
  box-shadow:
    rgba(0,0,0,0.01) 0 0.175px 1.041px,
    rgba(0,0,0,0.02) 0 0 0.8px 2.925px,
    rgba(0,0,0,0.027) 0 2.025px 7.847px,
    rgba(0,0,0,0.04) 0 4px 18px,
    rgba(0,0,0,0.05) 0 23px 52px;
}
```

> **Replaces** any ad-hoc `shadow-md` / `shadow-lg` usage. Notion's elevation is deliberately faint; do not substitute heavier Tailwind shadows.

---

## 7. Component Specs

> These are the defaults for LayRate's Blade components. States documented: Default, Hover (LayRate addition — Notion documented only Default/Pressed), Pressed/Active, Disabled, Focus.

### 7.1 Buttons

| Component | Surface | Text | Type | Radius | Padding | States |
|---|---|---|---|---|---|---|
| **`btn-primary`** | `primary #0075de` | `on-primary #ffffff` | `button` | `full` | `10px 20px` | Hover: `primary-active #005bab`. Pressed: `primary-active` + `scale(0.97)`. Disabled: `opacity-50 cursor-not-allowed`. Focus: `primary` ring 2px offset 2px. |
| **`btn-secondary`** | `surface #ffffff` | `ink #1f1f1f` | `button` | `full` | `10px 20px` | 1px `hairline` border + `shadow-soft`. Hover: bg `canvas-soft`. Pressed: `scale(0.97)`. Focus: `primary` ring. |
| **`btn-utility`** | `surface #ffffff` | `ink #1f1f1f` | `button` | `md` (8px) | `6px 14px` | 1px `hairline` border. Hover: bg `canvas-soft`. Pressed: `scale(0.97)`. Use for nav/plan-select where pill is too large. |
| **`btn-danger`** | `alert-bg` is **not** used for fills. Danger uses `alert-text #9b1c24` on `alert-bg #fbe4e6` surface for **soft** danger, OR a solid `#9b1c24` fill with white text for **destructive** actions (delete). | — | `button` | `md` (8px) | `8px 16px` | Destructive variant: solid `#9b1c24` fill, white text, hover `#7a161d`. Soft variant: `alert-bg` fill, `alert-text` text, hover deeper tint. |
| **`btn-icon`** | `rgba(0,0,0,0.05)` | `ink` | — | `full` | `8px` | Circular. Hover: `rgba(0,0,0,0.08)`. Pressed: `scale(0.9)`. Must carry `aria-label`. |

> **Touch targets:** all buttons minimum 44×44px hit area on tablet/kiosk (audit Part 3). Bump vertical padding to `12px` on forms used by operators in barn conditions (egg logging, mortality).

### 7.2 Cards

| Component | Surface | Text | Type | Radius | Padding | Border | Shadow |
|---|---|---|---|---|---|---|---|
| **`card`** (feature) | `surface #ffffff` | `ink` | `body-md` | `lg` (12px) | `lg` (24px) | 1px `hairline` | flat (Level 0) |
| **`card-elevated`** | `surface #ffffff` | `ink` | `body-md` | `lg` (12px) | `lg` (24px) | 1px `hairline` | `shadow-soft` (Level 1) — for floating/sticky cards |
| **`card-soft`** (highlighted) | `canvas-soft #f6f5f4` | `ink` | `body-sm` | `md` (8px) | `lg` (24px) | none | flat — distinguished by surface tint, not colored border (Notion pattern for "featured") |
| **`metric-card`** | `surface #ffffff` | `ink` | — | `lg` (12px) | `lg` (24px) | 1px `hairline` | flat | Anatomy: eyebrow label → large value (`heading-3` or `title`) → optional status badge → optional delta (`caption`). Uniform across all metric cards (audit Part 5). |
| **`cage-card`** | `surface #ffffff` | `ink` | `body-sm` | `lg` (12px) | `md` (16px) | 1px `hairline` + 3px left accent in `cage` color | flat | Slot grid header uses `cage-soft` tint. |
| **`slot-card`** | `surface #ffffff` → `cage-soft` when selected | `ink` | `caption` | `lg` (12px) | `sm` (12px) | 1px `hairline`; selected: `cage` 2px border | flat | `role="button" tabindex="0"` + keydown handler (audit Part 3). |
| **`empty-state`** | `canvas-soft` | `ink-muted` | `body-md` | `xl` (16px) | `xxl` (32px) | none | flat |

### 7.3 Inputs & Forms

**`text-input`** — text/number field
- Surface `surface #ffffff`, text `ink`, type `body-sm` (15px), 1px `#dcdcdc` border, radius `xs` (4px), padding `8px 10px` (bumped from Notion's 6px for touch targets per audit Part 3).
- **Focus:** `primary` 2px ring (offset 1px) + `shadow-soft`. Border becomes `primary`.
- **Error:** border `alert-text #9b1c24` + `ring-1 ring-[#f3cdd0]` + error message below in `alert-text` `caption`. Paired border + message (audit Part 3).
- **Disabled:** bg `canvas-soft`, text `ink-faint`, `cursor-not-allowed`.
- Placeholder: `ink-faint`.

**`select`** — dropdown
- Same chrome as `text-input`. Native `<select>` styled with a custom chevron (`lucide chevron-down`) via appearance-none.

**`checkbox` / `toggle`**
- Checkbox: 16px, `hairline` border, checked fill `primary`, white check. Radius `xs`.
- Toggle (sensor on/off): 36×20px track, `hairline` border, on-state track `primary`, knob white. Radius `full`.

**Form layout:**
- Field vertical rhythm: `md` (16px) gap.
- Label: `eyebrow` (12/600) above field, `ink-secondary`.
- Help text: `caption` below field, `ink-muted`.

### 7.4 Badges & Pills

**`badge-pill`** — eyebrow/category pill (Notion native)
- Surface `surface`, text `primary`, type `eyebrow`, radius `full`, padding `4px 8px`, 1px `hairline` border. Use for neutral category tags.

**`status-badge`** — functional status (LayRate-specific)
- Surface = status `bg` (soft fill), text = status `text` (deep), type `eyebrow`, radius `full`, padding `4px 10px`, 1px status border. Renders as a soft pastel pill, never a bright block.
- Single source: `<x-status-badge status="Watch" type="sensor" />` component (audit Part 6).

**`cage-badge`** — cage identity pill
- Surface `cage-soft`, text `cage` (deep), type `eyebrow`, radius `full`, padding `4px 10px`. Optional 4px dot in `cage` color before label.

### 7.5 Tables

**`data-table`** (Notion `ex-data-table`)
- Header: `canvas-soft` bg, `eyebrow` type (mono-caps feel via `tracking-[0.125px]` + uppercase), `ink-muted`.
- Body: `body-sm`, `ink`.
- Cell padding: `sm md` (12px 16px).
- Row border: 1px `hairline`. Zebra: none (Notion is flat; rely on hairlines). Hover row: `canvas-soft` at 50%.
- Sticky header: `shadow-soft` on the header row when scrolled.

### 7.6 Modals

**`modal`** (Notion `ex-modal-card`)
- Surface `surface`, radius `xl` (16px), padding `lg` (24px), `shadow-elevated` (Level 2).
- Backdrop: `rgba(0,0,0,0.4)` + `backdrop-blur-sm`.
- `role="dialog" aria-modal="true"`, Escape to close, click-backdrop to close (audit Part 3).
- Title: `heading-3`. Body: `body-md`. Actions row: right-aligned, `md` gap.
- Max width: `max-w-lg` (32rem) for standard, `max-w-2xl` for cage config.

### 7.7 Navigation

**`nav-bar`** (top, if added) — white `canvas`, `ink` text, `body-sm`, padding `md`. Not currently used (LayRate uses sidebar).

**`sidebar`** — see §2.3. Active row: `sidebar-active` bg + 2px `primary` left accent. Section dividers: 1px `sidebar-divider`. Collapses to 4rem (audit Part 1) with tooltip-on-hover.

**`cage-tabs`** — `btn-utility`-style tabs, active state: `cage-soft` bg + `cage` text + 2px `cage` bottom border. Inactive: `surface` + `ink-muted`.

### 7.8 Toast / Flash

**`toast`** (Notion `ex-toast`)
- Surface `surface`, radius `xl` (16px), padding `sm md`, `shadow-soft`, `body-sm`. Auto-dismiss 4s. Success/Watch/Alert variants tint the left edge 3px with the status `text` color.

### 7.9 Charts (Chart.js)

- Global defaults: `Chart.defaults.color = '#31302e'` (`ink-secondary`); `Chart.defaults.font.family = 'Inter', system-ui, sans-serif`; legend font 12px.
- Series colors = `cage` palette (§2.5). Fill areas = `cage-soft` at 30% alpha.
- Grid lines: `hairline`. Axis labels: `caption` / `ink-muted`.

---

## 8. Icons

Lucide icons (already in use). Rules:
- Stroke width 1.5px (`w-N h-N` with default stroke; do not override to 2px).
- Size: 16px (`w-4 h-4`) inline in badges/labels, 20px (`w-5 h-5`) in nav/buttons, 24px (`w-6 h-6`) in empty states.
- Color: inherit from text color, except `btn-icon` where the glyph is `ink`.
- Every icon-only button **must** carry an `aria-label` (audit Part 3).

---

## 9. Tailwind Config Mapping

The concrete `tailwind.config` extend block to drop into `layouts/app.blade.php` (Phase 1+ work — not applied now):

```js
tailwind.config = {
  theme: {
    extend: {
      colors: {
        // Surface & text (Notion)
        'canvas-soft': '#f6f5f4',
        'surface': '#ffffff',
        'hairline': '#e6e6e6',
        'ink': { DEFAULT: '#1f1f1f', secondary: '#31302e', muted: '#615d59', faint: '#a39e98' },
        // Structural accent (Notion)
        'primary': { DEFAULT: '#0075de', active: '#005bab' },
        'secondary': '#213183',
        'on-primary': '#ffffff',
        // Dark sidebar (LayRate exception)
        'sidebar-bg': '#1a2342',
        // Functional status (LayRate muted ramp)
        'ok': { bg: '#e8f5ec', text: '#1f6b3a', border: '#cfe8d6' },
        'watch': { bg: '#fdf3e0', text: '#8a5a00', border: '#f3e3bf' },
        'alert': { bg: '#fbe4e6', text: '#9b1c24', border: '#f3cdd0' },
        // Cage identity (LayRate muted sticker-derived)
        'cage-a': { DEFAULT: '#2a9d6a', soft: '#d6f0e3' },
        'cage-b': { DEFAULT: '#3b7bd9', soft: '#dcebfa' },
        'cage-c': { DEFAULT: '#d97a3e', soft: '#fae3d0' },
        'cage-d': { DEFAULT: '#8a6bbf', soft: '#e9e0f5' },
        // Sticker (decorative only)
        'sticker': { sky: '#62aef0', purple: '#d6b6f6', pink: '#ff64c8', orange: '#dd5b00', teal: '#2a9d99', green: '#1aae39', brown: '#523410' },
      },
      fontFamily: {
        sans: ['Inter', '-apple-system', 'system-ui', '"Segoe UI"', 'Helvetica', 'Arial', 'sans-serif'],
      },
      fontSize: {
        'display-1': ['64px', { lineHeight: '1.0', letterSpacing: '-2.125px', fontWeight: '700' }],
        'display-2': ['54px', { lineHeight: '1.04', letterSpacing: '-1.875px', fontWeight: '700' }],
        'heading-1': ['40px', { lineHeight: '1.1', letterSpacing: '-1px', fontWeight: '700' }],
        'heading-2': ['26px', { lineHeight: '1.23', letterSpacing: '-0.625px', fontWeight: '700' }],
        'heading-3': ['22px', { lineHeight: '1.27', letterSpacing: '-0.25px', fontWeight: '700' }],
        'title': ['20px', { lineHeight: '1.4', letterSpacing: '-0.125px', fontWeight: '600' }],
        'body-md': ['16px', { lineHeight: '1.5' }],
        'body-sm': ['15px', { lineHeight: '1.33' }],
        'button': ['16px', { lineHeight: '1.5', fontWeight: '500' }],
        'caption': ['14px', { lineHeight: '1.43' }],
        'eyebrow': ['12px', { lineHeight: '1.33', letterSpacing: '0.125px', fontWeight: '600' }],
      },
      spacing: { 'xxs': '4px', 'xs': '8px', 'sm': '12px', 'md': '16px', 'lg': '24px', 'xl': '28px', 'xxl': '32px' },
      borderRadius: { 'xs': '4px', 'sm': '5px', 'md': '8px', 'lg': '12px', 'xl': '16px' },
      boxShadow: {
        'soft': 'rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px',
        'elevated': 'rgba(0,0,0,0.01) 0 0.175px 1.041px, rgba(0,0,0,0.02) 0 0 0.8px 2.925px, rgba(0,0,0,0.027) 0 2.025px 7.847px, rgba(0,0,0,0.04) 0 4px 18px, rgba(0,0,0,0.05) 0 23px 52px',
      },
    }
  }
}
```

> Tailwind's default spacing scale (`p-4`=16px, `p-6`=24px, `p-8`=32px) already aligns with Notion's `md`/`lg`/`xxl`. The named tokens above are for semantic clarity; in practice `p-4`/`p-6`/`gap-4`/`gap-6` resolve to the right values. The audit's `p-5` (20px) and `gap-3`/`gap-5` mixing is **removed** — gaps resolve only to `xs/sm/md/lg/xl/xxl` (i.e. `p-2/p-3/p-4/p-6/p-7/p-8`).

---

## 10. Reconciliation Decisions & Exceptions

Each decision is a deliberate, documented mapping — later phases should not re-litigate these without flagging.

| # | Decision | Rationale |
|---|---|---|
| 1 | **Inter replaces system-ui stack.** | Notion's tuned Inter is the type voice; system-ui was informal (audit Part 0). Inter via CDN with explicit negative tracking approximates `NotionInter`. |
| 2 | **Primary CTA = Notion blue `#0075de`, not navy.** | Splits LayRate's old navy double-duty. Navy → sidebar surface; blue → the one structural action color. Removes button/header ambiguity. |
| 3 | **Sidebar = deep navy `#1a2342`, persistent.** | **Exception** to Notion's "single hero night band" guardrail. A persistent nav surface is a functional admin-tool requirement, not marketing. Documented and contained — the night treatment does not spread to other bands. |
| 4 | **Active nav indicator = `primary` blue (2px left accent), even on dark sidebar.** | Preserves Notion's rule that the structural blue is the active/focus signal everywhere. |
| 5 | **Functional status = muted/pastel ramp, not bright colors.** | Per Phase 0 brief. OK/Watch/Alert keep meaning but render as soft surface fills (Notion "soft surface" treatment). Never paint CTAs or structure. |
| 6 | **Cage colors = muted sticker-derived, soft + full pair.** | Cage identity is functional (which cage). Muted tones from Notion's sticker palette, tuned for WCAG AA + colorblind distinguishability (audit Part 5). Full tone = thin accents only; soft = fills. |
| 7 | **Display sizes (display-1/2, heading-1) reserved, unused.** | LayRate is a dense ops tool, not a marketing site. Tokens retained for completeness; headings top out at `heading-2` (26px) for page titles. |
| 8 | **Inputs = `xs` radius (4px), never pill.** | Direct Notion guardrail. CTAs are pill; inputs are tight — the contrast is intentional. |
| 9 | **Touch targets bumped to 44×44px on operator forms.** | Notion's 6px input padding is for marketing forms; LayRate operators use tablets/kiosks in barn conditions (audit Part 3). Inputs pad `8px 10px`, buttons `10-12px 20px`. |
| 10 | **Shadows = Notion's barely-there layers, never heavy.** | `shadow-soft` / `shadow-elevated` only. No `shadow-md`/`shadow-lg`/`shadow-2xl`. |
| 11 | **`p-5` (20px) removed from the spacing vocabulary.** | 20px is not on Notion's 8px-base scale. Gaps resolve only to `xs/sm/md/lg/xl/xxl`. Fixes the audit's `gap-3`/`gap-5` mixing. |
| 12 | **Hover states added.** | Notion documented only Default/Pressed. LayRate is interactive (not marketing static), so hover is defined per component for clarity. |

---

## 11. Do's and Don'ts (LayRate-adapted guardrails)

Adopted from Notion's Do's/Don'ts, adapted for LayRate's functional requirements. **These are the guardrails for all later phases.**

### Do
- Reserve `primary` (`#0075de`) for the primary action, inline links, and the active/focus signal — nothing decorative.
- Keep the page on the warm `canvas-soft` (`#f6f5f4`); use pure white `surface` for cards and fields to create gentle figure/ground.
- Render status (OK/Watch/Alert) as **soft pastel surface fills** (`ok`/`watch`/`alert` `bg` + `text` pairs) — muted, not bright.
- Render cage identity with the `cage`/`cage-soft` pair — full tone for thin accents, soft for fills.
- Source cage color and status color from **single accessors/components** (`Cage::color`, `<x-status-badge>`), never re-derive in views (audit Part 2).
- Set page titles in `heading-2` (26/700/−0.625px) with negative tracking applied explicitly.
- Use pill `full` for primary CTAs and tighter `md` (8px) for utility/nav buttons — the contrast is intentional.
- Define surfaces with `hairline` + `shadow-soft`/`shadow-elevated`, never heavy drop-shadows.
- Bump touch targets to 44×44px on operator-facing forms (egg logging, mortality).
- Add `role="button" tabindex="0"` + keydown + `aria-label` to all clickable non-buttons (slot cards) (audit Part 3).
- Add `:focus-visible` ring (`primary`, 2px, offset 2px) globally for keyboard accessibility (audit Part 3).

### Don't
- **Don't** paint a CTA or structural fill in any status or sticker color — those are soft-surface meaning/decoration only.
- **Don't** introduce a second structural accent alongside `primary`. Sidebar navy is a *surface*, not an accent.
- **Don't** put pill `full` radii on form fields — inputs stay tight at `xs` (4px).
- **Don't** drop heavy shadows (`shadow-md`/`shadow-lg`/`shadow-2xl`) — use `shadow-soft`/`shadow-elevated` only.
- **Don't** set body copy in a heavy weight — 400 for body, 700 for headlines.
- **Don't** place type on pure clinical white for full pages — `canvas-soft` is the page canvas.
- **Don't** use `p-5` (20px) or ad-hoc `gap-3`/`gap-5` — gaps resolve only to the named spacing scale.
- **Don't** use display sizes (`display-1/2`, `heading-1`) in ops views — reserved.
- **Don't** use bright saturated status blocks — always the muted `bg`/`text` pair.
- **Don't** re-derive cage/status colors inline in Blade — use the accessor/component.
- **Don't** use native `confirm()`/`alert()` — use the themed `confirm-modal` / `toast` partials (audit Parts 3, 6).
- **Don't** leave icon-only buttons without `aria-label` (audit Part 3).

---

## 12. Phase Reference

Later phases consume this document as the single visual source of truth:

- **Phase 1** — apply the §9 Tailwind config to `layouts/app.blade.php`; load Inter; add global `:focus-visible` + `shadow-soft`/`shadow-elevated` CSS; restyle the sidebar per §2.3.
- **Phase 2+** — restyle each Blade view using the §7 component specs, sourcing cage/status color from accessors/components (§2.4, §2.5, audit Part 2), and applying the §11 guardrails throughout.
- All phases check against §10 (reconciliation decisions) and §11 (Do's/Don'ts) before marking complete.

**Phase 0 is complete. Awaiting approval before Phase 1.**
