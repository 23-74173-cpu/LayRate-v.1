# LayRate Changelog Audit — June 29 to July 2, 2026

**Audit Period:** June 29, 2026 – July 2, 2026  
**Total Commits:** 15  
**Files Changed:** 119  
**Lines Added:** 11,777  
**Lines Removed:** 1,296

---

## Summary Table

| Feature Area | Files Changed | Status |
|--------------|---------------|--------|
| Egg Logging (edit capability) | 2 | ✅ Complete |
| Feed (delete for batches/consumption) | 2 | ✅ Complete |
| Mortality (edit + cascade logic) | 4 | ✅ Complete |
| Egg Stocks (edit capability) | 2 | ✅ Complete |
| Hardware Inventory (new feature) | 12 | ✅ Complete |
| Design System / UI Fixes | 45+ | ✅ Complete |
| Schema Migration (slot-grid) | 25+ | ✅ Complete |
| Chickens Inventory (new feature) | 8 | ✅ Complete |
| Egg Management Module (new feature) | 15+ | ✅ Complete |
| Turbo Drive Fixes | 10+ | ✅ Complete |

---

## 1. Egg Logging — Edit Capability

**Commit:** `f49524a` (July 1, 2026)

### Files Modified
- `app/Http/Controllers/EggLoggingController.php`
- `resources/views/egg-logging.blade.php`

### Changes
- **Added `update()` method** to `EggLoggingController`
  - Validates: `log_date` (required, date), `egg_count` (required, integer, min:0), `hen_count` (required, integer, min:1), `notes` (nullable, string)
  - Recalculates HDEP: `round((egg_count / hen_count) * 100, 2)`
  - Updates existing `ProductionLog` record
  - Redirects with success message

### Implementation Details
- Edit form added to recent logs table (inline edit button per row)
- HDEP auto-recalculated on update
- No ownership validation (any authenticated user can edit any log)
- No audit trail for edits (original values not preserved)

---

## 2. Feed — Delete for Batches & Consumption Logs

**Commit:** `f49524a` (July 1, 2026)

### Files Modified
- `app/Http/Controllers/FeedController.php`
- `resources/views/feed.blade.php`

### Changes

#### Feed Batch Deletion
- **Added `checkDeleteBatch(FeedBatch $feedBatch)` method**
  - Returns JSON: `{ can_delete: bool, count: int }`
  - Checks if batch has associated `FeedConsumptionLog` records
  - Used for pre-delete confirmation in UI

- **Added `destroyBatch(FeedBatch $feedBatch)` method**
  - Blocks deletion if batch has consumption logs (returns error with count)
  - Deletes batch if no references exist
  - Redirects with success/error message

#### Feed Consumption Log Deletion
- **Added `destroyConsumption(FeedConsumptionLog $feedConsumptionLog)` method**
  - Deletes individual consumption log record
  - No cascade logic (batch remains intact)
  - Redirects with success message

### Implementation Details
- Cascade protection: cannot delete batch with existing consumption logs
- User must manually delete consumption logs before deleting batch
- No soft delete (permanent removal)
- No admin-only restriction (any authenticated user can delete)

---

## 3. Mortality — Edit Capability + Cascade Logic

**Commit:** `f49524a` (July 1, 2026)

### Files Modified
- `app/Http/Controllers/MortalityController.php`
- `app/Models/MortalityLogHen.php` (new)
- `database/migrations/2026_07_01_012854_create_mortality_log_hens_table.php` (new)
- `resources/views/mortality.blade.php`

### Changes

#### New Pivot Table: `mortality_log_hens`
- Links `MortalityLog` to individual `Hen` records
- Fields: `mortality_log_id`, `hen_id`, `cage_slot_id`
- Enables tracking which specific hens were deactivated per mortality event

#### Edit Capability with Cascade Logic
- **Added `update()` method** with complex cascade handling:

**Validation:**
- `log_date` (required, date)
- `count` (required, integer, min:1)
- `reason` (required, enum: Disease/Heat Stress/Injury/Predator/Unknown/Other)
- `notes` (nullable, string, max:1000)

**Increase Count (newCount > oldCount):**
1. Pre-flight check: ensures enough active hens exist in cage
2. Queries active hens, excludes already-linked hens from this mortality log
3. Sorts by `cage_slot_id`, then `placement_date` (oldest first)
4. Deactivates hens: sets `is_active = false`
5. Creates `MortalityLogHen` pivot records for each deactivated hen
6. Decrements `CageSlot.current_occupancy` for affected slots
7. Returns validation error if insufficient active hens

**Decrease Count (newCount < oldCount):**
1. Retrieves most recent `MortalityLogHen` pivot records (by ID desc)
2. Reactivates hens: sets `is_active = true`
3. Increments `CageSlot.current_occupancy` for affected slots
4. Deletes pivot records
5. Logs warning if hen no longer exists (skips reactivation)

**Transaction Safety:**
- All updates wrapped in `DB::transaction()`
- Atomic: either all changes succeed or none apply

**Post-Update:**
- Calls `checkMortalitySpike()` to trigger alerts if mortality rate exceeds threshold

### Implementation Details
- Bidirectional cascade: can increase or decrease death count
- Hen selection deterministic: oldest hens in lowest slot numbers first
- Handles edge case: hen deleted between mortality log creation and edit
- Slot occupancy kept in sync automatically
- No audit trail for edits (original count/reason not preserved)

---

## 4. Egg Stocks — Edit Capability

**Commit:** `f49524a` (July 1, 2026)

### Files Modified
- `app/Http/Controllers/EggStockController.php`
- `resources/views/eggs/stocks.blade.php`

### Changes
- **Added `update()` method** to `EggStockController`
  - Validates: `egg_size` (required, enum: small/medium/large/jumbo), `count` (required, integer, min:1), `harvested_date` (required, date)
  - Updates existing `EggStockBatch` record
  - Redirects with success message

### Implementation Details
- Edit form added to stock batches table
- No cascade logic (batch is standalone record)
- No validation against source production log (if linked)
- Freshness status (`fresh`/`aging`/`old`) recalculated automatically via model accessor

---

## 5. Hardware Inventory — New Feature

**Commit:** `f49524a` (July 1, 2026)

### Files Created
- `app/Http/Controllers/HardwareItemController.php`
- `app/Http/Requests/StoreHardwareItemRequest.php`
- `app/Models/HardwareItem.php`
- `database/migrations/2026_07_01_020000_create_hardware_items_table.php`
- `database/migrations/2026_07_01_020001_backfill_hardware_items_from_cage_slots.php`
- `database/migrations/2026_07_01_020002_drop_has_sensor_from_cage_slots.php`
- `resources/views/hardware/index.blade.php`

### Schema: `hardware_items` Table
```sql
- id (bigint, PK)
- device_type (enum: DHT22, IR_breakbeam, relay, other)
- serial_number (varchar 100, unique)
- cage_id (FK → cages, nullable, cascade delete)
- cage_slot_id (FK → cage_slots, nullable, cascade delete)
- installation_date (date, nullable)
- status (enum: active, faulty, removed, spare, default: active)
- last_calibration_date (date, nullable)
- timestamps
```

### Model: `HardwareItem`
- **Fillable:** device_type, serial_number, cage_id, cage_slot_id, installation_date, status, last_calibration_date
- **Casts:** installation_date → date, last_calibration_date → date
- **Constants:** `DEVICE_TYPES` (4 types), `STATUSES` (4 statuses)
- **Relationships:** `cage()` → belongsTo, `cageSlot()` → belongsTo

### Controller: `HardwareItemController`

#### `index()`
- Eager-loads `cage`, `cageSlot.cage`
- Orders by status (active first), then serial_number
- Computes summary counts: breakbeam, DHT22, active, faulty
- Passes cages and cage_slots for form dropdowns

#### `store(StoreHardwareItemRequest $request)`
- Validates via form request
- If status = 'spare', nullifies cage_id and cage_slot_id
- Creates hardware item
- Redirects with success message

#### `update(StoreHardwareItemRequest $request, HardwareItem $hardwareItem)`
- Same validation and spare logic as store
- Updates existing item
- Redirects with success message

#### `destroy(HardwareItem $hardwareItem)`
- Deletes hardware item
- Redirects with success message

### Validation: `StoreHardwareItemRequest`

**Rules:**
- `device_type`: required, must be in DEVICE_TYPES
- `serial_number`: required, string, max:100, unique (ignores current item on update)
- `cage_id`: nullable, exists in cages
- `cage_slot_id`: nullable, exists in cage_slots
- `installation_date`: nullable, date
- `status`: required, must be in STATUSES
- `last_calibration_date`: nullable, date

**Custom Validation Logic (withValidator):**
- **Spare devices:** must not have cage_id or cage_slot_id
- **IR_breakbeam sensors:** must have cage_slot_id, must NOT have cage_id
- **DHT22/relay devices:** must have cage_id, must NOT have cage_slot_id
- **Other devices:** no enforcement (both nullable)

### Migration: Backfill from `cage_slots`
- Reads existing `cage_slots` with `has_sensor = true`
- Creates `HardwareItem` records with device_type = 'IR_breakbeam'
- Assigns to cage_slot_id
- Generates serial_number: `SN-CAGE{id}-SLOT{slot_number}`

### Migration: Drop `has_sensor` from `cage_slots`
- Removes `has_sensor` boolean column from `cage_slots` table
- Sensor tracking now fully delegated to `hardware_items`

### View: `hardware/index.blade.php`
- Summary cards: active devices, faulty devices, IR breakbeam count, DHT22 count
- Add/Edit modal with device type selector, serial number, status, assignment dropdowns
- Conditional dropdowns: IR breakbeam shows slot picker, DHT22/relay shows cage picker
- Inventory table with edit/delete actions
- Spare devices shown separately (no assignment)

### Implementation Details
- Type-aware assignment logic enforced in validation
- Cascade delete: deleting cage/slot deletes associated hardware items
- Spare devices explicitly unassigned (cage_id = null, cage_slot_id = null)
- Serial number uniqueness enforced across all items
- No audit trail for status changes

---

## 6. Design System / UI Fixes

**Commits:** `b52e77c`, `f8aaeb3`, `054b211`, `39c68dc`, `f49524a` (July 1, 2026)

### Files Modified
- `resources/css/app.css`
- `public/css/tailwind.css` (rebuilt)
- `resources/views/layouts/app.blade.php`
- `resources/views/components/*.blade.php` (8 new components)
- Multiple view files (dashboard, cages, egg-logging, feed, mortality, etc.)

### Design System Tokens Added to `app.css`

**Colors:**
- Surface: `#ffffff`
- Secondary: `#213183`
- On-primary: `#ffffff`
- Sticker palette (decorative): sky, purple, pink, orange, teal, green, brown

**Spacing Scale:**
- `--space-xxs: 4px` through `--space-xxl: 32px` (7 steps)

**Border Radius:**
- `--radius-xs: 4px` through `--radius-xl: 16px` (5 steps)

**Elevation (Shadows):**
- `--shadow-soft`: subtle multi-layer shadow
- `--shadow-elevated`: stronger shadow with extended blur

**Typography:**
- `--text-display-1: 64px` with 1.0 line height
- Inter font set as default sans-serif

### New Blade Components (8 total)
1. `<x-page-header>` — contextual page title with breadcrumbs
2. `<x-underline-tabs>` — Notion-style tab bar with colored active indicators
3. `<x-cage-color>` — applies cage identity color to elements
4. `<x-status-badge>` — colored badge for status display (ok/watch/alert)
5. `<x-alert-banner>` — full-width alert banner for dashboard
6. `<x-alerts-modal>` — session-aware modal for displaying new alerts
7. `<x-confirm-modal>` — reusable confirmation dialog for destructive actions
8. `<x-input-error>` — inline error message display
9. `<x-paginator>` — custom pagination component
10. `<x-slot-card>` — compact cage slot display card

### Modal Overflow Fix
**Problem:** Global modals clipped by `--spacing-md` theme collision  
**Solution:** Added `@source "../views/"` to Tailwind config to ensure all utility classes are scanned  
**Result:** Modals now render correctly without clipping

### Accessibility Improvements
- **Focus-visible styles:** 2px `#0075de` outline with 2px offset on all interactive elements
- **Aria-labels:** added to all icon-only Lucide buttons across views (sidebar, mortality, chickens, egg-logging, cages/bulk-add, move/remove modals)
- **Keyboard navigation:** Enter/Space selection on slot cards
- **Chart.js defaults:** colorblind-safe palette (`window.CAGE_COLORS`), 12px legend labels, Inter font, subtle grid lines, rounded bars

### Favicons
- Added egg favicon: `favicon.ico`, `favicon-16x16.png`, `favicon-32x32.png`, `apple-touch-icon.png`
- Design: cream egg `#f6f5f4` with navy `#102A4C` stroke, flat Notion aesthetic
- Added `<link>` tags to `layouts/app.blade.php` and `auth/login.blade.php`

### Dashboard Redesign
- Contextual header with breadcrumbs
- Full-width alert banner (replaced inline banner)
- Unified 4-card KPI row: hens, HDEP, eggs, environment
- Compact 2×2 cage overview cards
- Feed/mortality cards with Notion-style flat surfaces and hairline borders
- Removed "Live Readings" section (merged into KPI card)
- Replaced "auto-counted via IR sensor" with "manual entry · logged by operator"

### Cage Management Redesign
- Notion-style underline tab bar: [All] [CAGE-A..D] with colored active indicators
- Compact mini-grid view (~200px height per cage, replaced tall cards)
- Progressive disclosure: click slot → inline expand panel with hens list
- Simplified edit modal (removed live preview/layout math)
- Retained full add modal with live layout preview and configuration summary
- Integrated `<x-confirm-modal>` for delete confirmation flow
- Escape key handler to close all modals
- Auto-open edit modal on resize error with inline error display

### Egg Logging Restructure
- 2-column layout: slot grid (55%) + sticky log entry form (45%)
- Moved today's egg total from summary cards to inline header metric
- Collapsed recent logs table behind chevron disclosure toggle
- Made hen count readonly (auto-populated from selected slot)
- Fixed sensor override modal toggle (style.display vs Tailwind hidden class conflict)
- Added keyboard accessibility to slot cards (Enter/Space selection)
- Replaced inline `match()` blocks with `<x-cage-color>` component

### CP% Legend
- Added legend above feed batches table
- Color-coded indicators: Optimal (green), Watch (yellow), Critical (red)

### Login Page
- Added soft red error banner (Notion-style callout)

### Mortality Log
- Pre-select cage via `?cage_id=` query param (MortalityController)

---

## 7. Other Changes

### 7.1 Schema Migration: Cage-Level to Slot-Grid Model

**Commit:** `f287c2b` (June 30, 2026)

#### New Table: `cage_slots`
- Central hub table linking cages to individual slots
- Fields: `cage_id` (FK), `slot_number`, `row_number`, `column_number`, `current_occupancy`, `has_sensor` (later removed), `sensor_device_id`

#### Model Changes
- **New `CageSlot` model** with relationships:
  - `cage()` → belongsTo
  - `hens()` → hasMany
  - `productionLogs()` → hasMany
  - `primaryHen()` → returns first active hen
  - `status` accessor → empty/occupied/sensor_only/full
  - `remaining` accessor → max_chickens_per_slot - current_occupancy

- **`Cage` model updated:**
  - `cageSlots()` → hasMany
  - `hens()` → hasManyThrough (via CageSlot)
  - `productionLogs()` → hasManyThrough (via CageSlot)
  - `latestProductionLog()` → hasManyThrough + latestOfMany
  - `latestEnvironmentLog()` → hasOne + latestOfMany
  - `total_capacity` accessor: rows × slots_per_row × max_chickens_per_slot

- **`Hen` model updated:**
  - FK changed from `cage_id` → `cage_slot_id`
  - `cageSlot()` → belongsTo
  - `cage` accessor → returns `$this->cageSlot?->cage`

- **`ProductionLog` model updated:**
  - FK changed from `cage_id` → `cage_slot_id`
  - `cageSlot()` → belongsTo
  - `cage` accessor → returns `$this->cageSlot?->cage`

- **`Forecast` model updated:**
  - FK changed from `cage_id` → `cage_slot_id` (nullable for whole-farm forecasts)
  - Added `breed` column

#### Migration Renumbering
- Old migrations (2026_06_28_*) deleted
- New migrations renumbered to 2026_01_01_000002 through 000013
- Ensures clean migration order for fresh installs

#### Seeder Rewrite
- Seeds 4 cages (CAGE-A through CAGE-D) with 3 rows × 5 slots each
- Creates 60 cage_slots total
- Seeds 180 hens (4 per slot for active cages)
- Sensor slots: CAGE-A slots 1, 5, 6, 10
- Production logs: 14 days of history per slot
- Environmental logs: 24 hours (every 2 hours)
- Feed consumption: 7 days per active cage
- Alerts: 2 sample alerts (humidity_high, humidity_watch)
- Mortality: 7 sample records across cages
- Forecasts: 7 days for CAGE-A

### 7.2 Chickens Inventory — New Feature

**Commit:** `fd98980` (June 30, 2026)

#### New Controller: `ChickensController`
- **`index()`**: displays inventory + mortality tabs
- **`move(Request $request, Hen $hen)`**: moves hen to new slot with capacity check
- **`remove(Request $request, Hen $hen)`**: removes hen, optionally records as mortality

#### View: `chickens/index.blade.php`
- **Inventory tab:**
  - Grouped hierarchy: cage → slot → hen
  - Per-hen Move/Remove buttons
  - Checkbox bulk selection
  - Status toggle: all/active/inactive
  - Cage/breed filters, tag code search
  - Collapsible cages and slots (start collapsed)

- **Mortality tab:**
  - Embeds full mortality log (form + records + today's summary)
  - Optionally creates MortalityLog entry on hen removal

#### Modals
- **Move modal:** cage → slot destination picker with live capacity check
- **Remove modal:** optional record as mortality with reason/notes fields

#### Slot Expand Panel
- Hover maximize icon on slot box
- Expands inline hen list panel
- Per-hen and bulk Move All / Remove All actions
- `hens-json` endpoint for dynamic loading

#### Model Updates
- **`Hen` model:** added `cage` accessor
- **`CageSlot` model:** added `remaining` accessor

### 7.3 Egg Management Module — New Feature

**Commit:** `2acc899` (July 1, 2026)

#### New Models
- **`EggSizeLog`**: tracks egg size per production log (small/medium/large/jumbo)
- **`EggStockBatch`**: tracks harvested egg batches with freshness status
- **`PreOrder`**: tracks customer pre-orders with QR codes
- **`MortalityLogHen`**: pivot table linking mortality logs to individual hens

#### New Controllers
- **`EggStockController`**: CRUD for egg stock batches
- **`PreOrderController`**: CRUD for pre-orders with QR generation

#### New Migrations
- `2026_07_01_012851_create_egg_size_logs_table.php`
- `2026_07_01_012852_create_egg_stock_batches_table.php`
- `2026_07_01_012853_create_pre_orders_table.php`
- `2026_07_01_012854_create_mortality_log_hens_table.php`

#### Views
- `eggs/_tabs.blade.php`: shared tab navigation
- `eggs/stocks.blade.php`: stock batch inventory
- `eggs/pre-orders.blade.php`: pre-order management
- `eggs/qr-print.blade.php`: QR code print view

#### Mortality Integration
- Mortality logs now update hen state (`is_active = false`)
- Slot occupancy decremented automatically
- Spike alerts triggered when mortality rate exceeds threshold
- `RepairMortalityHenState` artisan command for fixing inconsistencies

#### Notifications
- Replaced dashboard alert banner with session-aware modal
- New notifications page (`/notifications`)
- `<x-alerts-modal>` component for displaying new alerts

### 7.4 Turbo Drive Fixes

**Commit:** `f4d1ae4` (July 1, 2026)

#### Issues Fixed
1. **Lucide icons not rendering after Turbo page swaps**
   - Solution: moved `createIcons()` into `turbo:load` handler

2. **Active nav highlight not updating**
   - Solution: replaced server-side `routeIs` logic with client-side `data-route` matching

3. **Drag-drop page refresh**
   - Solution: added `e.stopPropagation()` and explicit Content-Type headers on JSON responses

4. **Drag-drop not persisting**
   - Solution: added `location_row`/`location_column` to `Cage::fillable`

5. **Location field replaced with canvas position**
   - Removed text Location input from add/edit modals
   - Added read-only canvas position display
   - Removed redundant location text from cage cards

6. **White page on cage form submit**
   - Solution: added `data-turbo=false` to cage forms

7. **Mobile drawer opening minimized**
   - Solution: guarded collapsed state restore with `window.innerWidth >= 1024`

8. **Content not loading on Turbo navigation**
   - Solution: wrapped sidebar event binding in `SIDEBAR_INITIALIZED` flag
   - Applied bind-once patterns across reports, egg-logging, bulk-add, cages views

### 7.5 Cage Code Auto-Generation

**Commit:** `6a3eaab` (July 1, 2026)

#### Changes
- Removed manual `cage_code` input from create form
- Server-side generation: CAGE-A, CAGE-B, ... CAGE-AA sequence
- Per-slot sensor configuration added to edit cage modal only
- Persisted slot-level sensor updates in `CageController@update`
- Deleted dead `toggleSensor()` method and route
- Extended Cage color generation for dynamically created cages beyond CAGE-D

### 7.6 Pagination

**Commit:** `ce0936b` (June 30, 2026)

#### Changes
- Paginated all system tables at 20 records/page:
  - EggLogging
  - Mortality
  - Chickens mortality
  - Feed consumption
- Restructured egg-logging slot grid into collapsible cage dropdown groups
- Chickens inventory: cages and slots start collapsed
- Reinitialized Lucide icons after toggle to fix chevron rendering in hidden panels

### 7.7 Fullscreen Removal

**Commit:** `6662dac` (July 2, 2026)

#### Changes
- Commented out auto-fullscreen script in `layouts/app.blade.php`
- Removed `requestFullscreen()` calls on load and first user interaction
- Script retained but disabled for potential future re-enablement

---

## Commit Log

| Hash | Date | Message |
|------|------|---------|
| `6662dac` | Jul 2 | removed the full screen for now |
| `f49524a` | Jul 1 | feat: hardware inventory, CRUD edits, modal overflow fix |
| `2acc899` | Jul 1 | feat: Egg Management module, mortality integration, notifications, and UI unification |
| `f4d1ae4` | Jul 1 | fix: resolve Turbo Drive navigation issues and replace location field with canvas position |
| `6a3eaab` | Jul 1 | feat(cages): auto-generate cage codes and add per-slot sensor config in edit form |
| `39c68dc` | Jul 1 | feat(ui): Phase 5 accessibility pass, Chart.js defaults, and favicon |
| `054b211` | Jul 1 | feat(ui): redesign cage management with tabbed compact layout and progressive disclosure |
| `f8aaeb3` | Jul 1 | feat(ui): restructure egg logging with sticky form layout and Notion design tokens |
| `b52e77c` | Jul 1 | feat(ui): redesign dashboard with Notion design system and reusable components |
| `e16929b` | Jul 1 | MD files for UI improvements |
| `ce0936b` | Jun 30 | feat: add pagination and improve slot UI across egg-logging, chickens, feed, and mortality |
| `f668deb` | Jun 30 | refactor: Egg Logging now per-slot instead of per-cage |
| `fd98980` | Jun 30 | feat: Chickens inventory section with move/remove and slot expand panel |
| `08d8cca` | Jun 30 | added slot grid |
| `f287c2b` | Jun 30 | Migrate schema from cage-level to slot-grid model |

---

**End of Audit**
