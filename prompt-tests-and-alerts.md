## Add missing controller tests + scheduled env alert command

### Current state
- 20 feature tests exist in `tests/Feature/`. Patterns used: `RefreshDatabase`, dedicated test records with unique prefixes, `actingAs()` + route-based assertions, `assertDatabaseHas`/`assertDatabaseMissing`.
- `EnvironmentAlertService::check()` creates alerts when env readings cross thresholds, but is ONLY called from `SensorIngestionController` (live sensor ingestion). There is no scheduled command to re-check existing EnvironmentalLog rows against thresholds.
- No `App\Console\Commands` directory has alert-related commands. `routes/console.php` only has the `forecast:sync-input-records` schedule.

### Target state
1. Controller test files exist for every controller that lacks coverage, following existing patterns.
2. A new Artisan command `alerts:check-environment` reads all EnvironmentalLog rows from the last 24 hours and passes each to `EnvironmentAlertService::check()`.
3. That command is registered in `routes/console.php` to run every 15 minutes. No infrastructure changes needed — the Pi already has `* * * * * php artisan schedule:run` via cron (SETUP.md).

### File scope
**Create:**
- `tests/Feature/DashboardControllerTest.php`
- `tests/Feature/CageControllerTest.php` (full coverage — existing CageDeleteFlowTest only covers delete flow)
- `tests/Feature/MortalityControllerTest.php`
- `tests/Feature/EnvironmentControllerTest.php` (existing EnvironmentThresholdTest only covers live-data status display)
- `tests/Feature/FeedControllerTest.php` (existing FeedBatchManagementTest covers batch CRUD + consumption; add any missing)
- `tests/Feature/ForecastControllerTest.php`
- `tests/Feature/AuthControllerTest.php`
- `app/Console/Commands/CheckEnvironmentAlerts.php`
- (any additional controller uncovered — discover by diff)

**Modify:**
- `routes/console.php` — add the schedule entry

**Do NOT touch:**
- Any existing test file that already passes
- Database schema or migrations
- Controllers, models, services (test only — no app code changes except the new command)
- CSS/JS/Blade files

### Constraints
- Follow existing test patterns exactly: `setUp()`, dedicated test records, `actingAs()`, route-based assertions, `assertDatabaseHas`.
- Use `DatabaseSeeder` if the test needs the seeded admin user; otherwise use `User::factory()->create(['role' => 'admin'])`.
- Use `self::createTestCage()` helper pattern (like `SensorIngestionTest`'s dedicated cage) to avoid collision with seed data.
- The command must call `EnvironmentAlertService::check()` for each log — not duplicate the threshold logic.
- Only make changes directly requested. Do not add extra features, abstractions, or refactoring.
- Do not add test files for controllers that already have test coverage.

### Stop conditions
- Stop and ask before deleting any file, adding any dependency, or touching the database schema.
- Show me which controllers you found uncovered before writing tests for them.
- If an existing test file already covers a controller, skip it.

### Check
After writing:
1. `php artisan make:test DashboardControllerTest` equivalent files exist for each uncovered controller
2. `php artisan alerts:check-environment` runs without error against existing env logs
3. New tests pass: `php artisan test --filter=DashboardControllerTest`
4. All pre-existing tests still pass: `php artisan test --testsuite=Feature --exclude-group=fails`
