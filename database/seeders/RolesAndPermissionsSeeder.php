<?php

namespace Database\Seeders;

use App\Enums\UserPermission;
use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        $permissions = collect(UserPermission::cases())
            ->map(fn (UserPermission $permission): Permission => Permission::findOrCreate($permission->value, 'web'));

        Role::findByName(UserRole::Administrator->value, 'web')->syncPermissions($permissions);

        foreach (UserRole::managed() as $role) {
            Role::findByName($role->value, 'web')->syncPermissions(
                $role === UserRole::Station ? [
                    UserPermission::CreateMemos->value,
                    UserPermission::ViewOwnMemos->value,
                    UserPermission::UpdateOwnDraftMemos->value,
                    UserPermission::SubmitOwnDraftMemos->value,
                ] : [],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
