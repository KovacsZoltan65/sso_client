<?php

namespace Tests\Feature\Auth;

use App\Services\Sso\SsoReachabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

class SsoReachabilityStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sso.server_base_url', 'https://sso-server.test');
        config()->set('sso.authorize_endpoint', '/oauth/authorize');
        config()->set('sso.readiness_endpoint', null);
        config()->set('sso.local_auth_failure_threshold', 2);

        Cache::flush();
    }

    #[Group('security')]
    public function test_200_probe_is_classified_as_healthy(): void
    {
        Http::fake([
            'https://sso-server.test/oauth/authorize' => Http::response('', 302),
        ]);

        $result = app(SsoReachabilityService::class)->refresh();

        $this->assertSame(SsoReachabilityService::STATUS_HEALTHY, $result->status);
        $this->assertTrue($result->isReachable);
        $this->assertFalse($result->isMaintenance);
        $this->assertSame(302, $result->httpStatus);
    }

    #[Group('security')]
    public function test_503_probe_is_classified_as_maintenance(): void
    {
        Http::fake([
            'https://sso-server.test/oauth/authorize' => Http::response('<html>maintenance</html>', 503, [
                'Retry-After' => '120',
            ]),
        ]);

        $result = app(SsoReachabilityService::class)->refresh();

        $this->assertSame(SsoReachabilityService::STATUS_MAINTENANCE, $result->status);
        $this->assertTrue($result->isReachable);
        $this->assertTrue($result->isMaintenance);
        $this->assertSame(503, $result->httpStatus);
        $this->assertSame('120', $result->retryAfter);
    }

    #[Group('security')]
    public function test_connection_failure_is_classified_as_unreachable(): void
    {
        config()->set('sso.local_auth_failure_threshold', 1);

        Http::fake([
            'https://sso-server.test/oauth/authorize' => Http::failedConnection(),
        ]);

        $result = app(SsoReachabilityService::class)->refresh();

        $this->assertSame(SsoReachabilityService::STATUS_UNREACHABLE, $result->status);
        $this->assertFalse($result->isReachable);
        $this->assertFalse($result->isMaintenance);
        $this->assertNull($result->httpStatus);
    }

    #[Group('security')]
    public function test_login_page_exposes_maintenance_state_separately(): void
    {
        Http::fake([
            'https://sso-server.test/oauth/authorize' => Http::response('<html>maintenance</html>', 503, [
                'Retry-After' => '60',
            ]),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('decision.reachability.status', 'maintenance')
                ->where('decision.reachability.isMaintenance', true)
                ->where('decision.reachability.retryAfter', '60')
            );
    }

    #[Group('security')]
    public function test_login_page_exposes_unreachable_state_separately(): void
    {
        config()->set('sso.local_auth_failure_threshold', 1);

        Http::fake([
            'https://sso-server.test/oauth/authorize' => Http::failedConnection(),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('decision.reachability.status', 'unreachable')
                ->where('decision.reachability.isReachable', false)
            );
    }
}
