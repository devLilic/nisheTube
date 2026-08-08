<?php

namespace Tests\Feature\Settings;

use App\Models\Market;
use App\Models\User;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->has('preferenceOptions.timezones')
            ->where('preferenceOptions.resultDepths', [25, 50, 100, 200])
            ->has('preferenceOptions.markets', 3)
            ->where('preferenceOptions.markets.0.value', 'global_en')
            ->where('preferenceOptions.markets.1.value', 'ro_ro')
            ->where('preferenceOptions.markets.2.value', 'ru_ru'));
    }

    public function test_profile_page_only_lists_enabled_markets(): void
    {
        $user = User::factory()->create();

        Market::query()
            ->where('key', 'ro_ro')
            ->update(['is_enabled' => false]);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('preferenceOptions.markets', 2)
                ->where('preferenceOptions.markets.0.value', 'global_en')
                ->where('preferenceOptions.markets.1.value', 'ru_ru'));
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_updates_are_scoped_to_the_authenticated_user(): void
    {
        $user = User::factory()->create(['name' => 'Owner']);
        $otherUser = User::factory()->create(['name' => 'Other User']);

        $this->actingAs($user)->patch(route('profile.update'), [
            'user_id' => $otherUser->id,
            'name' => 'Updated Owner',
            'email' => $user->email,
        ])->assertSessionHasNoErrors();

        $this->assertSame('Updated Owner', $user->refresh()->name);
        $this->assertSame('Other User', $otherUser->refresh()->name);
    }

    public function test_user_can_delete_their_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['research_context' => 'private'])
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home'))
            ->assertSessionMissing('research_context');

        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_correct_password_must_be_provided_to_delete_account()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from(route('profile.edit'))
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->fresh());
    }
}
