# Cage & Chicken Features — Wishlist Audit

**Date:** 2026-07-05
**Method:** Codebase inspection (controllers, views, models, migrations, routes, JS). Every finding cites file:line.

---

## CAGE FEATURES (Items 1–15)

### 1. Cage/Slot Reorder Mechanism

**PARTIAL** — A farm-layout drag-and-drop system exists for cages, but no mechanism exists for reordering slots within a cage or renumbering slots.

| What | Status | Evidence |
|---|---|---|
| Cage drag-and-drop on visual grid | EXISTS | `cages/index.blade.php:64-127` farm grid canvas, JS `handleDrop()` at line 995 with swap-on-occupied-cell logic (line 1010-1027) |
| Cage batch position update | EXISTS | `routes/web.php:61` → `CageController::batchUpdatePosition()` (lines 324-384) |
| Slot reordering/renumbering | MISSING | No code exists for reordering or renumbering slots within a cage |
| Cage sorting | EXISTS | `CageController::index()` line 30: `->orderBy('cage_code')` |

**Gap:** Cages can be visually positioned on a grid via drag-and-drop. Slots are static (numbered by creation order, no reorder/swap mechanism exists).

---

### 2. Sensor Checkbox in Cage Edit — Uncheck Bug

**EXISTS (appears correct)** — The hidden-input + checkbox pattern should make unchecking work. Found a different bug in the modal auto-reopen on resize failure.

**Checkbox HTML** (`cages/index.blade.php:768-770`):
```html
<input type="hidden" name="slots[{slot.id}][has_sensor]" value="0">
<input type="checkbox" class="ir-sensor-box" name="slots[{slot.id}][has_sensor]" value="1" {checked}>
```
Standard Laravel checkbox trick: hidden `0` sent when unchecked; checkbox `1` overwrites when checked. **This pattern is correct.**

**Controller handler** (`CageController.php:110-145`): Reads `$slotData['has_sensor']` with `?? false`, then:
- Checked + no existing sensor → assigns from spare (`$slotsNeedingSensor[]`)
- Unchecked + existing sensor → sets status to `'spare'`, clears `cage_id`/`cage_slot_id`

**Root cause of potential "uncheck doesn't work":** If the form action URL is stale or the `#editCageForm` has no `action` attribute set, the JS on line 716 (`document.getElementById('editCageForm').action = '/cages/' + id;`) must execute. If `openEditModal()` is called before `id` is populated, the form submits to the current page URL instead of `PUT /cages/{id}`.

**Actual bug found:** The edit modal autio-reopen on resize failure is broken — `cages/index.blade.php:1330` checks `isset($editCage)` but `$editCage` is never passed to the view by `CageController::index()` (line 41: `compact(...)` does not include `$editCage`). The modal never auto-opens after a failed resize, so users can't see the error feedback. This may mask the sensor uncheck issue.

---

### 3. Slot Click → Detail View

**EXISTS** — Clicking a slot expands a detail panel with occupancy count, hen list, and today's egg status.

| What | Where | Evidence |
|---|---|---|
| Slot click handler | `cages/index.blade.php:217-233` | `<button onclick="expandSlot(...)">` per slot mini-box |
| Detail panel HTML | `cages/index.blade.php:235-265` | `#slotExpandPanel-{cageId}` — shows hen list, move/remove buttons, egg status |
| AJAX endpoint | `routes/web.php:51` → `CageController::hensJson()` | Returns JSON with slot hens + today's production log |
| Per-slot egg status | `CageController.php:399-452` | Queries `ProductionLog::where('cage_slot_id', $slot->id)->whereDate('log_date', today())` |
| Today's egg_status | Computed at line 428+ | Returns `"All laid — N egg(s)"` or `"No eggs logged"` etc. |

**"Has laid today" concept:** PARTIAL — Exists at the **slot level** (aggregate eggs per slot per day via `production_logs` table). No **per-hen** egg tracking exists. The unique constraint on `(cage_slot_id, log_date)` confirms one log per slot per day. Per-hen daily egg tracking is not in the data model.

---

### 4. Bulk Add Entry Points

**EXISTS** — Bulk Add is accessible from **4 locations**, including the section header button (not only per-cage).

| Entry Point | File | Line |
|---|---|---|
| Cage section header button | `cages/index.blade.php` | 11 |
| Per-cage card add button | `cages/index.blade.php` | 177 |
| Chickens unplaced list "Place all" | `chickens/_unplaced-list.blade.php` | 11 |
| Chickens per-hen "Place" link | `chickens/_unplaced-list.blade.php` | 50 |

Routes: `GET /cages/bulk-add` → `CageController::bulkAdd()` (line 52), `POST /cages/bulk-add` → `CageController::storeBulkAdd()` (line 53).

The section header button is `<a href="{{ route('cages.bulk-add') }}">Bulk Add Chickens</a>` — it IS present in the header, contrary to the original audit's claim.

---

### 5. Flexible/Responsive Grid Layout

**PARTIAL** — Cage cards have variable minimum widths based on slot count but do NOT have full-row spanning logic.

| Aspect | Code | Evidence |
|---|---|---|
| Container | `cages/index.blade.php:149` | `flex flex-wrap gap-4` |
| Card flex | `cages/index.blade.php:162` | `flex: 1 1 {{ $cardMinWidth }}px` |
| Card min-width | computed at line 158 | `max(240, $cage->slots_per_row * 30 + 40)` |
| Card max-width | line 162 | `max-width: 100%` |
| Full-row spanning | MISSING | Cards wrap as flex items but no cage ever occupies a full row alone; leftover space on the last row is not handled |

**Farm layout grid full-row logic** exists only for the drag-and-drop canvas (`applyRowSpanning()` at line 1201-1229), not for the cage card list.

**Gap:** A cage with many slots gets wider (proportional to `slots_per_row`), but a cage alone on a row doesn't span to fill the row width. The flex container distributes leftover space proportionally via `flex-grow`.

---

### 6. Delete Cage Flow

**EXISTS** — The primary flow is an **in-page modal** (`#deleteCageModal`). A separate confirm-delete **page** exists but is not reachable from the UI.

| Path | Evidence |
|---|---|
| Main flow (modal) | `cages/index.blade.php:186-189` trash button → `openDeleteModal()` (line 1264) → AJAX fetch `/cages/{id}/delete-info` → modal HTML (line 460-520) → `confirmCageDelete()` (line 1294) → `DELETE /cages/{id}` |
| Controller `destroy()` | `CageController.php:222-252` | Returns JSON. Handles `hens_action` (move/delete) and `return_sensors` (bool). |
| Admin-only page | `routes/web.php:65-66` GET `/cages/{cage}/confirm-delete` → `deleteConfirm()` → `confirm-delete.blade.php`. No link exists in the UI to reach this page. |

---

### 7. Granular Delete Options

**PARTIAL** — Hens and sensors have granular options. Historical records (logs) have none.

| What | Options | Evidence |
|---|---|---|
| Hens | `move` (to unplaced) / `delete` (permanent) — radio buttons | `cages/index.blade.php:474-483` |
| Sensors | `return_sensors` checkbox (default checked) | `cages/index.blade.php:487-493` |
| Historical records | **Always deleted**, no options | `cages/index.blade.php:495-504` shows warning text but no checkboxes |
| Production logs | Cascade-deleted with cage | FK `cage_slot_id → cage_slots.id CASCADE` |
| Environmental logs | Cascade-deleted with cage | FK `cage_id → cages.id CASCADE` |
| Feed logs | Cascade-deleted with cage | FK `cage_id → cages.id CASCADE` |
| Mortality logs | Cascade-deleted with cage | FK `cage_id → cages.id CASCADE` |

---

### 8. Sensor Count in Cage Edit

**EXISTS** — Not a hardcoded `HardwareItem::count()`, but a **live inventory spare count** fetched via AJAX and displayed with dynamic remaining-capacity tracking.

| What | Where | Evidence |
|---|---|---|
| Spare IR count | `CageController::sensorInfo()` line 282 | `HardwareItem::where('device_type', 'IR_breakbeam')->where('status', 'spare')->count()` |
| JS availability tracking | `cages/index.blade.php:798-815` | `updateIrAvailability()` computes remaining = `spareIR - newlyChecked` |
| DHT22 availability | `cages/index.blade.php:817-846` | Same pattern for DHT22 sensors |
| Blocking over-assignment | `cages/index.blade.php:808-813` | Disables unchecked boxes when remaining ≤ 0 |

**No hardcoded cap** — the edit modal queries live HardwareItem inventory and prevents assigning more sensors than available spares.

---

### 9. Breed Stock in Bulk Add

**MISSING** — No breed-level availability or stock count exists in the bulk-add flow.

| What | Current behavior | Evidence |
|---|---|---|
| Breed data sent to view | Only distinct breed **names** (for filter dropdown) | `CageController::bulkAdd()` line 573: `$unplacedHens->pluck('breed')->unique()->sort()` |
| Breed count | MISSING — no per-breed hen count computed | Not in `compact()` at line 582 |
| Breed-level validation | MISSING — no breed quota or over-assignment check | `storeBulkAdd()` lines 585-702 checks only slot capacity, not breed |
| Server-side breed check | MISSING | Validation rules at line 587-593 don't reference breed |

**Gap:** The bulk-add form has a breed filter dropdown (to show/hide hens of a specific breed) but does not show "15 unplaced ISA Browns" or any count per breed. The controller does not prevent placing more of one breed than available.

---

### 10. Sensor Type Schema

**EXISTS** — Generic `hardware_items` table with a `device_type` ENUM column.

| Column | Type | Values |
|---|---|---|
| `device_type` | MySQL ENUM | `'DHT22', 'IR_breakbeam', 'relay', 'other'` |
| `status` | MySQL ENUM | `'active', 'faulty', 'removed', 'spare'` |

Migration: `2026_07_01_020000_create_hardware_items_table.php`. Model: `HardwareItem.php` with constants at lines 28-30.

**Backfill migration** at `2026_07_01_020001_backfill_hardware_items_from_cage_slots.php` migrates old `cage_slots.has_sensor` data to `HardwareItem` records with `device_type = 'IR_breakbeam'`.

---

### 11. Auto-Generated Sensor Device IDs

**EXISTS** — Via `CageController::nextDeviceId()` at line 207-220.

```php
private function nextDeviceId(string $deviceType): string
{
    $prefix = $deviceType === 'IR_breakbeam' ? 'IRBBS' : $deviceType;
    $max = HardwareItem::where('serial_number', 'like', $prefix.'\_%')
        ->pluck('serial_number')
        ->reduce(fn($carry, $serial) => /* parse suffix number, find max */, 0);
    return $prefix.'_'.($max + 1);
}
```

Generates `IRBBS_1`, `IRBBS_2`, ... and `DHT22_1`, `DHT22_2`, ... **globally sequential per type** across all cages. Called from `assignSpareSensor()` (line 182) inside `DB::transaction()` with `lockForUpdate()`.

---

### 12. Sensor Counts from Hardware Inventory

**EXISTS** — Multiple code paths query `HardwareItem` for spare counts:

| Context | Method | Line | Query |
|---|---|---|---|
| Cage index page | `CageController::index()` | 35-38 | `HardwareItem::where('status', 'spare')->groupBy('device_type')` |
| Cage edit modal (AJAX) | `CageController::sensorInfo()` | 271-286 | Per-type spare counts, per-cage DHT22 list |
| Cage edit save | `CageController::update()` | 134-163 | Spare IR + DHT counts before assigning/returning |
| Delete info | `CageController::deleteInfo()` | 261-263 | Active+faulty sensor count for the cage |
| Delete action | `CageController::destroy()` | 240-244 | Returns sensors to spare inventory |

All spare counts are live queries — no hardcoded values.

---

### 13. Bulk Add Data Source

**EXISTS** — `CageController::bulkAdd()` (lines 555-583) runs 3 queries:

1. `Cage::with('cageSlots')->where('is_active', 1)->orderBy('cage_code')` — all active cages
2. `Hen::whereNull('cage_slot_id')->where('is_active', 1)->orderBy('id')` — all unplaced hens
3. `$unplacedHens->pluck('breed')->unique()->sort()` — distinct breed names only

No breed counts, no sensor inventory, no capacity-per-breed data. The form lets the user filter by breed but does not show availability counts per breed.

---

### 14. Printable Cage Label

**EXISTS** — Two views, one route, accessible from cage index.

| View | File | Lines | Description |
|---|---|---|---|
| Full label | `cages/print-label.blade.php` | 147 | Letterhead, slot grid by row, breed table, footer, `window.print()` |
| Compact label | `cages/label.blade.php` | 56 | Large-format (96px cage code), colored border — **not routed** (legacy?) |

Route: `GET /cages/{cage}/print-label` → `CageController::printLabel()` (routes/web.php:54). Entry point: printer icon button at `cages/index.blade.php:173`.

---

### 15. Cage Orientation Toggle

**PARTIAL** — A horizontal/vertical toggle exists for the **farm layout canvas** (drag-and-drop grid at page top). The **cage card list** (below the canvas) has NO toggle.

| What | Exists? | Evidence |
|---|---|---|
| Canvas toggle UI | EXISTS | `cages/index.blade.php:37-47` — two buttons labeled "Horizontal flow" / "Vertical flow" |
| Canvas JS | EXISTS | `setCanvasFlow()` + `applyCanvasFlow()` at lines 1231-1259, persisted in `localStorage` |
| Canvas layout | EXISTS | Horizontal: `gridAutoFlow: row` + row-spanning. Vertical: `gridAutoFlow: column` + fixed grid rows |
| Cage cards orientation toggle | MISSING | Card container at line 149 uses `flex flex-wrap gap-4` — no toggle mechanism exists |

---

## CHICKEN FEATURES (Items 16–20)

### 16. Chicken Registration Form

**PARTIAL** — Form exists, schema is comprehensive, but `sex` field is not in the form (hardcoded to `'hen'` in controller).

**Form fields** (`chickens/partials/register-modal.blade.php`):

| Requested Field | Form Present? | DB Column | Form Label | Notes |
|---|---|---|---|---|
| Age at acquisition | YES | `age_at_placement_weeks` | "Age at Acquisition (weeks)" | Input, required, min 0 |
| Breed | YES | `breed` | "Breed" | Select, 5 options, required |
| Source / Breeder | YES | `source` | "Source / Origin" | Text input, optional |
| Date acquired / hatched | YES | `date_acquired` | "Date Acquired" | Date input, required, default today |
| Sex | **MISSING from form** | `sex` (enum) | — | Controller hardcodes `'sex' => 'hen'` at `ChickensController.php:146` |
| Initial health status | YES | `initial_health_status` | "Initial Health Status" | Text input, optional |
| Notes | YES | `notes` | "Notes" | Textarea, optional, max 1000 |
| Quantity | YES | (none — creates N hens) | "Number of hens to register" | Input 1-100, required |

**Missing from form but in DB:**
- `tag_code` (string 50, unique, nullable) — not captured during registration
- `chicken_id` — auto-generated by `Hen::boot()`
- `placement_date` — set later when placed in slot
- `flock_age_weeks` — set to same value as `age_at_placement_weeks` at creation
- `deactivation_cause` — set during mortality/culling/removal paths only

---

### 17. Culling Section

**EXISTS** — Dedicated culling tab in the chickens page, with required reason field.

| Aspect | Evidence |
|---|---|
| UI location | `chickens/index.blade.php:205-212` — tab panel with lazy-loaded Turbo Frame |
| Culling form (modal) | `chickens/partials/cull-modal.blade.php:24-33` — fields: hen_id (hidden), cull_date (required), reason (required), notes (optional) |
| Reason values | `low_production, illness, aggression, age, other` |
| Validation | `ChickensController.php:214-221` — `reason => 'required|in:low_production,illness,aggression,age,other'` |
| DB table | `2026_07_03_000005_create_culling_logs_table.php` |
| Records listing | `ChickensController::cullingRecords()` → `chickens/_culling-records.blade.php` (lazy-loaded frame) |

Culling is a separate tab with its own form, input fields, and records table — not folded into a generic remove flow.

---

### 18. "Wing" / Old Terminology

**NO LEGACY TERMINOLOGY FOUND** — A case-insensitive search for `\bwing\b`, `row_col`, and `section` (as farm terminology) across all PHP, Blade, JSON, and MD files returned **zero matches**.

The cage → cage_slot naming convention appears to have been fully established from the start. No old terminology (wing, row_col) exists anywhere in the current codebase. The only `section` occurrences are standard Laravel `@section()` Blade directives and a JS variable `stagingSection` (a DOM element, not farm terminology).

---

### 19. Chicken ID Auto-Generation

**EXISTS** — Format `CHK-{YYYY}-{NNNNN}` auto-generated by `Hen::boot()` (Hen.php:25-48).

```php
$hen->chicken_id = DB::transaction(function () {
    $year = now()->format('Y');
    $prefix = "CHK-{$year}-%";
    $last = Hen::where('chicken_id', 'like', "CHK-{$year}-%")
        ->lockForUpdate()->orderBy('chicken_id', 'desc')->value('chicken_id');
    $next = $last ? (int) substr($last, -5) + 1 : 1;
    return sprintf("CHK-%s-%05d", $year, $next);
});
```

**Year rollover:** Correct by design — no explicit rollover code needed. When the year changes from 2025 to 2026, the LIKE query `CHK-2026-%` matches zero rows, `$last` is `null`, `$next` is `1`, and the first ID is `CHK-2026-00001`. No edge-case handling or tests exist.

---

### 20. Full Lifecycle Data Model

#### 20a. Cage Assignment (reason field)

**EXISTS** — `cage_transfers` table has `reason` (string 100, nullable) and `notes` (text, nullable).

| Column | Migration | Model |
|---|---|---|
| `reason` | `2026_07_03_000002_create_cage_transfers_table.php` | `CageTransfer.php:13` in `$fillable` |

The reason field is populated during initial placement (`placeHenInSlot()` sets `reason = 'Initial placement'` or `'Initial placement (auto-distribute)'`) and during moves (`move()` sets `reason = $transferReason` from user input).

#### 20b. Cage Transfers

**EXISTS** — Same table as 20a. Full schema:

| Column | Type | Constraints |
|---|---|---|
| `hen_id` | FK→hens | Cascade on delete |
| `from_cage_slot_id` | FK→cage_slots, nullable | Null on delete |
| `to_cage_slot_id` | FK→cage_slots | Cascade on delete |
| `transfer_date` | date | NOT NULL |
| `reason` | string(100) | nullable |
| `notes` | text | nullable |
| `recorded_by` | FK→users, nullable | Null on delete |

#### 20c. Health Events

**EXISTS** — `health_events` table with columns:

| Column | Type | Constraints |
|---|---|---|
| `hen_id` | FK→hens | Cascade on delete |
| `event_date` | date | NOT NULL |
| `event_type` | MySQL ENUM | `'sick', 'treated', 'recovered'` |
| `description` | string(255) | nullable |
| `notes` | text | nullable |
| `recorded_by` | FK→users, nullable | Null on delete |

Migration: `2026_07_03_000003_create_health_events_table.php`. Model: `HealthEvent.php`.

#### 20d. Weight Checks

**EXISTS** — `weight_checks` table with columns:

| Column | Type | Constraints |
|---|---|---|
| `hen_id` | FK→hens | Cascade on delete |
| `check_date` | date | NOT NULL |
| `weight_kg` | decimal(5,2) | NOT NULL |
| `notes` | text | nullable |
| `recorded_by` | FK→users, nullable | Null on delete |

Migration: `2026_07_03_000004_create_weight_checks_table.php`. Model: `WeightCheck.php`.

#### 20e. Mortality Log

**EXISTS** — `mortality_logs` table with all requested fields:

| Requested Field | DB Column | Present? |
|---|---|---|
| Date of death | `log_date` (date, NOT NULL) | YES |
| Cause | `reason` (enum: Disease, Heat Stress, Injury, Predator, Unknown, Other) | YES |
| Cage at time of death | `cage_id` (FK→cages) | YES |
| Notes | `notes` (text, nullable) | YES |
| Per-hen pivot | `mortality_log_hens` with `cage_slot_id` | YES |

Migration: `2026_01_01_000012_create_mortality_logs_table.php`. Pivot: `2026_07_01_012854_create_mortality_log_hens_table.php`.

#### 20f. Sold/Removed

**EXISTS** — `removals` table with all requested fields:

| Requested Field | DB Column | Type | Present? |
|---|---|---|---|
| Date | `removal_date` | date, NOT NULL | YES |
| Reason | `reason` | string(100), NOT NULL | YES |
| Destination | `destination` | string(200), nullable | YES |
| Notes | `notes` | text, nullable | YES |

Migration: `2026_07_03_000006_create_removals_table.php`. Model: `Removal.php:14` `$fillable` includes `destination`.

---

## Summary Tables

### Cage Features (1–15)

| # | Feature | Status | Key Gap |
|---|---|---|---|
| 1 | Cage/slot reorder | PARTIAL | Cage drag-and-drop exists but slot reorder/renumber is missing |
| 2 | Sensor checkbox uncheck | EXISTS (appears correct) | Modal auto-reopen on resize failure is broken — unrelated bug at `index.blade.php:1330` |
| 3 | Slot click → detail | EXISTS | Per-slot egg status exists. Per-hen "laid today" is MISSING from data model |
| 4 | Bulk Add entry points | EXISTS | 4 entry points including section header button |
| 5 | Responsive grid layout | PARTIAL | Cards have variable min-width but no full-row spanning |
| 6 | Delete flow | EXISTS | In-page modal (primary), separate page exists but unreachable |
| 7 | Granular delete options | PARTIAL | Hens + sensors have options; historical logs always deleted |
| 8 | Sensor count in edit | EXISTS | Live spare-count via AJAX, blocks over-assignment |
| 9 | Breed stock in Bulk Add | MISSING | No breed-level availability count or validation |
| 10 | Sensor type schema | EXISTS | `hardware_items` table with `device_type` ENUM (DHT22, IR_breakbeam, relay, other) |
| 11 | Auto sensor Device IDs | EXISTS | `nextDeviceId()` generates `IRBBS_N` / `DHT22_N` globally |
| 12 | Sensor counts from inventory | EXISTS | All sensor UI uses live `HardwareItem` spare counts |
| 13 | Bulk Add data source | EXISTS | Queries unplaced hens + active cages + breed names |
| 14 | Printable cage label | EXISTS | Two views, route, entry point in cage index |
| 15 | Cage orientation toggle | PARTIAL | Canvas toggle exists; cage card list has no toggle |

### Chicken Features (16–20)

| # | Feature | Status | Key Gap |
|---|---|---|---|
| 16 | Chicken registration form | PARTIAL | `sex` field missing from form (hardcoded to `'hen'` in controller) |
| 17 | Culling section | EXISTS | Dedicated tab, reason required, separate form + records |
| 18 | "Wing" old terminology | MISSING (good) | No legacy terminology found anywhere in codebase |
| 19 | Chicken ID auto-generation | EXISTS | `CHK-YYYY-NNNNN` with year rollover by design |
| 20a | Cage Assignment (reason) | EXISTS | `cage_transfers.reason` (string 100, nullable) |
| 20b | Cage Transfers | EXISTS | Full bidirectional tracking with dates, reasons, notes |
| 20c | Health Events | EXISTS | `health_events` with `event_type` ENUM (sick/treated/recovered) |
| 20d | Weight Checks | EXISTS | `weight_checks` with decimal weight + date |
| 20e | Mortality Log | EXISTS | All fields present (date, cause, cage, notes) |
| 20f | Sold/Removed | EXISTS | All fields present including `destination` |
