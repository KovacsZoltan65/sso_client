<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureDedicatedTestingDatabaseIsConfigured();
    }

    protected function ensureDedicatedTestingDatabaseIsConfigured(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        $defaultConnection = (string) config('database.default');
        $databaseName = (string) config("database.connections.{$defaultConnection}.database");

        if ($defaultConnection !== 'mysql') {
            throw new RuntimeException("Testing must use the mysql connection. Current connection: [{$defaultConnection}].");
        }

        if ($databaseName !== 'sso_client_testing') {
            throw new RuntimeException("Testing must use the dedicated MySQL test database [sso_client_test]. Current database: [{$databaseName}].");
        }
    }
}
