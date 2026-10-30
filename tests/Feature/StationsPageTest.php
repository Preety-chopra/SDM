<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Line;
use App\Models\Station;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_stations_are_grouped_by_line_and_sorted_numerically_by_sequence_with_unsequenced_stations_last(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);
        $secondLine = Line::query()->create(['code' => 'B', 'name' => 'Second Line']);
        $firstLine = Line::query()->create(['code' => 'A', 'name' => 'First Line']);
        foreach ([
            ['code' => 'A10', 'name' => 'Alpha Tenth', 'sequence_no' => 10, 'line_id' => $firstLine->id],
            ['code' => 'A02', 'name' => 'Zulu Second', 'sequence_no' => 2, 'line_id' => $firstLine->id],
            ['code' => 'AN', 'name' => 'Aardvark Unsequenced', 'sequence_no' => null, 'line_id' => $firstLine->id],
            ['code' => 'A01', 'name' => 'Middle First', 'sequence_no' => 1, 'line_id' => $firstLine->id],
            ['code' => 'B02', 'name' => 'Alpha Second', 'sequence_no' => 2, 'line_id' => $secondLine->id],
            ['code' => 'B01', 'name' => 'Zulu First', 'sequence_no' => 1, 'line_id' => $secondLine->id],
        ] as $attributes) {
            Station::query()->create($attributes);
        }

        $this->actingAs($administrator)->get(route('stations.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'data-line-code="A"', 'Middle First', 'Zulu Second', 'Alpha Tenth', 'Aardvark Unsequenced',
                'data-line-code="B"', 'Zulu First', 'Alpha Second',
            ], false);
    }

    public function test_station_rows_display_the_stored_sequence_and_keep_edit_and_delete_controls_available(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);
        $line = Line::query()->create(['code' => 'RED', 'name' => 'Red Line']);
        $station = Station::query()->create(['code' => 'TEST', 'name' => 'Test Station', 'line_id' => $line->id, 'sequence_no' => 17]);

        $response = $this->actingAs($administrator)->get(route('stations.index'))->assertOk();

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame('17', trim($xpath->evaluate('string(//tr[@data-station-id="'.$station->id.'"]/td[1])')));
        $response->assertSee(route('stations.update', $station), false)
            ->assertSee(route('stations.destroy', $station), false);
    }
}
