# LayRate Poultry Farm Management System — Codebase Audit

## 1. TECH STACK

### Programming Languages
- **PHP 8.2+** — backend (Laravel 12 ecosystem)
- **Blade** — server-side templating (`.blade.php`)
- **JavaScript (vanilla)** — frontend interactivity (no JS framework)
- **C++ (Arduino/PlatformIO)** — embedded firmware for sensor hardware
- **SQL** — MySQL schema definition + Laravel migrations
- **YAML** — GitHub Actions CI/CD
- **INI** — PlatformIO project configuration

### Frameworks & Major Libraries
| Layer | Technology | Version / Notes |
|---|---|---|
| Backend | **Laravel** | `^12.0` (Laravel 12) |
| Frontend | **Tailwind CSS** | via CDN (`cdn.tailwindcss.com`) |
| Charts | **Chart.js** | via CDN (`4.4.0`) |
| Icons | **Lucide** | via CDN (`unpkg.com/lucide`) |
| Testing | **PHPUnit** | `^11.5.50` |
| Code style | **Laravel Pint** | `^1.24` |
| Faker | **FakerPHP** | `^1.23` |
| Mocking | **Mockery** | `^1.6` |
| Error UI | **NunoMaduro/Collision** | `^8.6` |
| Dev env | **Laravel Sail** | `^1.41` |
| Log viewer | **Laravel Pail** | `^1.2.2` |
| REPL | **Laravel Tinker** | `^2.10.1` |

### Embedded / Hardware
- **PlatformIO** (`LayRate - Arduino/platformio.ini`)
  - Board: `Arduino Uno R3` (atmelavr/uno)
  - Framework: `Arduino`
  - Libraries: `DHT sensor library@^1.4.4`, `Adafruit Unified Sensor@^1.1.9`
  - Sensors: **DHT22** (temp/humidity), **IR Break Beam** (egg counting)
  - Serial: 9600 baud

### Build Tools & Package Managers
- **Composer** — PHP dependency management (`composer.json`, `composer.lock`)
- **npm** — Node-based build step referenced in composer scripts
- **Vite** — referenced in the `dev` script as `npm run dev`
- **PlatformIO** — Arduino build system (`platformio.ini`)
- **No Dockerfile present** but Sail config available

### Database System(s)
- **MySQL 8.x / MariaDB** — primary database, configured via `DB_SOCKET=/tmp/mariadb.sock` (MariaDB socket) in `.env`
- **SQLite** — not available in this environment (PHP built without `pdo_sqlite`); the codebase's `.env.example` references `DB_CONNECTION=sqlite` but the runtime uses MySQL
- Database drivers configured: sqlite, mysql, mariadb, pgsql, sqlsrv

### Deployment & Runtime
- **GitHub Actions** — CI/CD to a **Raspberry Pi** self-hosted runner (`.github/workflows/deploy.yml`)
- Deploy target: `/var/www/layrate` on a Pi, owned by user `layratepi`, group `www-data`
- Environment config via `.env` file (`.env.example` provided)
- Storage/cache directories explicitly permissioned during deploy

---

## 2. PROJECT STRUCTURE

```
LayRate-Main/
├── app/
│   ├── Http/
│   │   ├── Controllers/          # 13 controllers
│   │   └── Middleware/           # EnsureAdmin middleware
│   ├── Models/                   # 12 Eloquent models (Cage + CageSlot + 10 others)
│   └── Providers/                # AppServiceProvider
├── bootstrap/                    # Laravel app bootstrap + cached config
├── config/                       # 10 Laravel config files
├── database/
│   ├── factories/                # UserFactory
│   ├── migrations/               # 17 migration files (2026-01-01_* timestamped to avoid conflicts)
│   ├── seeders/                  # DatabaseSeeder (60 cage_slots, 180 hens, 4 sensor-equipped slots)
│   └── layrate_schema.sql        # Standalone MySQL schema (reference)
├── resources/views/
│   ├── layouts/app.blade.php     # Main layout (sidebar + header)
│   ├── auth/login.blade.php      # Login page
│   ├── cages/
│   │   ├── index.blade.php      # Cage management — slot grid UI with Battery Cage Configuration modal
│   │   └── confirm-delete.blade.php # Delete confirmation with data loss counts
│   ├── partials/
│   │   ├── slot-box.blade.php   # Reusable slot box partial (CageSlot model)
│   │   └── cage-sensor-badge.blade.php
│   └── [feature].blade.php       # Per-feature views
├── routes/
│   ├── web.php                   # All web routes (all auth-guarded except login/logout)
│   └── console.php               # Artisan console commands
├── public/                       # Web server document root
├── storage/                      # Logs, cache, sessions
├── docs/                         # Documentation (system workflow, specs, plans)
├── dist/                         # Built frontend assets (index.html + bundled JS/CSS)
├── docu/                         # Research manuscript (.docx)
├── LayRate - Arduino/            # Arduino firmware project
│   ├── src/main.cpp              # Firmware source
│   ├── platformio.ini            # PlatformIO config
│   ├── lib/ test/ include/       # Arduino project scaffolding
│   └── .vscode/                  # VS Code / PlatformIO IDE config
├── tests/                        # Empty — no test files found
├── node_modules/                 # Frontend dependencies
├── vendor/                       # Composer dependencies
└── .github/workflows/deploy.yml  # CI/CD pipeline
```

### Entry Points
- **`public/index.php`** — HTTP entry point (Laravel front controller)
- **`artisan`** — CLI entry point
- **`bootstrap/app.php`** — Application configuration (middleware aliases, route loading)
- **`routes/web.php`** — All HTTP route definitions
- **`LayRate - Arduino/src/main.cpp`** — Arduino firmware entry

### Architecture Pattern
**Monolithic MVC** (Laravel standard):
- **Model** — `app/Models/` (Eloquent ORM)
- **View** — `resources/views/` (Blade templates)
- **Controller** — `app/Http/Controllers/` (thin controllers)
- **Routes** — `routes/web.php` (all routes in a single file)
- No service layer, no custom commands/queues/jobs, no API routes

---

## 3. FEATURES IMPLEMENTED

### 3.1 Authentication & Authorization
- **Login/Logout** (`AuthController`, `auth/login.blade.php`)
- Session-based auth using Laravel's built-in auth
- Two roles: `admin` and `operator`
- **Admin middleware** (`EnsureAdmin`) guards destructive actions (delete cage, delete production log, delete mortality log)
- Route: `routes/web.php:18-23`

### 3.2 Dashboard (`/`)
- **Controller**: `DashboardController`
- **View**: `dashboard.blade.php`
- Metric cards: total hens, today's HDEP (hen-day egg production), eggs collected, coop environment
- Cage overview cards with breed, capacity, flock age, sensor status
- Feed today progress bars per cage
- Mortality today per cage
- Recent alerts with mark-read functionality
- Live readings per cage (temperature/humidity with status indicators)

### 3.3 Cage Management (`/cages`)
- **Controller**: `CageController`
- **View**: `cages/index.blade.php`, `cages/confirm-delete.blade.php`
- **New Slot-Grid UI** (Commit 2): Each cage rendered as a card with an interactive slot grid showing per-slot occupancy, age, and sensor status
- **Battery Cage Configuration modal** (Add Cage): Live Layout Preview (rows × columns) and Configuration Summary that update on field change; creates `Cage` + all `CageSlot` rows in a single transaction
- **Edit Cage modal**: Resize-safety check before allowing dimension changes; blocks resize if occupied hens or sensor-equipped slots would be orphaned; flashes `session('edit_cage_id')` + error message on block, causing modal to auto-re-open with the failed cage
- **Per-slot sensor toggle**: Click any slot box to POST-toggle its `has_sensor` flag via `CageController::toggleSensor()`
- **Delete confirmation** (`confirm-delete.blade.php`): Shows slot count, sensor slot count, hen count, and counts of all related records (production logs, environmental logs, feed consumption, mortality, alerts) before deletion
- **New CageController methods**: `toggleSensor()`, `deleteConfirm()`, `forceDestroy()`, `checkResizeSafety()`, `resizeSlots()`, `createSlotsForCage()`
- Cage entity fields: cage_code, location, rows, slots_per_row, max_chickens_per_slot, total_capacity (computed), is_active
- Per-slot fields (via `cage_slots` table): slot_number, row_number, column_number, current_occupancy, has_sensor, sensor_device_id
- Cage colors: A=#2D7D46, B=#1D4E8F, C=#C2703E, D=#6B4C8A
- Sensor slots seeded: CAGE-A slots 1 (row-1 col-1), 5 (row-1 col-5), 6 (row-2 col-1), 10 (row-2 col-5) — each occupied with 4 hens

### 3.4 Egg Logging (`/egg-logging`)
- **Controller**: `EggLoggingController`
- **View**: `egg-logging.blade.php`
- Log daily egg production per cage (date, cage, egg count, hen count)
- Auto-computes HDEP = (eggs / hens) × 100
- **Sensor Lock Override** feature: Previously applied at cage level (`Cage.has_sensor`); now uses `cage_slot_id` — eggs logged per cage via first slot ID
- Override uses a session-based timed verification (10-minute window)
- Recent logs table with override tracking, admin delete
- Rate-limited override verification (6 requests per minute)

### 3.5 Environment Monitor (`/environment`)
- **Controller**: `EnvironmentController`
- **View**: `environment.blade.php`
- Per-cage sensor cards showing live temperature/humidity with status (Normal/Watch/Alert)
- Trend charts (Chart.js line charts for temp/humidity over 24 hours)
- Configurable alert thresholds stored in `settings` table
- Recent log summary table

### 3.6 Feed & Nutrition (`/feed`)
- **Controller**: `FeedController`
- **View**: `feed.blade.php`
- Dual-tab interface: **Feed Batches** (CRUD) and **Daily Consumption** (log per cage per day)
- Tracks crude protein percentage per batch with color-coded indicators
- Weekly summary metrics: avg CP%, avg feed/cage/day, total feed used (7-day rolling)
- `recorded_by` correctly set to `auth()->id()` (fixed in Commit 1)

### 3.7 Mortality Log (`/mortality`)
- **Controller**: `MortalityController`
- **View**: `mortality.blade.php`
- Record deaths per cage with date, count, reason (Disease/Heat Stress/Injury/Predator/Unknown/Other), and notes
- Today's summary cards per cage with red highlighting on losses
- Recent records table with admin delete

### 3.8 Analytics (`/analytics`)
- **Controller**: `AnalyticsController`
- **View**: `analytics.blade.php`
- Per-cage + per-period (week/month/3 months) selection tabs
- Three Chart.js charts: HDEP trend line, eggs collected bar chart, feed-vs-HDEP scatter plot
- Summary metrics: avg HDEP, best day, worst day, breed, flock age

### 3.9 Forecast (`/forecast`)
- **Controller**: `ForecastController`
- **View**: `forecast.blade.php`
- Per-cage or whole-farm scope toggle
- Horizon options: 7, 14, or 30 days
- **Primitive forecasting**: averages last 14 days of HDEP then applies a deterministic variation (±0.3% per 3-day cycle) — not a real ML/statistical model
- Stores forecasts in DB for persistence; confidence decreases with distance from forecast date

### 3.10 Reports (`/reports`)
- **Controller**: `ReportController`
- **View**: `reports.blade.php`
- Four report types: Production, Feed, Environment, Mortality
- Date range and cage filter; mortality report additionally filters by reason
- Formal printable document with letterhead, metadata strip, summary statistic pills, data table, and signature block
- **CSV export** endpoint (`/reports/csv`) streams CSV for any report type

### 3.11 Account Settings (`/account`)
- **Controller**: `AccountController`
- **View**: `account.blade.php`
- Change password (requires current password; min 8 chars)
- Set/change override PIN (4-6 digits, rejects weak PINs from a blocklist of 14 common patterns)
- Admin-only staff PIN status table

### 3.12 Arduino Sensor Firmware
- **File**: `LayRate - Arduino/src/main.cpp`
- **Sensors**: DHT22 (temperature/humidity), IR Break Beam (egg counting via beam interrupt)
- **Behavior**:
  - DHT22 reads every 2 seconds with 3-retry NaN/range validation
  - IR beam detects object passage, increments counter, toggles onboard LED
  - Outputs formatted blocks over serial (9600 baud) only on value change
  - Error messages when DHT22 fails wiring checks

### Partially Implemented / Notable Gaps
- **No alert generation logic** in the backend — alerts are only created via seeders, never programmatically from sensor readings crossing thresholds
- **Forecast algorithm** is a toy: simple averaging + deterministic sinusoidal variation (±0.3). No statistical model, no seasonality, no trend detection
- **No queue workers consuming jobs** despite `QUEUE_CONNECTION=database` — no custom jobs defined
- **No email/password reset flow** — `password_reset_tokens` table exists but no routes or views
- **No API routes** — fully server-rendered, no REST/JSON API
- **No tests** — `tests/` directory exists but is empty
- **No data ingestion pipeline** from Arduino — environmental data must be manually entered or imported

---

## 4. DATA LAYER

### Database Schema (12 application tables + 5 framework tables)

> **Schema note**: Commit 1 introduced `cage_slots` as a central hub table. The `production_logs`, `hens`, and `forecasts` tables now reference `cage_slot_id` (FK) instead of `cage_id`. The `cages` table itself no longer has `capacity` (replaced by `total_capacity` computed from `rows × slots_per_row × max_chickens_per_slot`) or `has_sensor` (now per-slot on `cage_slots`).

| Table | Key Fields | Relationships |
|---|---|---|
| **users** | id, name, email, password, role (admin/operator), override_pin_hash | N/A |
| **cages** | id, cage_code (unique), location, rows, slots_per_row, max_chickens_per_slot, total_capacity, is_active | PK for cage_slots |
| **cage_slots** | id, cage_id (FK), slot_number, row_number, column_number, current_occupancy, has_sensor, sensor_device_id | FK → cages; PK for hens, production_logs, environmental_logs, alerts, feed_consumption_logs, forecasts |
| **hens** | id, cage_slot_id (FK), tag_code (unique), date_acquired, placement_date, age_at_placement_weeks, flock_age_weeks, breed (enum 5 breeds), is_active | FK → cage_slots |
| **production_logs** | id, cage_slot_id (FK), log_date, egg_count, hen_count, hdep (computed), recorded_by (FK→users), overridden_by_user_id, overridden_at, notes | FK → cage_slots, users; unique(cage_slot_id, log_date) |
| **environmental_logs** | id, cage_id (FK), recorded_at, temperature_c, humidity_pct | FK → cages; index on recorded_at |
| **feed_batches** | id, batch_code (unique), crude_protein, date_received, notes | PK for feed_consumption_logs |
| **feed_consumption_logs** | id, cage_id (FK), feed_batch_id (FK), log_date, feed_consumed_kg, recorded_by (FK) | FK → cages, feed_batches, users; unique(cage_id, log_date) |
| **alerts** | id, cage_id (FK), alert_type, message, is_read, triggered_at | FK → cages |
| **forecasts** | id, cage_slot_id (FK, nullable), forecast_date, target_date, predicted_hdep | FK → cage_slots; nullable for whole-farm forecasts |
| **mortality_logs** | id, cage_id (FK), log_date, count, reason (enum), notes, recorded_by (FK) | FK → cages, users |
| **settings** | id, key (unique), value, label | key-value store for threshold config |
| **jobs**, **job_batches**, **failed_jobs** | Standard Laravel queue tables | — |
| **cache**, **cache_locks** | Standard Laravel cache tables | — |
| **sessions** | Standard Laravel session table | — |
| **migrations** | Framework migration tracking | — |

### Key Model Relationships

**Cage model** (`Cage.php`):
- `cageSlots()` → `hasMany(CageSlot::class)` — all slots in this cage
- `hens()` → `hasManyThrough(Hen::class, CageSlot::class)` — all hens via slots
- `productionLogs()` → `hasManyThrough(ProductionLog::class, CageSlot::class)` — all production logs via slots
- `latestProductionLog()` → `hasManyThrough(...)->latestOfMany('log_date')` — single latest production log (accessor only, not for eager loading)
- `latestEnvironmentLog()` → `hasOne(EnvironmentalLog::class)->latestOfMany('recorded_at')` — single latest env log (accessor only, not for eager loading)
- `environmentalLogs()` → `hasMany(EnvironmentalLog::class)` — direct FK to cages table
- `alerts()`, `feedConsumptionLogs()`, `forecasts()` → standard `hasMany`

**CageSlot model** (`CageSlot.php`, new in Commit 1):
- `cage()` → `belongsTo(Cage::class)`
- `hens()` → `hasMany(Hen::class)`
- `productionLogs()` → `hasMany(ProductionLog::class)`
- `primaryHen()` → returns the first active hen in this slot
- `status` accessor → `empty` / `occupied` / `sensor_only` / `full`

**ProductionLog model** (`ProductionLog.php`):
- `cageSlot()` → `belongsTo(CageSlot::class)`
- `getCageAttribute()` → accessor that returns `$this->cageSlot?->cage` for convenience
- `recorder()`, `overriddenBy()` → standard `belongsTo(User::class)`

### Forecasting / Data Processing
- **Location**: `ForecastController::generateForecast()` (line 111-134)
- **Algorithm**: Averages the last 14 days of HDEP, applies a hardcoded ±0.3% variation on a 3-day cycle
- **Data Flow**: ProductionLog records → aggregate historical averages → generate forecast values → store in `forecasts` table → display via view with Chart.js line chart
- No ML, no statistics library, no external data science tools

### Data Flow
1. **Arduino sensors** → Serial output (DHT22 temp/humidity, IR beam break count) → read by Pi (not yet implemented in PHP)
2. **Manual operator entry** (egg logging, feed, mortality) → validated via FormRequest → stored in MySQL via Eloquent ORM
3. **Threshold config** → saved to `settings` table → read on Environment page for status computation
4. **Dashboard** → aggregates latest readings across all cages; computes averages, trends, totals
5. **Analytics** → queries by cage/cage code + date range → renders Chart.js visualizations
6. **Reports** → date-range-filtered queries → renders printable document or streams CSV

---

## 5. CONFIGURATION & ENVIRONMENT

### Environment Variables (from `.env.example`)

```
APP_NAME, APP_ENV, APP_KEY, APP_URL
APP_LOCALE, APP_FALLBACK_LOCALE, APP_FAKER_LOCALE
APP_MAINTENANCE_DRIVER, APP_MAINTENANCE_STORE
PHP_CLI_SERVER_WORKERS
BCRYPT_ROUNDS
LOG_CHANNEL, LOG_STACK, LOG_DEPRECATIONS_CHANNEL, LOG_LEVEL
DB_CONNECTION, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD, DB_SOCKET
SESSION_DRIVER, SESSION_LIFETIME, SESSION_ENCRYPT, SESSION_PATH, SESSION_DOMAIN
BROADCAST_CONNECTION, FILESYSTEM_DISK, QUEUE_CONNECTION
CACHE_STORE, CACHE_PREFIX
MEMCACHED_HOST
REDIS_CLIENT, REDIS_HOST, REDIS_PASSWORD, REDIS_PORT
MAIL_MAILER, MAIL_SCHEME, MAIL_HOST, MAIL_PORT, MAIL_USERNAME, MAIL_PASSWORD
MAIL_FROM_ADDRESS, MAIL_FROM_NAME
AWS_ACCESS_KEY_ID, AWS_SECRET_ACCESS_KEY, AWS_DEFAULT_REGION, AWS_BUCKET, AWS_USE_PATH_STYLE_ENDPOINT
VITE_APP_NAME
```

> Note: `DB_SOCKET=/tmp/mariadb.sock` is required in this environment due to MariaDB running on a non-default socket path.

### External Services / APIs Integrated
- **Postmark** (email) — configured in `config/services.php`
- **Resend** (email) — configured
- **AWS SES** (email) — configured with credentials from env
- **Slack** (notifications) — configured with bot token
- **S3-compatible storage** — configured in `config/filesystems.php`
- **None of these are actively used** in any controller or model code — they are standard Laravel config stubs

---

## 6. CODE QUALITY OBSERVATIONS

### Naming & Style
- **Consistent**: PSR-4 compliant, Laravel conventions followed throughout
- **Good**: Controllers are thin, models are clean, routing is centralized
- **Minor inconsistency**: Some controllers use `fn()` arrow functions while others use full closures

### Duplicated Logic
- Cage color matching is repeated across at least 6 different view files — should be a single accessor or helper
- Temp/humidity status classification logic is duplicated between `EnvironmentController` and `EnvironmentalLog` model

### Error Handling
- **Good**: Validation errors caught via `$request->validate()` and returned with `->withErrors()`
- **Missing**: No exception handler customization (empty `withExceptions` block)
- **Missing**: No database operation try/catch wrapping

### Technical Debt
- **`hasManyThrough(...)->latestOfMany(...)` returns a query builder, not a Relation** — cannot be eager loaded via `with()`. Use a separate plain relationship for eager loading and the `latestOfMany` variant only as an accessor. This caused a `RelationNotFoundException` on Dashboard until fixed.
- **Arduino serial protocol** has no corresponding consumer on the PHP side
- **Alerts are never created programmatically** — threshold evaluation exists in views but no backend job/observer triggers alert creation
- **`dist/`** contains built frontend assets not connected to the Laravel views

### Outdated/Deprecated Dependencies
- Tailwind CSS via `latest` CDN — risk of breaking UI changes on auto-update
- Lucide via `unpkg.com/lucide@latest` — "latest" tag means unpredictable updates

### No Tests
- `tests/` directory exists with `TestCase.php` but **no test files** of any kind are present

---

## 7. SUMMARY

This is **LayRate**, a Laravel 12 web application that serves as a poultry farm management system. It provides egg production tracking (with HDEP computation), per-cage slot-grid cage management with optional per-slot IR/environmental sensors, feed batch tracking, mortality recording, configurable environmental alert thresholds, HDEP forecasting (toy algorithm), a multi-report generator with CSV export, and a per-cage analytics dashboard with Chart.js visualizations. The system is deployed to a **Raspberry Pi** via GitHub Actions self-hosted CI/CD. The repository also includes **Arduino Uno R3 firmware** for an IR break-beam egg counter and DHT22 sensor, though the data ingestion pipeline to the web application is not yet implemented.

**Commit 1** (slot migration, f287c2b) introduced `cage_slots` as the central data hub — `hens`, `production_logs`, and `forecasts` now use `cage_slot_id` FK instead of `cage_id`. The `cages` table was restructured from a flat single-cage model to a battery-cage model with `rows`, `slots_per_row`, `max_chickens_per_slot`, and `total_capacity`. `FeedController::recorded_by` was fixed to use `auth()->id()`.

**Commit 2** (this session) rebuilt the cage management UI (`cages/index.blade.php`) with a slot-grid card layout, Battery Cage Configuration add/edit modals with live Layout Preview, per-slot sensor toggle, resize-safety validation with auto-reopening edit modal on failure, and a delete confirmation page showing full data loss counts.

Notable remaining gaps: no automated alert generation, no test coverage, a placeholder forecasting algorithm, and no Arduino-to-PHP data pipeline. The application is functionally complete for manual data entry and reporting.
