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
        $normalizedDatabaseName = str_replace('\\', '/', strtolower($databaseName));

        if ($defaultConnection === 'sqlite') {
            if (! str_ends_with($normalizedDatabaseName, 'database/testing.sqlite')) {
                throw new RuntimeException("Testing must use the dedicated SQLite database file [database/testing.sqlite]. Current database: [{$databaseName}].");
            }

            return;
        }

        if ($databaseName === '' || ! str_contains(strtolower($databaseName), 'test')) {
            throw new RuntimeException("Testing must use a dedicated test database. Current connection [{$defaultConnection}] is configured with [{$databaseName}].");
        }
    }
}
