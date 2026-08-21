<?php

use App\Enums\UserPermission;
use App\Enums\UserRole;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::findOrCreate(UserPermission::ManageRoles->value, 'web');
        $administrator = Role::query()
            ->where('name', UserRole::Administrator->value)
            ->where('guard_name', 'web')
            ->first();

        $administrator?->givePermissionTo($permission);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = Permission::query()
            ->where('name', UserPermission::ManageRoles->value)
            ->where('guard_name', 'web')
            ->first();

        $permission?->delete();
    }
};
