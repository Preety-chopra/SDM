<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pages_use_sidebar_account_dropdown_and_global_back_navigation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('data-app-shell', false)
            ->assertSee('data-app-sidebar', false)
            ->assertSee('data-sidebar-toggle', false)
            ->assertSee('class="absolute right-3 top-3', false)
            ->assertDontSee('>Collapse sidebar</span>', false)
            ->assertSee('Change password')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('data-global-back href="'.route('dashboard').'"', false);

        $response->assertSeeInOrder(['data-page-heading', 'data-global-back'], false);
    }

    public function test_dashboard_does_not_render_a_redundant_back_button(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-global-back', false);
    }

    public function test_nested_resource_pages_backtrack_to_their_index_before_dashboard(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->actingAs($administrator)
            ->get(route('roles.create'))
            ->assertOk()
            ->assertSee('data-global-back href="'.route('roles.index').'"', false);

        $this->actingAs($administrator)
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('data-global-back href="'.route('dashboard').'"', false);
    }
}
