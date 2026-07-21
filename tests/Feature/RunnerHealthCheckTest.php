<?php

namespace Tests\Feature;

use App\Models\Alert;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunnerHealthCheckTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    #[Test]
    public function it_creates_alert_when_runner_inactive(): void
    {
        Process::fake([
            'systemctl is-active *' => Process::result(
                output: 'inactive',
                exitCode: 3,
            ),
        ]);

        $this->artisan('runner:health-check')
            ->expectsOutputToContain('Runner service actions.runner.23-74173-cpu-LayRate-v.1.LayRatePI.service is inactive.')
            ->assertSuccessful();

        $this->assertDatabaseHas('alerts', [
            'alert_type' => 'runner_offline',
            'is_read' => 0,
        ]);

        $alert = Alert::where('alert_type', 'runner_offline')->first();
        $this->assertNull($alert->cage_id);
        $this->assertStringContainsString('inactive', $alert->message);
    }

    #[Test]
    public function it_does_not_duplicate_alert_when_still_inactive(): void
    {
        Alert::create([
            'cage_id' => null,
            'alert_type' => 'runner_offline',
            'message' => 'Runner is inactive.',
            'is_read' => 0,
            'triggered_at' => now()->subHour(),
        ]);

        Process::fake([
            'systemctl is-active *' => Process::result(
                output: 'inactive',
                exitCode: 3,
            ),
        ]);

        $this->artisan('runner:health-check')
            ->expectsOutputToContain('Runner offline alert already exists')
            ->assertSuccessful();

        $this->assertEquals(
            1,
            Alert::where('alert_type', 'runner_offline')->where('is_read', 0)->count(),
            'Should still have exactly one unread alert — no duplicate created.',
        );
    }

    #[Test]
    public function it_clears_existing_alert_when_runner_comes_back_online(): void
    {
        Alert::create([
            'cage_id' => null,
            'alert_type' => 'runner_offline',
            'message' => 'Runner was inactive.',
            'is_read' => 0,
            'triggered_at' => now()->subHour(),
        ]);

        Process::fake([
            'systemctl is-active *' => Process::result(
                output: 'active',
                exitCode: 0,
            ),
        ]);

        $this->artisan('runner:health-check')
            ->expectsOutputToContain('Runner service actions.runner.23-74173-cpu-LayRate-v.1.LayRatePI.service is active.')
            ->expectsOutputToContain('Cleared previous runner_offline alert')
            ->assertSuccessful();

        $this->assertDatabaseHas('alerts', [
            'alert_type' => 'runner_offline',
            'is_read' => 1,
        ]);
    }
}
