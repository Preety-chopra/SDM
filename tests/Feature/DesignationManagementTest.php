<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Designation;
use App\Models\User;
use Database\Seeders\DesignationSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolesAndPermissionsSeeder::class,
            DesignationSeeder::class,
        ]);
    }

    public function test_user_form_uses_the_active_seeded_designations_and_rejects_free_text(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $response = $this->actingAs($administrator)
            ->get(route('users.create', UserRole::CmcTeamLead->value))
            ->assertOk()
            ->assertSee('Station Manager (SM)')
            ->assertSee('Senior Station Manager (Sr. SM)')
            ->assertSee('Assistant Manager (AM)')
            ->assertSee('Manager')
            ->assertSee('Deputy General Manager (DGM)');

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $designationControl = (new \DOMXPath($document))->query('//select[@name="designation"]')->item(0);

        $this->assertNotNull($designationControl);

        $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validUserData(),
            'designation' => 'Not configured',
        ])->assertSessionHasErrors('designation');

        $this->actingAs($administrator)->post(route('users.store'), [
            ...$this->validUserData(),
            'designation' => 'AM',
        ])->assertRedirect(route('users.index', UserRole::CmcTeamLead->value));

        $this->assertSame('AM', User::query()->where('email', 'lead@example.com')->value('designation'));
    }

    public function test_administrator_can_add_a_designation_for_future_user_forms(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);

        $this->actingAs($administrator)->post(route('designations.store'), [
            'code' => 'AGM',
            'name' => 'Additional General Manager',
            'is_active' => '1',
        ])->assertRedirect();

        $designation = Designation::query()->where('code', 'AGM')->sole();

        $this->actingAs($administrator)
            ->get(route('users.create', UserRole::CmcTeamLead->value))
            ->assertOk()
            ->assertSee('Additional General Manager (AGM)');

        $this->assertDatabaseHas((new AuditLog)->getTable(), [
            'event' => 'designation.created',
            'auditable_type' => $designation->getMorphClass(),
            'auditable_id' => $designation->id,
            'actor_id' => $administrator->id,
        ]);
    }

    public function test_non_administrators_cannot_manage_designations_and_used_designations_cannot_be_deleted(): void
    {
        $administrator = $this->userWithRole(UserRole::Administrator);
        $stationUser = $this->userWithRole(UserRole::Station);
        $designation = Designation::query()->where('code', 'SM')->sole();
        $stationUser->update(['designation' => $designation->code]);

        $this->actingAs($stationUser)->get(route('designations.index'))->assertForbidden();
        $this->actingAs($stationUser)->post(route('designations.store'), [
            'code' => 'X',
            'name' => 'Unauthorized',
            'is_active' => '1',
        ])->assertForbidden();

        $this->actingAs($administrator)
            ->delete(route('designations.destroy', $designation))
            ->assertSessionHasErrors('designation');

        $this->assertModelExists($designation);
    }

    private function userWithRole(UserRole $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }

    /** @return array<string, mixed> */
    private function validUserData(): array
    {
        return [
            'name' => 'CMC Lead',
            'email' => 'lead@example.com',
            'role' => UserRole::CmcTeamLead->value,
            'employee_number' => 'EMP-CMC-01',
            'phone' => null,
            'designation' => null,
            'station_id' => null,
            'is_enabled' => '1',
            'password' => 'Secure Password 123!',
            'password_confirmation' => 'Secure Password 123!',
        ];
    }
}
