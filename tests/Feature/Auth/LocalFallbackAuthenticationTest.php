<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\LocalFallbackAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class LocalFallbackAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('sso.server_base_url', 'https://sso-server.test');
        config()->set('sso.authorize_endpoint', '/oauth/authorize');
        config()->set('sso.token_endpoint', '/api/oauth/token');
        config()->set('sso.userinfo_endpoint', '/api/oauth/userinfo');
        config()->set('sso.client_id', 'portal-client');
        config()->set('sso.client_secret', 'secret-value');
        config()->set('sso.redirect_uri', 'http://sso-client.test/auth/sso/callback');
        config()->set('sso.scopes', ['openid', 'profile', 'email']);
        config()->set('sso.local_auth_incident_id_required', false);
    }

    #[Group('security')]
    public function test_local_login_routes_are_blocked_when_feature_flag_is_disabled(): void
    {
        $this->get('/local-login')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'A local fallback login nincs engedelyezve ebben a kornyezetben.');

        $this->post('/local-login', [
            'email' => 'fallback@example.test',
            'password' => 'secret',
        ])->assertRedirect(route('login'));
    }

    #[Group('security')]
    public function test_healthy_sso_keeps_local_login_blocked_and_shows_warning_on_login_page(): void
    {
        config()->set('sso.local_auth_enabled', true);
        config()->set('sso.local_auth_incident_id', 'INC-42');

        Http::fake([
            'https://sso-server.test/oauth/authorize' => Http::response('', 302),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('decision.featureEnabled', true)
                ->where('decision.blockedBecauseSsoHealthy', true)
                ->where('decision.currentlyAllowed', false)
                ->where('decision.incidentId', 'INC-42')
            );

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.security',
            'event' => 'client_security.local_fallback.sso_reachable_warning',
        ]);

        $this->get('/local-login')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'A local fallback login jelenleg tiltott, mert az SSO szerver elerheto.');

        $this->post('/local-login', [
            'email' => 'fallback@example.test',
            'password' => 'secret',
        ])->assertStatus(302)
            ->assertHeader('Location', route('login'))
            ->assertSessionHas('error', 'A local fallback login mar nem erheto el, mert az SSO szerver ujra elerhetove valt.');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.local_fallback.page_blocked',
        ]);
    }

    #[Group('security')]
    public function test_unreachable_sso_allows_local_login_flow_for_allowlisted_local_only_users(): void
    {
        config()->set('sso.local_auth_enabled', true);
        config()->set('sso.local_auth_failure_threshold', 1);
        config()->set('sso.local_auth_incident_id', 'INC-77');

        Http::fake([
            'https://sso-server.test/oauth/authorize' => Http::failedConnection(),
        ]);

        $fallbackUser = User::factory()->create([
            'email' => 'fallback@example.test',
            'password' => Hash::make('secret-pass'),
            'sso_user_id' => null,
            'fallback_auth_enabled' => true,
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('decision.featureEnabled', true)
                ->where('decision.currentlyAllowed', true)
                ->where('decision.reachability.status', 'unreachable')
                ->where('decision.fallbackReason', 'unreachable_allowed')
            );

        $this->get('/local-login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/LocalFallbackLogin')
                ->where('decision.currentlyAllowed', true)
            );

        $this->post('/local-login', [
            'email' => 'fallback@example.test',
            'password' => 'secret-pass',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($fallbackUser);
        $this->assertSame(
            LocalFallbackAuthService::SESSION_MODE_LOCAL_FALLBACK,
            session(config('sso.session_mode_session_key')),
        );

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.sessionMode', 'local_fallback')
                ->where('fallback.banner.visible', true)
                ->where('fallback.banner.incidentId', 'INC-77')
            );

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.local_fallback.page_allowed',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.local_fallback.login_succeeded',
        ]);
    }

    #[Group('security')]
    public function test_degraded_sso_keeps_fallback_blocked_when_allow_degraded_flag_is_disabled(): void
    {
        config()->set('sso.local_auth_enabled', true);
        config()->set('sso.local_auth_allow_degraded', false);
        config()->set('sso.local_auth_failure_threshold', 3);

        Http::fake([
            'https://sso-server.test/oauth/authorize' => Http::response('', 500),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('decision.reachability.status', 'degraded')
                ->where('decision.currentlyAllowed', false)
                ->where('decision.blockedBecauseDegraded', true)
                ->where('decision.allowDegraded', false)
            );

        $this->get('/local-login')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'A local fallback login jelenleg tiltott, mert a degraded fallback nincs engedelyezve.');

        $this->post('/local-login', [
            'email' => 'fallback@example.test',
            'password' => 'secret',
        ])->assertStatus(302)
            ->assertSessionHas('error', 'A local fallback login jelenleg tiltott, mert a degraded fallback nincs engedelyezve.');
    }

    #[Group('security')]
    public function test_degraded_sso_can_allow_fallback_when_allow_degraded_flag_is_enabled(): void
    {
        config()->set('sso.local_auth_enabled', true);
        config()->set('sso.local_auth_allow_degraded', true);
        config()->set('sso.local_auth_failure_threshold', 2);
        config()->set('sso.local_auth_incident_id', 'INC-DEG-1');

        Http::fake([
            'https://sso-server.test/oauth/authorize' => Http::response('', 500),
        ]);

        $fallbackUser = User::factory()->create([
            'email' => 'fallback@example.test',
            'password' => Hash::make('secret-pass'),
            'sso_user_id' => null,
            'fallback_auth_enabled' => true,
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('decision.reachability.status', 'degraded')
                ->where('decision.currentlyAllowed', true)
                ->where('decision.fallbackReason', 'degraded_allowed')
                ->where('decision.allowDegraded', true)
            );

        $this->get('/local-login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/LocalFallbackLogin')
                ->where('decision.currentlyAllowed', true)
                ->where('decision.fallbackReason', 'degraded_allowed')
            );

        $this->post('/local-login', [
            'email' => 'fallback@example.test',
            'password' => 'secret-pass',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($fallbackUser);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.local_fallback.page_allowed',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.local_fallback.login_succeeded',
        ]);
    }

    #[Group('security')]
    public function test_invalid_local_fallback_login_is_audited_and_rejected(): void
    {
        config()->set('sso.local_auth_enabled', true);
        config()->set('sso.local_auth_failure_threshold', 1);

        Http::fake([
            'https://sso-server.test/oauth/authorize' => Http::failedConnection(),
        ]);

        User::factory()->create([
            'email' => 'fallback@example.test',
            'password' => Hash::make('secret-pass'),
            'sso_user_id' => null,
            'fallback_auth_enabled' => true,
        ]);

        $this->from('/local-login')
            ->post('/local-login', [
                'email' => 'fallback@example.test',
                'password' => 'wrong-pass',
            ])
            ->assertRedirect('/local-login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.local_fallback.login_failed',
        ]);
    }

    #[Group('security')]
    public function test_local_fallback_submit_is_blocked_if_sso_recovers_between_page_load_and_submit(): void
    {
        config()->set('sso.local_auth_enabled', true);
        config()->set('sso.local_auth_failure_threshold', 1);

        $probeAttempt = 0;
        Http::fake(function () use (&$probeAttempt) {
            $probeAttempt++;

            if ($probeAttempt === 1) {
                throw new \Illuminate\Http\Client\ConnectionException('connection failed');
            }

            return Http::response('', 302);
        });

        User::factory()->create([
            'email' => 'fallback@example.test',
            'password' => Hash::make('secret-pass'),
            'sso_user_id' => null,
            'fallback_auth_enabled' => true,
        ]);

        $this->get('/local-login')->assertOk();

        $this->post('/local-login', [
            'email' => 'fallback@example.test',
            'password' => 'secret-pass',
        ])->assertStatus(302)
            ->assertHeader('Location', route('login'))
            ->assertSessionHas('error', 'A local fallback login mar nem erheto el, mert az SSO szerver ujra elerhetove valt.');

        $this->assertGuest();
    }

    #[Group('security')]
    public function test_local_fallback_submit_is_blocked_if_degraded_page_recovers_to_healthy_before_submit(): void
    {
        config()->set('sso.local_auth_enabled', true);
        config()->set('sso.local_auth_allow_degraded', true);
        config()->set('sso.local_auth_failure_threshold', 2);

        $probeAttempt = 0;
        Http::fake(function () use (&$probeAttempt) {
            $probeAttempt++;

            if ($probeAttempt === 1) {
                return Http::response('', 500);
            }

            return Http::response('', 302);
        });

        User::factory()->create([
            'email' => 'fallback@example.test',
            'password' => Hash::make('secret-pass'),
            'sso_user_id' => null,
            'fallback_auth_enabled' => true,
        ]);

        $this->get('/local-login')->assertOk();

        $this->post('/local-login', [
            'email' => 'fallback@example.test',
            'password' => 'secret-pass',
        ])->assertRedirect(route('login'))
            ->assertSessionHas('error', 'A local fallback login mar nem erheto el, mert az SSO szerver ujra elerhetove valt.');

        $this->assertGuest();
    }

    #[Group('security')]
    public function test_maintenance_policy_remains_blocked_even_when_allow_degraded_is_enabled(): void
    {
        config()->set('sso.local_auth_enabled', true);
        config()->set('sso.local_auth_allow_degraded', true);

        Http::fake([
            'https://sso-server.test/oauth/authorize' => Http::response('<html>maintenance</html>', 503),
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('decision.reachability.status', 'maintenance')
                ->where('decision.currentlyAllowed', false)
                ->where('decision.blockedBecauseMaintenance', true)
            );

        $this->get('/local-login')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'A local fallback login jelenleg tiltott, mert az SSO szerver karbantartas alatt van.');
    }

    #[Group('security')]
    public function test_fallback_session_cannot_access_restricted_admin_routes_even_with_permissions(): void
    {
        Permission::findOrCreate('users.view', 'web');

        $user = User::factory()->create([
            'fallback_auth_enabled' => true,
        ]);
        $user->givePermissionTo('users.view');

        $this->actingAs($user)
            ->withSession([config('sso.session_mode_session_key') => LocalFallbackAuthService::SESSION_MODE_LOCAL_FALLBACK])
            ->get('/users')
            ->assertRedirect(route('dashboard'))
            ->assertSessionHas('error', 'A local fallback session csak korlatozott, olvasasi szintu oldalakhoz ferhet hozza.');

        $this->actingAs($user)
            ->withSession([config('sso.session_mode_session_key') => LocalFallbackAuthService::SESSION_MODE_LOCAL_FALLBACK])
            ->getJson('/api/users')
            ->assertForbidden()
            ->assertJsonPath('meta.session_mode', 'local_fallback');
    }
}
