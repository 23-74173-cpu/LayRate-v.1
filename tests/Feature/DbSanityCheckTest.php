<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase as FrameworkTestCase;
use Illuminate\Support\Facades\DB;

/**
 * Temporary QA sentinel: verifies the test suite resolves to the dedicated
 * layrate_testing database and NOT the live `layrate` DB, before any
 * RefreshDatabase test is allowed to run. Deliberately does NOT use
 * Tests\TestCase (which pulls in RefreshDatabase).
 */
class DbSanityCheckTest extends FrameworkTestCase
{
    public function test_testing_connection_points_at_layrate_testing(): void
    {
        $configured = config('database.connections.mysql.database');
        $this->assertSame('layrate_testing', $configured, "Configured DB is '$configured' — refusing to run suite against live DB");

        $actual = DB::connection()->getDatabaseName();
        $this->assertSame('layrate_testing', $actual, "Connected DB is '$actual' — refusing to run suite against live DB");
    }
}
