<?php

namespace Tests\Feature;

use RuntimeException;
use Tests\TestCase;

/**
 * Tests\TestCase must refuse a non-QA schema, whatever APP_ENV says. It runs
 * the check from createApplication(), before RefreshDatabase wipes anything.
 */
class TestDatabaseGuardTest extends TestCase
{
    public function test_guard_refuses_other_schemas_in_any_environment(): void
    {
        $this->app['env'] = 'local';
        config(['database.connections.mysql.database' => 'layrate']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing RefreshDatabase');

        $this->ensureApprovedTestSchema($this->app);
    }
}
