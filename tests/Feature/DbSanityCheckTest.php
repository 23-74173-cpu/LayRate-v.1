<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase as FrameworkTestCase;
use Illuminate\Support\Facades\DB;

/**
 * Temporary QA sentinel: verifies the test suite resolves to the dedicated
 * approved isolated QA database and NOT the live `layrate` DB, before any
 * RefreshDatabase test is allowed to run. Deliberately does NOT use
 * Tests\TestCase (which pulls in RefreshDatabase).
 */
class DbSanityCheckTest extends FrameworkTestCase
{
    public function test_testing_connection_points_at_approved_isolated_schema(): void
    {
        $connection = config('database.default');
        $configured = config("database.connections.{$connection}.database");
        $this->assertSame('mysql', $connection, "Configured connection is '$connection' — refusing to run suite without approved MySQL target");
        $this->assertSame('layrate_qa_20261027', $configured, "Configured DB is '$configured' — refusing to run suite against non-approved schema");
        $this->assertSame('127.0.0.1', config("database.connections.{$connection}.host"));
        $this->assertSame('3306', (string) config("database.connections.{$connection}.port"));
        $this->assertEmpty(config("database.connections.{$connection}.url"));

        $actual = DB::connection($connection)->getDatabaseName();
        $this->assertSame('layrate_qa_20261027', $actual, "Connected DB is '$actual' — refusing to run suite against non-approved schema");
    }
}
