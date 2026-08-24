# LayRate — DESIGN-SYSTEM.md

Source of truth: the `@theme` block in `resources/css/app.css`. This document
describes every token that exists today (documented as-is, no invented tokens),
plus the **one sanctioned addition** currently in review: `--color-navy` (§2.3).

## 1. Font

```
--font-family-sans: Inter, -apple-system, system-ui, "Segoe UI", Helvetica, Arial, sans-serif
```

## 2. Colors

### 2.1 Core brand & ink scale
| Token | Value |
|---|---|
| `primary` | `#0075de` |
| `primary-active` | `#005bab` |
| `on-primary` | `#ffffff` |
| `secondary` | `#213183` |
| `surface` | `#ffffff` |
| `canvas-soft` | `#f6f5f4` |
| `sidebar-bg` | `#1a2342` |
| `hairline` | `#e6e6e6` |
| `ink` | `#1f1f1f` |
| `ink-secondary` | `#31302e` |
| `ink-muted` | `#615d59` |
| `ink-faint` | `#a39e98` |

### 2.2 Semantic status triples
| Token | Value |
|---|---|
| `ok-bg` / `ok-text` / `ok-border` | `#e8f5ec` / `#1f6b3a` / `#cfe8d6` |
| `watch-bg` / `watch-text` / `watch-border` | `#fdf3e0` / `#8a5a00` / `#f3e3bf` |
| `alert-bg` / `alert-text` / `alert-border` | `#fbe4e6` / `#9b1c24` / `#f3cdd0` |

### 2.3 Navy — PROPOSED addition (pending review)
The app uses several navy values with no single home. **Proposed canonical:
`--color-navy: #002D5E`** (most-used non-tokenized navy — primary buttons,
underline-tabs, system-time, profile; reads as the app's dark-navy brand).
- `#213183` stays `secondary` (gradients/confirm-modal).
- `#0075de` stays `primary` (action blue).
- `#001F42` (button hover) / `#102A4C` (legacy) collapse into `navy` (hover via
  opacity/filter; `#102A4C` migrates to `navy` in the later page pass).

### 2.4 Cage palette
`cage-a #2a9d6a` / `cage-a-soft #d6f0e3` · `cage-b #3b7bd9` / `cage-b-soft #dcebfa`
· `cage-c #d97a3e` / `cage-c-soft #fae3d0` · `cage-d #8a6bbf` / `cage-d-soft #e9e0f5`

### 2.5 Sticker palette (decorative only)
`sticker-sky #62aef0` · `sticker-purple #d6b6f6` · `sticker-pink #ff64c8`
· `sticker-orange #dd5b00` · `sticker-teal #2a9d99` · `sticker-green #1aae39`
· `sticker-brown #523410`

## 3. Spacing scale
| Token | px |
|---|---|
| `space-xxs` | 4 |
| `space-xs` | 8 |
| `space-sm` | 12 |
| `space-md` | 16 |
| `space-lg` | 24 |
| `space-xl` | 28 |
| `space-xxl` | 32 |

## 4. Border radius scale
`radius-xs 4px` · `radius-sm 5px` · `radius-md 8px` · `radius-lg 12px` · `radius-xl 16px`

## 5. Elevation
| Token | Shadow |
|---|---|
| `shadow-soft` | `rgba(0,0,0,.01) 0 .175px 1.041px, rgba(0,0,0,.02) 0 0 .8px 2.925px, rgba(0,0,0,.027) 0 2.025px 7.847px, rgba(0,0,0,.04) 0 4px 18px` |
| `shadow-elevated` | same + `, rgba(0,0,0,.05) 0 23px 52px` |

## 6. Typography
| Token | size / lh / ls / weight |
|---|---|
| `display-1` | 64px / 1.0 / −2.125px / 700 |
| `display-2` | 54px / 1.04 / −1.875px / 700 |
| `heading-1` | 40px / 1.1 / −1px / 700 |
| `heading-2` | 26px / 1.23 / −0.625px / 700 |
| `heading-3` | 22px / 1.27 / −0.25px / 700 |
| `title` | 20px / 1.4 / −0.125px / 600 |
| `body-md` | 16px / 1.5 |
| `body-sm` | 15px / 1.33 |
| `button` | 16px / 1.5 / 500 |
| `caption` | 14px / 1.43 |
| `eyebrow` | 12px / 1.33 / +0.125px / 600 |

## 7. Component tokens in use (this pass)
`card`, `button`, `underline-tabs` — to be rewritten onto the tokens above
(including `navy` once confirmed) and documented here after the change.