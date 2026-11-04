<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Line;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class LineDropdownOrderingTest extends TestCase
{
    use RefreshDatabase;

    #[TestWith(['sdm-memos.index', []])]
    #[TestWith(['stations.index', []])]
    #[TestWith(['users.create_any', []])]
    #[TestWith(['users.create', ['role' => 'station_manager']])]
    #[TestWith(['contracts.create', []])]
    public function test_line_choices_use_natural_code_order_with_ael_after_numbered_lines(string $routeName, array $parameters): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);
        $this->createLinesWithStations();

        $this->actingAs($administrator)->get(route($routeName, $parameters))
            ->assertOk()
            ->assertViewHas('lines', fn ($lines): bool => $lines->pluck('code')->values()->all() === [
                '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'AEL',
            ]);
    }

    public function test_edit_user_line_choices_use_natural_code_order(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);
        $managedUser = User::factory()->create();
        $managedUser->assignRole(UserRole::StationManager->value);
        $this->createLinesWithStations();

        $this->actingAs($administrator)->get(route('users.edit', $managedUser))
            ->assertOk()
            ->assertViewHas('lines', fn ($lines): bool => $lines->pluck('code')->values()->all() === [
                '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', 'AEL',
            ]);
    }

    private function createLinesWithStations(): void
    {
        foreach (['10', 'AEL', '2', '9', '1', '8', '3', '7', '4', '6', '5'] as $index => $code) {
            $line = Line::query()->create([
                'code' => $code,
                'name' => 'Network '.chr(65 + $index),
                'is_active' => true,
            ]);

            Station::query()->create([
                'code' => 'STATION-'.$code,
                'name' => 'Station '.$code,
                'line_id' => $line->id,
                'is_active' => true,
            ]);
        }
    }
}
