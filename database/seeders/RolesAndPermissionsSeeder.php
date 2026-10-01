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

        $stationControllerPermissions = [
            UserPermission::CreateMemos->value,
            UserPermission::ViewOwnMemos->value,
            UserPermission::UpdateOwnDraftMemos->value,
            UserPermission::SubmitOwnDraftMemos->value,
        ];

        foreach (UserRole::managed() as $role) {
            Role::findByName($role->value, 'web')->syncPermissions(
                match ($role) {
                    UserRole::Station => $stationControllerPermissions,
                    UserRole::StationManager => [
                        UserPermission::CreateMemos->value,
                        UserPermission::ViewOwnMemos->value,
                        UserPermission::UpdateOwnDraftMemos->value,
                        UserPermission::SubmitOwnDraftMemos->value,
                        UserPermission::ViewRoutedMemos->value,
                        UserPermission::ActOnRoutedMemos->value,
                    ],
                    UserRole::AssistantManager, UserRole::ManagerOperations, UserRole::DeputyGeneralManager => [
                        UserPermission::ViewRoutedMemos->value,
                        UserPermission::ActOnRoutedMemos->value,
                    ],
                    UserRole::CmcTeamLead => [
                        UserPermission::ViewRoutedMemos->value,
                        UserPermission::ActOnRoutedMemos->value,
                        UserPermission::AssignCmcStaff->value,
                    ],
                    UserRole::CmcStaff => [
                        UserPermission::ViewRoutedMemos->value,
                        UserPermission::ActOnRoutedMemos->value,
                        UserPermission::AssessMemos->value,
                        UserPermission::ManagePenaltyLetters->value,
                        UserPermission::ManageObjections->value,
                        UserPermission::RecordDeductions->value,
                    ],
                    default => [],
                },
            );
        }

        Role::query()
            ->where('name', 'station_controller')
            ->where('guard_name', 'web')
            ->first()
            ?->syncPermissions($stationControllerPermissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
