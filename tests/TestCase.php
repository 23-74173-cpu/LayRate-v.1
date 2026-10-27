<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    /**
     * The schema is checked as soon as the app exists. RefreshDatabase's
     * migrate:fresh drops every table inside parent::setUp(), so a check after
     * it is too late, and many tests re-import the trait, which would replace
     * a check placed in its hooks. Not limited to APP_ENV=testing: a cached
     * config (php artisan config:cache) overrides phpunit.xml, APP_ENV and
     * DB_DATABASE included.
     */
    public function createApplication()
    {
        $app = parent::createApplication();

        $this->ensureApprovedTestSchema($app);

        return $app;
    }

    protected function ensureApprovedTestSchema($app): void
    {
        $config = $app['config'];
        $connection = $config->get('database.default');
        $configuredDatabase = $config->get("database.connections.{$connection}.database");
        $databaseUrl = $config->get("database.connections.{$connection}.url");

        if ($connection !== 'mysql'
            || $configuredDatabase !== 'layrate_qa_20261027'
            || filled($databaseUrl)
            || $config->get("database.connections.{$connection}.host") !== '127.0.0.1'
            || (string) $config->get("database.connections.{$connection}.port") !== '3306') {
            throw new RuntimeException('Refusing RefreshDatabase: test database must be the approved isolated local schema layrate_qa_20261027. If the config is cached, run php artisan config:clear first.');
        }

        $actualDatabase = $app['db']->connection($connection)->selectOne('select database() as name')->name ?? null;

        if ($actualDatabase !== 'layrate_qa_20261027') {
            throw new RuntimeException("Refusing RefreshDatabase: connected schema '{$actualDatabase}' is not the approved isolated test schema.");
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
