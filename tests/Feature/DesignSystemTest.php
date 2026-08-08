<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DesignSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_view_the_design_system_showcase(): void
    {
        $this->get(route('design-system'))->assertRedirect(route('login'));
    }

    public function test_authenticated_verified_users_can_view_the_design_system_showcase(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('design-system'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('design-system'));
    }
}
