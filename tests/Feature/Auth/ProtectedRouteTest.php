<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProtectedRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_authenticated_pages(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->get(route('profile.edit'))->assertRedirect(route('login'));
        $this->get(route('security.edit'))->assertRedirect(route('login'));
        $this->get(route('appearance.edit'))->assertRedirect(route('login'));
    }

    public function test_guests_cannot_mutate_account_data(): void
    {
        $this->patch(route('profile.update'), [
            'name' => 'Guest',
            'email' => 'guest@example.com',
        ])->assertRedirect(route('login'));

        $this->put(route('preferences.update'), [
            'timezone' => 'UTC',
            'default_market_key' => 'global_en',
        ])->assertRedirect(route('login'));

        $this->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login'));

        $this->delete(route('profile.destroy'), [
            'password' => 'password',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('users', 0);
    }
}
