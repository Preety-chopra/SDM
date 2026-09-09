<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ApplicationShellTest extends TestCase
{
    use RefreshDatabase;

    public function test_required_input_labels_render_an_accessible_red_asterisk(): void
    {
        $html = Blade::render('<x-input-label value="Contractor" required />');

        $this->assertStringContainsString('Contractor', $html);
        $this->assertStringContainsString('data-required-marker', $html);
        $this->assertStringContainsString('!text-red-600', $html);
        $this->assertStringContainsString('aria-hidden="true"', $html);
    }

    public function test_every_layout_page_provides_a_browser_title(): void
    {
        $missingTitles = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => preg_match('/<x-(?:app|guest)-layout/', $file->getContents()) === 1)
            ->reject(fn ($file) => preg_match('/<x-(?:app|guest)-layout\s+(?::)?title=/', $file->getContents()) === 1)
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $missingTitles, 'Views missing a browser title: '.implode(', ', $missingTitles));
    }

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

    public function test_back_button_uses_the_previous_in_app_page_with_a_dashboard_fallback(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        $this->actingAs($administrator)
            ->from(route('contracts.index'))
            ->get(route('roles.create'))
            ->assertOk()
            ->assertSee('data-global-back href="'.route('contracts.index').'"', false);

        $this->actingAs($administrator)
            ->from('https://example.invalid/outside')
            ->get(route('roles.index'))
            ->assertOk()
            ->assertSee('data-global-back href="'.route('dashboard').'"', false);
    }
}
