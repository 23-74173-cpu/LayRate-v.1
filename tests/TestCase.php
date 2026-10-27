<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $connection = config('database.default');
        $configuredDatabase = config("database.connections.{$connection}.database");
        $databaseUrl = config("database.connections.{$connection}.url");

        if (app()->environment('testing')) {
            if ($connection !== 'mysql'
                || $configuredDatabase !== 'layrate_qa_20261027'
                || filled($databaseUrl)
                || config("database.connections.{$connection}.host") !== '127.0.0.1'
                || (string) config("database.connections.{$connection}.port") !== '3306') {
                throw new RuntimeException('Refusing RefreshDatabase: test database must be the approved isolated local schema layrate_qa_20261027.');
            }

            $actualDatabase = $this->app['db']->connection($connection)->getDatabaseName();

            if ($actualDatabase !== 'layrate_qa_20261027') {
                throw new RuntimeException("Refusing RefreshDatabase: connected schema '{$actualDatabase}' is not the approved isolated test schema.");
            }
        }

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
