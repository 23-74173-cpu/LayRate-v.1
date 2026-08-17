<?php

namespace Tests\Unit\Models;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Setting::get()/thresholds()/eggWeights() used to run a fresh query on
 * every call — thresholds() in particular runs on every sensor-ingestion
 * reading (EnvironmentAlertService::check()) and every dashboard/report
 * load. Now cached, with cache cleared on every set() write.
 *
 * The get() caching design specifically guards against a real hazard found
 * while implementing this: 'farm_grid_rows' is called with default 6 in one
 * controller and default 4 in another. Caching the post-default value would
 * let whichever call site runs first silently poison the cache for the
 * other — so only the raw stored value is ever cached; each call site's
 * default is applied fresh, outside the cache, every time.
 */
class SettingCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_uses_cache_on_second_call(): void
    {
        Setting::set('temp_max', 30);

        DB::enableQueryLog();
        $first = Setting::get('temp_max', 99);
        $queriesAfterFirst = count(DB::getQueryLog());
        $second = Setting::get('temp_max', 99);
        $queriesAfterSecond = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertEquals('30', $first);
        $this->assertEquals('30', $second);
        $this->assertEquals(
            $queriesAfterFirst,
            $queriesAfterSecond,
            'Second get() call must be served from cache — no additional query.'
        );
    }

    public function test_different_default_values_for_same_unset_key_are_each_honored(): void
    {
        // Mirrors the real-world shape of the hazard found while
        // implementing this: 'farm_grid_rows' is called with default 6 in
        // one controller (CageController.php:33) and default 4 in another
        // (CageController.php:1096). A made-up key is used here rather than
        // 'farm_grid_rows' itself because that one is pre-seeded by
        // migration 2026_07_01_012849_seed_farm_grid_settings — this test
        // needs a key with genuinely no row in the table.
        $key = 'test_unset_setting_key';
        $this->assertDatabaseMissing('settings', ['key' => $key]);

        $withDefaultSix = Setting::get($key, 6);
        $withDefaultFour = Setting::get($key, 4);

        $this->assertEquals(6, $withDefaultSix);
        $this->assertEquals(4, $withDefaultFour, 'Second call\'s default must not be poisoned by the first call\'s cached result.');
    }

    public function test_set_invalidates_get_cache_immediately(): void
    {
        Setting::set('temp_max', 30);
        $this->assertEquals('30', Setting::get('temp_max'));

        Setting::set('temp_max', 35);

        $this->assertEquals('35', Setting::get('temp_max'), 'Must reflect the new value immediately, not the stale cached one.');
    }

    public function test_thresholds_reflects_updates_after_cache_was_warmed(): void
    {
        Setting::set('temp_min', 18);
        Setting::set('temp_max', 30);
        Setting::set('hum_min', 40);
        Setting::set('hum_max', 70);

        $warm = Setting::thresholds();
        $this->assertEquals(30.0, $warm['temp_max']);

        Setting::set('temp_max', 32);

        $fresh = Setting::thresholds();
        $this->assertEquals(32.0, $fresh['temp_max'], 'thresholds() must not serve a stale cached blob after set().');
    }

    public function test_egg_weights_reflects_updates_after_cache_was_warmed(): void
    {
        Setting::set('egg_weight_medium', 58);
        $warm = Setting::eggWeights();
        $this->assertEquals(58.0, $warm['medium']);

        Setting::set('egg_weight_medium', 60);
        $fresh = Setting::eggWeights();
        $this->assertEquals(60.0, $fresh['medium']);
    }

    public function test_thresholds_query_count_drops_to_zero_on_cached_call(): void
    {
        Setting::set('temp_min', 18);
        Setting::set('temp_max', 30);

        Setting::thresholds(); // warm the cache

        DB::enableQueryLog();
        Setting::thresholds();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertEquals(0, $queries, 'A warm thresholds() call must hit zero queries — this runs on every sensor reading.');
    }

    protected function tearDown(): void
    {
        Cache::flush();
        parent::tearDown();
    }
}
