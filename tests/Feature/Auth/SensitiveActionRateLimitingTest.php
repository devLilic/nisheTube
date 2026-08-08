<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SensitiveActionRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_reset_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('password.update'), [
                'token' => 'invalid-token',
                'email' => 'user@example.com',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post(route('password.update'), [
            'token' => 'invalid-token',
            'email' => 'user@example.com',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertTooManyRequests();
    }

    public function test_password_confirmation_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->actingAs($user)
                ->post(route('password.confirm.store'), [
                    'password' => 'wrong-password',
                ])
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($user)
            ->post(route('password.confirm.store'), [
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests();
    }

    public function test_profile_update_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $this->actingAs($user)
                ->patch(route('profile.update'), [
                    'name' => '',
                    'email' => $user->email,
                ])
                ->assertSessionHasErrors('name');
        }

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => '',
                'email' => $user->email,
            ])
            ->assertTooManyRequests();
    }

    public function test_preference_update_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $this->actingAs($user)
                ->put(route('preferences.update'), [
                    'timezone' => 'Invalid/Timezone',
                    'default_market_key' => 'global_en',
                ])
                ->assertSessionHasErrors('timezone');
        }

        $this->actingAs($user)
            ->put(route('preferences.update'), [
                'timezone' => 'Invalid/Timezone',
                'default_market_key' => 'global_en',
            ])
            ->assertTooManyRequests();
    }

    public function test_password_update_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->actingAs($user)
                ->put(route('user-password.update'), [
                    'current_password' => 'wrong-password',
                    'password' => 'new-password',
                    'password_confirmation' => 'new-password',
                ])
                ->assertSessionHasErrors('current_password');
        }

        $this->actingAs($user)
            ->put(route('user-password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertTooManyRequests();
    }

    public function test_account_deletion_attempts_are_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->actingAs($user)
                ->delete(route('profile.destroy'), [
                    'password' => 'wrong-password',
                ])
                ->assertSessionHasErrors('password');
        }

        $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'wrong-password',
            ])
            ->assertTooManyRequests();

        $this->assertNotNull($user->fresh());
    }
}
