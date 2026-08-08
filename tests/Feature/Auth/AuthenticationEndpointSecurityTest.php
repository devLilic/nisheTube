<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationEndpointSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_not_available_from_a_non_loopback_address(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])
            ->get(route('register'))
            ->assertForbidden();
    }

    public function test_registration_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('register.store'), [
                'name' => '',
                'email' => 'invalid',
                'password' => 'short',
                'password_confirmation' => 'different',
            ])->assertRedirect();
        }

        $this->post(route('register.store'), [
            'name' => '',
            'email' => 'invalid',
            'password' => 'short',
            'password_confirmation' => 'different',
        ])->assertTooManyRequests();
    }

    public function test_password_reset_link_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('password.email'), [
                'email' => 'missing@example.com',
            ])->assertRedirect();
        }

        $this->post(route('password.email'), [
            'email' => 'missing@example.com',
        ])->assertTooManyRequests();
    }
}
