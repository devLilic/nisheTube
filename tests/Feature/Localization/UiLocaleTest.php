<?php

namespace Tests\Feature\Localization;

use App\Domain\Localization\Enums\UiLocale;
use App\Domain\Localization\Services\ResolveUiLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UiLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_defaults_to_romanian_with_explicit_english_fallback(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('lang="ro"', false)
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ro')
                ->where('fallbackLocale', 'en')
                ->where('supportedLocales', ['ro', 'en']));
    }

    public function test_guest_locale_persists_in_the_session_and_changes_html_language(): void
    {
        $this->from(route('home'))
            ->put(route('locale.update'), ['ui_locale' => 'en'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas(ResolveUiLocale::SESSION_KEY, 'en')
            ->assertInertiaFlash('toast.message', 'Interface language updated.')
            ->assertRedirect(route('home'));

        $this->get(route('home'))
            ->assertSee('lang="en"', false)
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));
    }

    public function test_invalid_guest_locale_uses_localized_validation_and_keeps_romanian(): void
    {
        $this->from(route('home'))
            ->put(route('locale.update'), ['ui_locale' => 'de'])
            ->assertSessionHasErrors([
                'ui_locale' => 'Limba interfeței selectată nu este acceptată.',
            ])
            ->assertRedirect(route('home'));

        $this->assertSame('ro', session(ResolveUiLocale::SESSION_KEY, 'ro'));
    }

    public function test_english_guest_locale_uses_english_validation_fallback(): void
    {
        $this->withSession([ResolveUiLocale::SESSION_KEY => 'en'])
            ->from(route('home'))
            ->put(route('locale.update'), ['ui_locale' => 'de'])
            ->assertSessionHasErrors([
                'ui_locale' => 'The selected interface language is not supported.',
            ])
            ->assertRedirect(route('home'));
    }

    public function test_authenticated_preference_has_priority_over_guest_session(): void
    {
        $user = User::factory()->create(['ui_locale' => UiLocale::Romanian]);

        $this->withSession([ResolveUiLocale::SESSION_KEY => 'en'])
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'ro')
                ->where('auth.user.ui_locale', 'ro'));
    }

    public function test_authenticated_update_is_owner_scoped_and_does_not_change_research_preferences(): void
    {
        $user = User::factory()->create([
            'ui_locale' => UiLocale::Romanian,
            'default_market_key' => 'ro_ro',
        ]);
        $otherUser = User::factory()->create(['ui_locale' => UiLocale::Romanian]);

        $this->actingAs($user)
            ->put(route('locale.update'), [
                'ui_locale' => 'en',
                'user_id' => $otherUser->id,
                'default_market_key' => 'global_en',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas(ResolveUiLocale::SESSION_KEY, 'en');

        $this->assertSame(UiLocale::English, $user->refresh()->ui_locale);
        $this->assertSame('ro_ro', $user->default_market_key);
        $this->assertSame(UiLocale::Romanian, $otherUser->refresh()->ui_locale);
    }

    public function test_registration_adopts_the_guest_locale(): void
    {
        $this->withSession([ResolveUiLocale::SESSION_KEY => 'en'])
            ->post(route('register.store'), [
                'name' => 'Localized User',
                'email' => 'localized@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'email' => 'localized@example.com',
            'ui_locale' => 'en',
        ]);
    }

    public function test_new_users_without_a_guest_choice_default_to_romanian(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UiLocale::Romanian, $user->ui_locale);
    }
}
