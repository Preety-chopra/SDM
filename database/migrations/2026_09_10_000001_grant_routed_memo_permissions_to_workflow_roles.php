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

        $permissions = [
            Permission::findOrCreate(UserPermission::ViewRoutedMemos->value, 'web'),
            Permission::findOrCreate(UserPermission::ActOnRoutedMemos->value, 'web'),
        ];

        foreach ($this->workflowRoles() as $roleName) {
            Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first()
                ?->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            UserPermission::ViewRoutedMemos->value,
            UserPermission::ActOnRoutedMemos->value,
        ];

        foreach ($this->workflowRoles() as $roleName) {
            Role::query()
                ->where('name', $roleName)
                ->where('guard_name', 'web')
                ->first()
                ?->revokePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /** @return list<string> */
    private function workflowRoles(): array
    {
        return [
            UserRole::StationManager->value,
            UserRole::AssistantManager->value,
            UserRole::ManagerOperations->value,
            UserRole::CmcTeamLead->value,
            UserRole::CmcStaff->value,
            UserRole::DeputyGeneralManager->value,
        ];
    }
};
