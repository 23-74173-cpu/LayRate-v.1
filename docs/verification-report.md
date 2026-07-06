# Verification Report — Audit Findings (Real Evidence)

**Date:** 2026-07-05
**Method:** curl HTTP requests, artisan tinker, direct DB queries, rendered HTML inspection

---

## Item 2 — Sensor Uncheck Bug → **CORRECTED**

Hidden+checkbox pattern works. Controller code at `CageController.php:121-131` processes unchecked sensors correctly.

**Evidence (curl PUT + tinker):**
```
BEFORE:  HardwareItem ID=1 status=active  cage_slot_id=1 (CAGE-A, slot 1)
AFTER:   HardwareItem ID=1 status=spare   cage_slot_id=null
RESTORE: HardwareItem ID=1 status=active  cage_slot_id=1  ← re-checked OK
```

**Test command used (X-XSRF-TOKEN header):**
```bash
curl -X POST http://localhost:8080/cages/1 \
  -H "X-XSRF-TOKEN: ${decrypted_token}" \
  -d "_method=PUT&slots[1][has_sensor]=0"
```

Both directions verified. The hidden-input pattern (`<input type="hidden" value="0">` + checkbox) correctly sends `0` when unchecked, which the controller casts to `false` and triggers the return-to-spare path at line 129-130. The `_token` POST parameter approach failed with 419 (CSRF mismatch) in this environment; the X-XSRF-TOKEN header succeeded.

**Secondary finding (modal auto-reopen on resize failure):** CONFIRMED — `$editCage` is never passed to the view in `CageController::index()` line 41. The `@isset($editCage)` check at `cages/index.blade.php:1330` will always evaluate false. Visual impact needs manual browser check.

---

## Item 18 — "Wing" Terminology → **CONFIRMED** (not found)

Rendered HTML from `GET /chickens` (200 OK) searched for `\bwing\b`, `\bsection\b`, `\bpen\b`, `\bbarn\b`, `\bhouse\b`:

| Term | Matches in rendered HTML | Classification |
|---|---|---|
| `wing` | 0 | Not found |
| `section` | 0 | Not found |
| `block` | 38 | All Tailwind CSS classes (`block text-xs`, `sm:block`) |
| `row`/`col` | 8/12 | Contemporary labels (`CAGE-A — Row 4, Col 3`) |
| `pen`, `barn`, `house` | 0 | Not found |

No legacy terminology exists in rendered output or Blade source. The grep of Blade source found `@section()` directives (server-side only, not location terms) and `section` as a JS variable (`stagingSection` — DOM element, not farm terminology).

---

## Item 20c — Health Events UI → **CONFIRMED** (exists)

| Aspect | Evidence |
|---|---|
| Blade modal | `resources/views/chickens/partials/health-event-modal.blade.php` — full form (103 lines) |
| Rendered in HTML | `grep 'Log Health Event' /tmp/chickens_page.html` → 1 match |
| Route | `POST chickens/health-event` → `ChickensController@storeHealthEvent` |
| Form fields | hen_id (hidden), event_date (required, date), event_type (required, sick/treated/recovered), description (optional), notes (optional) |
| Controller validates | `hen_id => required|exists:hens,id`, `event_date => required|date`, `event_type => required|in:sick,treated,recovered` |
| Create logic | `HealthEvent::create([... recorded_by => auth()->id()])` |
| Redirect | `redirect()->back()->with('success', 'Health event logged.')` |

**End-to-end test:**
```bash
curl -X POST http://localhost:8080/chickens/health-event \
  -H "X-XSRF-TOKEN: ${token}" \
  -d "hen_id=1&event_date=2026-07-05&event_type=sick&description=Respiratory+symptoms&notes=Test"
```
→ 302 redirect to /chickens
→ `HealthEvent ID=1 type=sick date=2026-07-05` confirmed via tinker

**Gap:** No standalone list/index page — creation is modal-only from chickens page.

---

## Item 20d — Weight Checks UI → **CONFIRMED** (exists)

| Aspect | Evidence |
|---|---|
| Blade modal | `resources/views/chickens/partials/weight-check-modal.blade.php` — full form (90 lines) |
| Rendered in HTML | `grep 'Record Weight' /tmp/chickens_page.html` → 1 match |
| Route | `POST chickens/weight-check` → `ChickensController@storeWeightCheck` |
| Form fields | hen_id (hidden), check_date (required, date), weight_kg (required, decimal), notes (optional) |
| Controller validates | `hen_id => required|exists:hens,id`, `check_date => required|date`, `weight_kg => required|numeric|min:0|max:20` |
| Create logic | `WeightCheck::create([... recorded_by => auth()->id()])` |
| Redirect | `redirect()->back()->with('success', 'Weight recorded.')` |

**End-to-end test:**
```bash
curl -X POST http://localhost:8080/chickens/weight-check \
  -H "X-XSRF-TOKEN: ${token}" \
  -d "hen_id=1&check_date=2026-07-05&weight_kg=1.85&notes=Test"
```
→ 302 redirect to /chickens
→ `WeightCheck ID=1 weight=1.85kg date=2026-07-05` confirmed via tinker

**Gap:** Same as health events — no standalone list page, modal-only creation.

---

## Item 14 — Printable Cage Label → **CONFIRMED** (real data)

`GET /cages/1/print-label` → 200 OK. Structure of rendered output:

```
┌─ Letterhead ─────────────────────────┐
│  Farm branding, green border          │
│  Title: "CAGE-A — Cage Label"        │
├─ Header ─────────────────────────────┤
│  Stats row                           │
├─ Slot Grid ──────────────────────────┤
│  grid-template-columns: 36px 5×1fr   │
│  R1 header → Slot #1 [S] 4/4         │
│             Slot #2      4/4          │
│             Slot #3      0/4 (empty)  │
│  ...                                  │
├─ Breed Table ────────────────────────┤
│  Slot | Chicken ID | Breed | Age     │
│  1-1  | CHK-2026-00006 | ISA Brown   │
│  1-1  | CHK-2026-00007 | ISA Brown   │
│  ...                                  │
├─ Footer ─────────────────────────────┤
│  Signature block                     │
├─ Print Button ───────────────────────┤
│  window.print() + window.close()     │
└──────────────────────────────────────┘
```

**Key data markers found in rendered HTML:**
- `CAGE-A` — correct cage code
- `CHK-2026-00006`, `CHK-2026-00007` — real chicken IDs (not placeholders)
- `ISA Brown` — real breed data
- `4/4`, `0/4` — occupancy vs. max
- CSS classes `occupied`, `empty`, `sensor` — dynamically assigned
- Print works via `window.print()`; close via `window.close()`

No lorem ipsum or placeholder content. All data pulled live from DB via the controller.

---

## Item 6 — Admin Confirm-Delete Route → **CONFIRMED** (orphaned, 200 OK)

`GET /cages/1/confirm-delete` → 200 OK. Route is admin-guarded (`routes/web.php:65` inside `middleware('admin')` group).

**Rendered content:**
- Title: `<h1>Delete CAGE-A?</h1>`
- Stats: Slots, sensor-equipped slots, hen records, production logs, environmental logs, feed consumption logs, mortality records, alerts (all live counts from DB)
- Cancel link → `route('cages.index')`
- Delete button → `DELETE /cages/{cage}/force` → `CageController::forceDestroy()`
- `forceDestroy()` does: `$cage->cageSlots()->delete(); $cage->delete();` — hard delete, no salvage options

**UI orphanage confirmed:** `grep -c 'confirm-delete' resources/views/cages/index.blade.php` = 0 — no link exists in the UI to reach this page.

**Relation to in-page modal:**
| Aspect | In-page modal (`destroy()`) | Confirm-delete page (`forceDestroy()`) |
|---|---|---|
| Access | Trash icon per cage card | No link exists |
| Hen handling | Move to unplaced OR delete | Always deleted |
| Sensor handling | Return to inventory OR keep | Always returned to spare? (not handled) |
| Detail level | Basic summary | Full breakdown: 8 record types counted |
| Auth | Admin-only | Admin-only |
| Route | `DELETE /cages/{cage}` | `DELETE /cages/{cage}/force` |

**Recommendation:** Either (a) remove the route and view if hard-delete is not needed (the in-page modal + `destroy()` already handles all delete operations with salvage options), or (b) add a "Delete permanently (no salvage)" link in the delete modal for admin users.

---

## Manual-Check Instructions (3 items requiring a browser)

### Item 2b — Modal auto-reopen on resize failure
1. Open `http://localhost:8080/cages` in a browser as admin
2. Click **Edit** on any cage card to open the edit modal
3. Change rows or slots_per_row to a smaller number (e.g., reduce CAGE-A from 3×5 to 2×4)
4. Submit — expect an error flash and the modal should auto-reopen with the same cage loaded
5. If the modal does NOT reopen, the `$editCage` bug at `index.blade.php:1330` is confirmed visually. The error `$errors->first('resize')` would still appear, but the user would have to manually click Edit again.

### Item 5 — Responsive grid full-row spanning
1. Open `http://localhost:8080/cages` in a browser
2. Resize the viewport from 1920px down to 360px width
3. Watch the cage card grid: cards should wrap proportionally but no cage ever stretches to fill an entire row
4. At very narrow widths, a single cage card may take a full row — verify the `min-width` formula at `index.blade.php:158` (`max(240, slots_per_row * 30 + 40)`) gives reasonable results
5. Report whether the layout breaks or looks acceptable at mobile widths

### Item 15 — Cage orientation toggle (canvas only, not card list)
1. Open `http://localhost:8080/cages` in a browser
2. Look at the **farm layout canvas** (drag-and-drop grid at top of page) — find the "Horizontal flow" / "Vertical flow" buttons
3. Click each — the canvas grid should reflow its cells. The orientation preference should persist (localStorage).
4. Now look at the **cage card list** below the canvas (the flex-wrap container) — there should be NO toggle for card flow orientation
5. The audit finding is that orientation toggle exists for the canvas but not for the cage cards, meaning the cards always flow left-to-right in row-based wrapping
6. Report back: do you see the toggle buttons, and does the card list indeed lack an orientation control?

---

## Summary Table

| # | Finding | Status | Evidence Source |
|---|---|---|---|
| 2 | Sensor uncheck works | **CONFIRMED** | curl PUT → HW status changed active→spare→active |
| 2b | Modal auto-reopen on resize failure | **Needs manual check** | `$editCage` never passed to view (code inspection) |
| 5 | Grid full-row spanning | **Needs manual check** | Visual layout test |
| 6 | Confirm-delete route orphaned | **CONFIRMED** | Route returns 200; zero links from UI |
| 14 | Printable label has real data | **CONFIRMED** | Rendered HTML shows CAGE-A, real chicken IDs, breeds |
| 15 | Orientation toggle (canvas only) | **Needs manual check** | Visual/interactive test |
| 18 | No "wing" terminology | **CONFIRMED** | Rendered HTML has zero legacy location terms |
| 20c | Health Events UI exists | **CONFIRMED** | Blade modal + route + end-to-end creation test |
| 20d | Weight Checks UI exists | **CONFIRMED** | Blade modal + route + end-to-end creation test |
