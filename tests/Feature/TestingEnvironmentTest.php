<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class TestingEnvironmentTest extends TestCase
{
    #[Group('security')]
    public function test_testing_environment_uses_the_dedicated_test_database(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', config('database.default'));
        $this->assertSame('sso_client_test', (string) DB::connection()->getDatabaseName());
    }
}
