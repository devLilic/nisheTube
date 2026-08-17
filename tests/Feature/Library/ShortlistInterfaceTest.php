<?php

namespace Tests\Feature\Library;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ShortlistInterfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_shortlist_is_authenticated_owner_scoped_and_has_an_explicit_empty_state(): void
    {
        $this->get(route('shortlist.index'))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('shortlist.index', ['runs' => ['foreign-id', 'foreign-id']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('shortlist/index')
                ->has('shortlist.items', 0)
                ->has('shortlist.selected', 0)
                ->where('shortlist.selection.minimum', 2)
                ->where('shortlist.selection.maximum', 5)
                ->where('shortlist.selection.state', 'needs_more'));
    }
}
