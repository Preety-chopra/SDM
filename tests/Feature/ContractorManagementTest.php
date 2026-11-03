<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Contractor;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ContractorManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_administrator_can_create_a_contractor_with_a_multiline_address(): void
    {
        $administrator = $this->administrator();
        $address = "12 Metro Road\nNew Delhi 110001";

        $this->actingAs($administrator)->from(route('contractors.index'))
            ->post(route('contractors.store'), [
                'name' => 'Metro Partner',
                'address' => $address,
                'is_active' => '1',
            ])->assertRedirect(route('contractors.index'));

        $contractor = Contractor::query()->sole();
        $this->assertSame($address, $contractor->address);
        $audit = AuditLog::query()->where('event', 'contractor.created')->sole();
        $this->assertSame($administrator->id, $audit->actor_id);
        $this->assertSame($contractor->getMorphClass(), $audit->auditable_type);
        $this->assertSame($contractor->id, $audit->auditable_id);
        $this->assertSame($address, $audit->after_values['address']);
    }

    public function test_administrator_can_update_and_clear_an_existing_contractors_address(): void
    {
        $administrator = $this->administrator();
        $contractor = Contractor::query()->create(['name' => 'Existing Partner', 'is_active' => true]);

        $this->actingAs($administrator)->put(route('contractors.update', $contractor), [
            'name' => $contractor->name,
            'address' => '34 Station Road',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertSame('34 Station Road', $contractor->fresh()->address);
        $audit = AuditLog::query()->where('event', 'contractor.updated')->sole();
        $this->assertSame($administrator->id, $audit->actor_id);
        $this->assertNull($audit->before_values['address']);
        $this->assertSame('34 Station Road', $audit->after_values['address']);

        $this->put(route('contractors.update', $contractor), [
            'name' => $contractor->name,
            'address' => '',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertNull($contractor->fresh()->address);
        $audit = AuditLog::query()->where('event', 'contractor.updated')->latest('id')->firstOrFail();
        $this->assertSame('34 Station Road', $audit->before_values['address']);
        $this->assertNull($audit->after_values['address']);
    }

    public function test_address_is_optional_and_omitting_it_on_update_preserves_the_saved_address(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)->post(route('contractors.store'), [
            'name' => 'Optional Address Partner',
            'is_active' => '1',
        ])->assertRedirect();

        $contractor = Contractor::query()->sole();
        $this->assertNull($contractor->address);
        $contractor->update(['address' => '56 Depot Road']);

        $this->put(route('contractors.update', $contractor), [
            'name' => 'Renamed Partner',
            'is_active' => '1',
        ])->assertRedirect();

        $this->assertDatabaseHas('contractors', [
            'id' => $contractor->id,
            'name' => 'Renamed Partner',
            'address' => '56 Depot Road',
        ]);
    }

    #[DataProvider('invalidAddresses')]
    public function test_invalid_addresses_are_rejected_without_creating_a_contractor(mixed $address, string $message): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)->post(route('contractors.store'), [
            'name' => 'Invalid Address Partner',
            'address' => $address,
            'is_active' => '1',
        ])->assertSessionHasErrors(['address' => $message]);

        $this->assertDatabaseCount('contractors', 0);
        $this->assertDatabaseMissing('audit_logs', ['auditable_type' => Contractor::class]);
    }

    public static function invalidAddresses(): array
    {
        return [
            'too long' => [str_repeat('a', 2001), 'The address field must not be greater than 2000 characters.'],
            'not text' => [['unexpected'], 'The address field must be a string.'],
        ];
    }

    public function test_contractors_page_has_address_fields_and_escapes_saved_addresses(): void
    {
        $administrator = $this->administrator();
        $address = "12 Road\n<script>alert('address')</script>";
        $contractor = Contractor::query()->create(['name' => 'Display Partner', 'address' => $address, 'is_active' => true]);

        $this->actingAs($administrator)->get(route('contractors.index'))
            ->assertOk()
            ->assertSee('id="contractor_address_create"', false)
            ->assertSee('id="contractor_address_'.$contractor->id.'"', false)
            ->assertSee($address)
            ->assertDontSee($address, false);
    }

    public function test_unauthorized_users_cannot_change_contractor_addresses(): void
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Station->value);
        $contractor = Contractor::query()->create(['name' => 'Protected Partner', 'is_active' => true]);

        $this->actingAs($user)->put(route('contractors.update', $contractor), [
            'name' => $contractor->name,
            'address' => 'Unauthorized address',
            'is_active' => '1',
        ])->assertForbidden();

        $this->assertNull($contractor->fresh()->address);
        $this->assertDatabaseMissing('audit_logs', ['auditable_type' => Contractor::class]);
    }

    public function test_validation_failure_restores_the_address_only_in_the_submitted_form(): void
    {
        $administrator = $this->administrator();
        $contractor = Contractor::query()->create(['name' => 'Edited Partner', 'address' => 'Original address', 'is_active' => true]);
        $other = Contractor::query()->create(['name' => 'Other Partner', 'address' => 'Other address', 'is_active' => true]);

        $this->actingAs($administrator)->from(route('contractors.index'))
            ->put(route('contractors.update', $contractor), [
                'name' => '',
                'address' => 'Unsaved address',
                'contractor_form' => (string) $contractor->id,
                'is_active' => '1',
            ])->assertSessionHasErrors('name');

        $response = $this->get(route('contractors.index'))->assertOk();
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);
        $this->assertSame('Unsaved address', $xpath->query('//textarea[@id="contractor_address_'.$contractor->id.'"]')->item(0)->textContent);
        $this->assertSame('Other address', $xpath->query('//textarea[@id="contractor_address_'.$other->id.'"]')->item(0)->textContent);
        $this->assertSame('', $xpath->query('//textarea[@id="contractor_address_create"]')->item(0)->textContent);
        $this->assertSame('Original address', $contractor->fresh()->address);
        $this->assertDatabaseMissing('audit_logs', ['auditable_type' => Contractor::class]);
    }

    public function test_address_migration_can_be_rolled_back_and_reapplied_without_removing_contractors(): void
    {
        $contractor = Contractor::query()->create(['name' => 'Existing Partner', 'is_active' => true]);
        $migration = require database_path('migrations/2026_10_31_115640_add_address_to_contractors_table.php');

        $migration->down();

        $this->assertFalse(Schema::hasColumn('contractors', 'address'));
        $this->assertDatabaseHas('contractors', ['id' => $contractor->id, 'name' => 'Existing Partner']);

        $migration->up();

        $this->assertTrue(Schema::hasColumn('contractors', 'address'));
        $this->assertNull($contractor->fresh()->address);
    }

    private function administrator(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Administrator->value);

        return $user;
    }
}
