<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Line;
use Database\Seeders\LineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_lines_can_be_seeded_independently_with_audit_records(): void
    {
        $this->seed(LineSeeder::class);

        $this->assertDatabaseCount('lines', 16);
        $this->assertDatabaseCount('stations', 0);
        $this->assertDatabaseCount('service_contracts', 0);
        $this->assertDatabaseHas('lines', ['code' => '2', 'name' => 'Yellow Line', 'color' => '#ffc107', 'is_active' => true]);
        $this->assertDatabaseHas('lines', ['code' => '10', 'name' => 'Golden', 'is_active' => true]);
        $this->assertDatabaseHas('lines', ['code' => 'L-3W&9', 'name' => 'Blue Line']);
        $this->assertSame(16, AuditLog::query()->where('event', 'line.created')->count());
    }

    public function test_reseeding_lines_preserves_existing_edits_and_does_not_duplicate_audits(): void
    {
        $line = Line::query()->create(['code' => '2', 'name' => 'Custom Yellow Line', 'is_active' => false]);
        $this->seed(LineSeeder::class);
        $auditCount = AuditLog::query()->count();

        $this->seed(LineSeeder::class);

        $this->assertDatabaseCount('lines', 16);
        $this->assertSame('Custom Yellow Line', $line->fresh()->name);
        $this->assertFalse($line->fresh()->is_active);
        $this->assertSame(15, $auditCount);
        $this->assertDatabaseCount('audit_logs', $auditCount);
    }
}
