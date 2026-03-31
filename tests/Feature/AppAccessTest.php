<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AppAccessTest extends TestCase
{
    use RefreshDatabase;

    #[Group('security')]
    public function test_welcome_page_exposes_guest_auth_state(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.isAuthenticated', false)
                ->where('auth.isGuest', true)
                ->where('auth.user', null)
                ->where('auth.loginUrl', route('login'))
                ->where('auth.reauthUrl', route('auth.sso.redirect'))
            );
    }

    public function test_authenticated_user_can_open_their_account_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/my-account')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Show')
                ->where('account.email', $user->email)
                ->where('auth.isAuthenticated', true)
                ->where('auth.isGuest', false)
            );
    }

    #[Group('security')]
    public function test_permission_protected_page_redirects_back_with_flash_message_when_permission_is_missing(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from('/dashboard')
            ->get('/sso-status')
            ->assertRedirect('/dashboard')
            ->assertSessionHas('error', 'Nincs jogosultsagod a kert oldal megtekintesehez.');
    }

    public function test_permission_protected_page_is_available_when_permission_is_granted(): void
    {
        $user = User::factory()->create();
        Permission::findOrCreate('sso-status.view', 'web');
        $user->givePermissionTo('sso-status.view');

        $this->actingAs($user)
            ->get('/sso-status')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Sso/Status')
                ->where('auth.isAuthenticated', true)
                ->where('auth.user.email', $user->email)
            );
    }

    #[Group('security')]
    public function test_json_permission_failure_returns_consistent_forbidden_payload(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson('/sso-status')
            ->assertForbidden()
            ->assertJson([
                'message' => 'Forbidden.',
            ]);

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.api',
            'event' => 'client_api.request.forbidden',
            'description' => 'Client API request forbidden.',
        ]);

        $activity = Activity::query()
            ->where('event', 'client_api.request.forbidden')
            ->latest()
            ->firstOrFail();

        $this->assertArrayNotHasKey('access_token', $activity->properties->toArray());
    }
}
