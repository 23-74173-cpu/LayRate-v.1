<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Cage;
use App\Models\CageSlot;
use App\Models\EnvironmentalLog;
use App\Models\ForecastRun;
use App\Models\Hen;
use App\Models\Note;
use App\Models\ProductionLog;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the System Settings → Clear Database admin action: wiping all farm
 * data while preserving the users table and the settings table.
 */
class ClearDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    /**
     * Every table that clearDatabase() must wipe. Mirrors the controller's
     * DATA_TABLES list; framework + users/settings tables are preserved.
     *
     * @var array<int, string>
     */
    private const DATA_TABLES = [
        'alerts',
        'cage_slots',
        'cage_transfers',
        'cages',
        'culling_logs',
        'devices',
        'egg_size_logs',
        'egg_stock_batches',
        'environmental_logs',
        'farm_feed_entries',
        'feed_batches',
        'feed_consumption_logs',
        'forecast_runs',
        'forecasts',
        'hardware_items',
        'health_events',
        'hens',
        'mortality_log_hens',
        'mortality_logs',
        'notes',
        'pre_orders',
        'production_logs',
        'removals',
        'sensor_occupancy_readings',
        'weight_checks',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->admin = User::where('email', 'admin@layrate.local')->firstOrFail();
        $this->operator = User::where('email', 'operator@layrate.local')->firstOrFail();
    }

    private function seedFarmData(): void
    {
        $cage = Cage::create([
            'cage_code' => 'CAGE-CLEAR',
            'location' => 'Test',
            'rows' => 1,
            'slots_per_row' => 1,
            'max_chickens_per_slot' => 50,
            'total_capacity' => 50,
            'is_active' => 1,
        ]);
        $slot = CageSlot::create([
            'cage_id' => $cage->id,
            'slot_number' => 1,
            'row_number' => 1,
            'column_number' => 1,
            'current_occupancy' => 50,
        ]);
        $hen = new Hen([
            'tag_code' => 'CAGE-CLEAR-1',
            'breed' => 'ISA Brown',
            'flock_age_weeks' => 30,
            'date_acquired' => now()->subMonths(8),
            'placement_date' => now()->subMonths(8),
            'age_at_placement_weeks' => 0,
            'is_active' => 1,
        ]);
        $hen->cage_slot_id = $slot->id;
        $hen->save();

        ProductionLog::create([
            'cage_slot_id' => $slot->id,
            'log_date' => now()->toDateString(),
            'hen_count' => 50,
            'egg_count' => 45,
            'hdep' => 90.0,
            'logged_via' => 'unknown',
        ]);
        EnvironmentalLog::create([
            'cage_id' => $cage->id,
            'recorded_at' => now(),
            'temperature_c' => 28.0,
            'humidity_pct' => 60.0,
            'is_override' => false,
        ]);

        $day = now()->toDateString();
        Alert::create([
            'cage_id' => $cage->id,
            'alert_type' => 'temperature_high',
            'message' => 'Temp above max.',
            'is_read' => false,
            'triggered_at' => now(),
            'alert_day' => $day,
            'dedup_key' => Alert::dedupKey($cage->id, 'temperature_high'),
        ]);
        Note::create(['body' => 'Test note', 'cage_id' => $cage->id]);
        ForecastRun::create([
            'scope' => 'cage',
            'cage_code' => 'CAGE-CLEAR',
            'horizon' => 7,
            'status' => 'queued',
            'redirect_params' => [],
        ]);
        Setting::set('clear_test_key', 'kept');
    }

    public function test_operator_cannot_access_clear_database_route(): void
    {
        $this->actingAs($this->operator)
            ->post(route('settings.clear-database'), ['admin_password' => 'password'])
            ->assertForbidden();
    }

    public function test_system_tab_renders_clear_database_button_for_admin(): void
    {
        $response = $this->actingAs($this->admin)->get(route('profile', ['tab' => 'system']));

        $response->assertOk();
        $response->assertSee('Clear Database');
        $response->assertSee('/settings/clear-database', false);
        $collapse = fn (string $html): string => preg_replace('/\s+/', ' ', $html) ?? '';
        $this->assertStringContainsString(
            '> Clear database </button>',
            $collapse($response->getContent()),
            'The Clear database submit button must render inside the section.'
        );
        $this->assertStringContainsString('name="admin_password"', $response->getContent());
    }

    public function test_system_tab_hides_clear_database_for_operator(): void
    {
        $response = $this->actingAs($this->operator)->get(route('profile', ['tab' => 'system']));

        $response->assertOk();
        $response->assertDontSee('Clear Database');
    }

    public function test_wrong_admin_password_is_rejected_and_data_intact(): void
    {
        $this->seedFarmData();

        $this->actingAs($this->admin)
            ->post(route('settings.clear-database'), ['admin_password' => 'wrong-password'])
            ->assertRedirect()
            ->assertSessionHasErrors('admin_password');

        $this->assertDatabaseCount('cages', 1);
        $this->assertDatabaseCount('hens', 1);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_correct_password_clears_farm_data_keeps_users_and_settings(): void
    {
        $this->seedFarmData();

        $response = $this->actingAs($this->admin)
            ->post(route('settings.clear-database'), ['admin_password' => 'password']);

        $response->assertRedirect(route('profile', ['tab' => 'system']));
        $response->assertSessionHas('success');

        foreach (self::DATA_TABLES as $table) {
            $this->assertSame(0, DB::table($table)->count(), "Table {$table} should be empty after clear.");
        }

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseHas('settings', ['key' => 'clear_test_key']);
    }
}