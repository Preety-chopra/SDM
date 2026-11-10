<?php

use App\Enums\UserPermission;
use App\Services\AuditService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
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
        DB::transaction(function (): void {
            // Existing installations audit permission changes; a fresh database only bootstraps reference roles.
            $isExistingInstallation = DB::table('users')->exists();
            $roles = [
                'aft' => [UserPermission::CreateMemos, UserPermission::ViewOwnMemos, UserPermission::UpdateOwnDraftMemos, UserPermission::SubmitOwnDraftMemos],
                'dgm_aft' => [UserPermission::ViewRoutedMemos, UserPermission::ActOnRoutedMemos],
            ];
            foreach ($roles as $name => $permissions) {
                $role = Role::findOrCreate($name, 'web');
                $before = $role->permissions()->pluck('name')->all();
                $role->givePermissionTo(array_map(fn (UserPermission $permission) => Permission::findOrCreate($permission->value, 'web'), $permissions));
                $after = $role->permissions()->pluck('name')->all();
                if ($isExistingInstallation && ($role->wasRecentlyCreated || array_diff($after, $before) !== [])) {
                    app(AuditService::class)->record($role, null, 'role.aft.configured', ['permissions' => $before], [
                        'name' => $name, 'permissions' => $after,
                    ]);
                }
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Preserve roles, assignments, and audit history on rollback. Remove them through role administration if no longer needed.
    }
};
