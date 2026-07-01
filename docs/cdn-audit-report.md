# LayRate — External CDN Dependency Audit

**Date:** 2026-07-01
**Scope:** All files under `resources/views/`

---

## External Dependencies Found

### 1. Tailwind CSS (Play CDN)

| Property | Value |
|---|---|
| **URL** | `https://cdn.tailwindcss.com` |
| **Type** | `<script src>` (Play CDN — JIT compiler in browser) |
| **Version** | Unpinned (`@latest` equivalent) |
| **Files** | `resources/views/layouts/app.blade.php` (line 20), `resources/views/auth/login.blade.php` (line 11) |
| **Config** | Custom `tailwind.config` block in `layouts/app.blade.php` lines 29-51 (custom colors, font family) |
| **Self-hostable?** | **No — not as a static file.** The Play CDN is a JavaScript runtime that scans the DOM at page-load and generates CSS on the fly. It cannot be saved as a `.css` file. Must be compiled using the Tailwind CLI against all view templates. |

### 2. Chart.js

| Property | Value |
|---|---|
| **URL** | `https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js` |
| **Type** | `<script src>` |
| **Version** | Pinned to `4.4.0` |
| **Files** | `resources/views/layouts/app.blade.php` (line 23) |
| **Self-hostable?** | **Yes.** Single JS file, no sub-dependencies. |

### 3. Lucide Icons

| Property | Value |
|---|---|
| **URL** | `https://unpkg.com/lucide@latest/dist/umd/lucide.min.js` |
| **Type** | `<script src>` |
| **Version** | Unpinned (`@latest`) |
| **Files** | `resources/views/layouts/app.blade.php` (line 26), `resources/views/auth/login.blade.php` (line 12) |
| **Self-hostable?** | **Yes.** Single UMD bundle. Need to pin to a specific version. |

### 4. Inter Font (via rsms.me)

| Property | Value |
|---|---|
| **CSS URL** | `https://rsms.me/inter/inter.css` |
| **Preconnect** | `https://rsms.me/` |
| **Type** | `<link rel="stylesheet">` + `<link rel="preconnect">` |
| **Version** | Unpinned (serves latest from rsms.me) |
| **Files** | `resources/views/layouts/app.blade.php` (lines 16-17), `resources/views/auth/login.blade.php` (lines 17-18) |
| **Self-hostable?** | **Yes, but requires downloading the CSS + all referenced `.woff2` font files.** The CSS at `rsms.me/inter/inter.css` references multiple `.woff2` files hosted on `rsms.me/inter/`. All font files must be downloaded and CSS paths updated to relative. |

---

## Summary Table

| # | Dependency | URL | Version | Files | Self-Hostable? | Notes |
|---|---|---|---|---|---|---|
| 1 | Tailwind CSS (Play CDN) | `https://cdn.tailwindcss.com` | Unpinned | `layouts/app.blade.php`, `auth/login.blade.php` | **Requires compilation** | Play CDN is a JS JIT compiler — must use Tailwind CLI to build static CSS from all views |
| 2 | Chart.js | `https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js` | `4.4.0` | `layouts/app.blade.php` | Yes | Single file, already pinned |
| 3 | Lucide Icons | `https://unpkg.com/lucide@latest/dist/umd/lucide.min.js` | `@latest` (unpinned) | `layouts/app.blade.php`, `auth/login.blade.php` | Yes | Single UMD bundle — must pin version |
| 4 | Inter Font CSS | `https://rsms.me/inter/inter.css` | Unpinned | `layouts/app.blade.php`, `auth/login.blade.php` | Yes | CSS references multiple `.woff2` files — download both CSS + fonts |
| 5 | Inter Font `.woff2` files | `https://rsms.me/inter/font-files/*.woff2` | Unpinned | Referenced by `inter.css` | Yes | Multiple weight/variant files — all must be downloaded |

---

## Tailwind Play CDN — Special Handling Required

The app uses the **Tailwind Play CDN** (`<script src="https://cdn.tailwindcss.com">`), which is a browser-based JIT compiler. It:

1. Scans the DOM at page load for Tailwind utility classes
2. Generates CSS dynamically in a `<style>` tag
3. Respects the `tailwind.config` block embedded in `layouts/app.blade.php` (lines 29-51)

**Cannot be saved as a static file.** The correct approach:

1. Install Tailwind CLI locally (`npx tailwindcss`)
2. Create a `tailwind.config.js` mirroring the inline config from `layouts/app.blade.php`
3. Point the content scanner at all `resources/views/**/*.blade.php` files
4. Compile: `npx tailwindcss -i ./resources/css/input.css -o ./public/css/tailwind.css --minify`
5. Replace both `<script src="https://cdn.tailwindcss.com">` tags with `<link href="{{ asset('css/tailwind.css') }}" rel="stylesheet">`
6. Remove the inline `<script>tailwind.config = {...}</script>` block (no longer needed)

**Risk:** If any view uses dynamic class names (e.g., `class="text-{{ $color }}"`), Tailwind's purge scanner won't detect them and they'll be missing from the compiled output. Must audit all views for this pattern before compiling.

---

## Inter Font — Font Files to Download

The `inter.css` stylesheet at `rsms.me/inter/inter.css` serves as a font-face manifest. It references `.woff2` files at paths like:

```
https://rsms.me/inter/font-files/Inter-Regular.woff2
https://rsms.me/inter/font-files/Inter-Medium.woff2
https://rsms.me/inter/font-files/Inter-SemiBold.woff2
https://rsms.me/inter/font-files/Inter-Bold.woff2
... (multiple weights and styles)
```

All referenced `.woff2` files must be downloaded to `public/fonts/` and the CSS updated to use `url('/fonts/Inter-*.woff2')` instead of the rsms.me URLs.

---

## No Other External Dependencies Found

All other 24 Blade view files (partials, components, feature pages) contain **zero** external URLs. All styling is inline or via Tailwind utility classes. All JS is inline vanilla JS.
