<?php

namespace Tests\Feature\Emergency;

use App\Data\Emergency\EmergencyActivationData;
use App\Models\EmergencyAccount;
use App\Models\User;
use App\Services\Emergency\EmergencyModeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class EmergencyModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('emergency.enabled', true);
        config()->set('emergency.require_manual_activation', true);
        config()->set('emergency.healthcheck_enabled', false);
        config()->set('emergency.allow_view_users', true);
        config()->set('emergency.allow_view_companies', true);
        config()->set('emergency.allow_view_audit_logs', true);
    }

    #[Group('security')]
    public function test_emergency_routes_are_hidden_when_feature_is_disabled(): void
    {
        config()->set('emergency.enabled', false);

        $this->get('/emergency/status')->assertNotFound();
        $this->get('/emergency/login')->assertNotFound();
    }

    #[Group('security')]
    public function test_emergency_status_is_available_but_login_is_not_available_while_mode_is_inactive(): void
    {
        $this->get('/emergency/status')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Emergency/Status')
                ->where('status.state', EmergencyModeService::STATE_NORMAL)
                ->where('status.emergencyLoginAvailable', false)
            );

        $this->post('/emergency/login', [
            'username' => 'operator',
            'password' => 'secret',
        ])->assertSessionHasErrors('username');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.security',
            'event' => 'client_security.emergency_action.blocked',
        ]);
    }

    #[Group('security')]
    public function test_active_emergency_mode_allows_valid_emergency_login(): void
    {
        app(EmergencyModeService::class)->activate(new EmergencyActivationData(
            reason: 'Upstream outage',
            operator: 'ops@example.test',
        ));

        $account = EmergencyAccount::query()->create([
            'username' => 'operator',
            'password' => Hash::make('secret-pass'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->post('/emergency/login', [
            'username' => 'operator',
            'password' => 'secret-pass',
        ])->assertRedirect('/emergency/dashboard');

        $this->assertAuthenticated('emergency');
        $this->assertGuest('web');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.emergency_login.succeeded',
            'description' => 'Emergency login succeeded.',
        ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.emergency_session.established',
            'description' => 'Emergency session established.',
        ]);

        $activity = Activity::query()
            ->where('event', 'client_auth.emergency_login.succeeded')
            ->latest()
            ->firstOrFail();

        $this->assertSame($account->id, $activity->properties->get('emergency_account_id'));
        $this->assertArrayNotHasKey('password', $activity->properties->toArray());
    }

    #[Group('security')]
    public function test_invalid_emergency_login_is_rejected_and_audited(): void
    {
        app(EmergencyModeService::class)->activate(new EmergencyActivationData(
            reason: 'Upstream outage',
            operator: 'ops@example.test',
        ));

        EmergencyAccount::query()->create([
            'username' => 'operator',
            'password' => Hash::make('secret-pass'),
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $this->post('/emergency/login', [
            'username' => 'operator',
            'password' => 'wrong-pass',
        ])->assertSessionHasErrors('username');

        $this->assertGuest('emergency');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.auth',
            'event' => 'client_auth.emergency_login.failed',
            'description' => 'Emergency login failed.',
        ]);
    }

    #[Group('security')]
    public function test_expired_emergency_account_cannot_log_in(): void
    {
        app(EmergencyModeService::class)->activate(new EmergencyActivationData(
            reason: 'Upstream outage',
            operator: 'ops@example.test',
        ));

        EmergencyAccount::query()->create([
            'username' => 'expired-operator',
            'password' => Hash::make('secret-pass'),
            'role' => 'viewer',
            'is_active' => true,
            'expires_at' => now()->subMinute(),
        ]);

        $this->post('/emergency/login', [
            'username' => 'expired-operator',
            'password' => 'secret-pass',
        ])->assertSessionHasErrors('username');

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.security',
            'event' => 'client_security.emergency_access.denied',
            'description' => 'Emergency access denied.',
        ]);
    }

    #[Group('security')]
    public function test_emergency_session_cannot_access_normal_web_routes(): void
    {
        app(EmergencyModeService::class)->activate(new EmergencyActivationData(
            reason: 'Upstream outage',
            operator: 'ops@example.test',
        ));

        EmergencyAccount::query()->create([
            'username' => 'viewer',
            'password' => Hash::make('secret-pass'),
            'role' => 'viewer',
            'is_active' => true,
        ]);

        $this->post('/emergency/login', [
            'username' => 'viewer',
            'password' => 'secret-pass',
        ])->assertRedirect('/emergency/dashboard');

        $this->get('/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'A munkamenet hianyzik vagy lejart. Jelentkezz be ujra.');
    }

    #[Group('security')]
    public function test_normal_web_session_cannot_access_emergency_dashboard(): void
    {
        app(EmergencyModeService::class)->activate(new EmergencyActivationData(
            reason: 'Upstream outage',
            operator: 'ops@example.test',
        ));

        $this->actingAs(User::factory()->create())
            ->get('/emergency/dashboard')
            ->assertRedirect(route('emergency.login'));
    }

    #[Group('security')]
    public function test_emergency_admin_can_deactivate_mode(): void
    {
        app(EmergencyModeService::class)->activate(new EmergencyActivationData(
            reason: 'Upstream outage',
            operator: 'ops@example.test',
        ));

        $account = EmergencyAccount::query()->create([
            'username' => 'admin',
            'password' => Hash::make('secret-pass'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->actingAs($account, 'emergency')
            ->post('/emergency/deactivate', [
                'reason' => 'SSO restored',
                'operator' => 'ops@example.test',
            ])
            ->assertRedirect(route('emergency.status'));

        $this->assertFalse(app(EmergencyModeService::class)->isEmergencyActive());

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.security',
            'event' => 'client_security.emergency_mode.disabled',
            'description' => 'Client emergency mode disabled.',
        ]);
    }

    #[Group('security')]
    public function test_expired_activation_is_downgraded_and_audited(): void
    {
        Cache::put((string) config('emergency.state_cache_key'), [
            'reason' => 'Outage',
            'activated_by' => 'ops@example.test',
            'activated_at' => now()->subHours(2)->toIso8601String(),
            'expires_at' => now()->subMinute()->toIso8601String(),
            'reference_id' => 'ref-expired',
        ], now()->addMinutes(10));

        $this->get('/emergency/status')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('status.state', EmergencyModeService::STATE_NORMAL)
                ->where('status.activationReference', null)
            );

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.security',
            'event' => 'client_security.emergency_mode.expired',
            'description' => 'Client emergency mode expired.',
        ]);
    }
}
