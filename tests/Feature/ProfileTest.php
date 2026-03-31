<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_open_the_profile_page(): void
    {
        config()->set('sso.server_base_url', 'https://sso-server.test');

        $user = User::factory()->create([
            'name' => 'Client User',
            'email' => 'client.user@example.test',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('authUser.name', 'Client User')
                ->where('authUser.email', 'client.user@example.test')
                ->where('profileApi.enabled', true)
                ->where('profileApi.baseUrl', 'https://sso-server.test')
                ->where('profileApi.endpoints.show', 'https://sso-server.test/api/profile')
                ->where('profileApi.endpoints.update', 'https://sso-server.test/api/profile')
                ->where('profileApi.endpoints.updatePassword', 'https://sso-server.test/api/profile/password')
                ->where('profileApi.editableFields', ['name'])
                ->where('profileApi.readOnlyFields', ['email'])
            );

        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'client.account',
            'event' => 'client_profile.page.viewed',
            'description' => 'Client profile page viewed.',
        ]);

        $activity = Activity::query()
            ->where('event', 'client_profile.page.viewed')
            ->latest()
            ->firstOrFail();

        $this->assertArrayNotHasKey('access_token', $activity->properties->toArray());
    }

    public function test_guest_is_redirected_away_from_the_profile_page(): void
    {
        $this->get('/profile')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'A munkamenet hianyzik vagy lejart. Jelentkezz be ujra.');
    }
}
