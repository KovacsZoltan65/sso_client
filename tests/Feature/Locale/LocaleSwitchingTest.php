<?php

namespace Tests\Feature\Locale;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleSwitchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shares_the_default_locale_with_inertia_responses(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale.current', config('app.locale'))
                ->where('locale.fallback', config('app.fallback_locale'))
                ->where('locale.available', config('app.available_locales')));
    }

    public function test_it_stores_the_selected_locale_in_session_and_applies_it_on_the_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('locale.update'), [
                'locale' => 'en',
            ])
            ->assertRedirect(route('dashboard'));

        $this->assertSame('en', session('locale'));

        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale.current', 'en'));
    }

    public function test_it_rejects_unsupported_locale_values(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('dashboard'))
            ->post(route('locale.update'), [
                'locale' => 'de',
            ])
            ->assertRedirect(route('dashboard'))
            ->assertSessionHasErrors('locale');
    }
}
