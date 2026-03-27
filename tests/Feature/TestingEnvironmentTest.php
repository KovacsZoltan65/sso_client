<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestingEnvironmentTest extends TestCase
{
    public function test_testing_environment_uses_the_dedicated_test_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertStringEndsWith('database/testing.sqlite', str_replace('\\', '/', (string) DB::connection()->getDatabaseName()));
    }
}
