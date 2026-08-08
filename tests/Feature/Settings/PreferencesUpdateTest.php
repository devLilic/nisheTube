<?php

namespace Tests\Feature\Settings;

use App\Models\Market;
use App\Models\User;
use App\Policies\UserPolicy;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PreferencesUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_user_can_update_their_research_preferences(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->put(route('preferences.update'), [
            'timezone' => 'Europe/Bucharest',
            'default_market_key' => 'ro_ro',
            'default_result_depth' => 100,
        ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Europe/Bucharest', $user->timezone);
        $this->assertSame('ro_ro', $user->default_market_key);
        $this->assertSame(100, $user->default_result_depth);

        $this->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.user.timezone', 'Europe/Bucharest')
                ->where('auth.user.default_market_key', 'ro_ro')
                ->where('auth.user.default_result_depth', 100));
    }

    public function test_preferences_require_a_valid_timezone_and_supported_market(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('preferences.update'), [
                'timezone' => 'Moon/Sea_of_Tranquility',
                'default_market_key' => 'unsupported_market',
            ]);

        $response
            ->assertSessionHasErrors(['timezone', 'default_market_key'])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('Europe/Chisinau', $user->refresh()->timezone);
        $this->assertNull($user->default_market_key);
    }

    public function test_disabled_market_cannot_be_selected_as_the_default(): void
    {
        $user = User::factory()->create();

        Market::query()->where('key', 'ru_ru')->update(['is_enabled' => false]);

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('preferences.update'), [
                'timezone' => 'Europe/Chisinau',
                'default_market_key' => 'ru_ru',
            ])
            ->assertSessionHasErrors('default_market_key')
            ->assertRedirect(route('profile.edit'));

        $this->assertNull($user->refresh()->default_market_key);
    }

    public function test_default_result_depth_must_be_a_supported_value(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('profile.edit'))
            ->put(route('preferences.update'), [
                'timezone' => 'Europe/Chisinau',
                'default_market_key' => 'global_en',
                'default_result_depth' => 75,
            ])
            ->assertSessionHasErrors('default_result_depth')
            ->assertRedirect(route('profile.edit'));

        $this->assertSame(50, $user->refresh()->default_result_depth);
    }

    public function test_guest_cannot_update_preferences(): void
    {
        $this->put(route('preferences.update'), [
            'timezone' => 'Europe/Chisinau',
            'default_market_key' => 'global_en',
        ])->assertRedirect(route('login'));
    }

    public function test_preferences_can_clear_the_default_market(): void
    {
        $user = User::factory()->create([
            'default_market_key' => 'ru_ru',
        ]);

        $this->actingAs($user)->put(route('preferences.update'), [
            'timezone' => 'UTC',
            'default_market_key' => null,
        ])->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('UTC', $user->timezone);
        $this->assertNull($user->default_market_key);
    }

    public function test_preference_updates_ignore_a_foreign_user_identifier(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create([
            'timezone' => 'Europe/Moscow',
            'default_market_key' => 'ru_ru',
        ]);

        $this->actingAs($user)->put(route('preferences.update'), [
            'user_id' => $otherUser->id,
            'timezone' => 'Europe/Bucharest',
            'default_market_key' => 'ro_ro',
        ])->assertSessionHasNoErrors();

        $this->assertSame('Europe/Bucharest', $user->refresh()->timezone);
        $this->assertSame('Europe/Moscow', $otherUser->refresh()->timezone);
        $this->assertSame('ru_ru', $otherUser->default_market_key);
    }

    public function test_user_policy_rejects_a_different_user(): void
    {
        $authenticatedUser = User::factory()->create();
        $otherUser = User::factory()->create();
        $policy = new UserPolicy;

        $this->assertFalse($policy->update($authenticatedUser, $otherUser));
        $this->assertFalse($policy->delete($authenticatedUser, $otherUser));
        $this->assertTrue($policy->update($authenticatedUser, $authenticatedUser));
    }
}
