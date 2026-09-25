<?php

namespace App\Http\Requests;

use App\Enums\UserRole;

class DashboardChartMemosRequest extends DashboardRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->getRoleNames()->contains(
            fn (string $role): bool => in_array($role, [
                UserRole::Station->value,
                'station_controller',
                UserRole::ManagerOperations->value,
                UserRole::CmcTeamLead->value,
                UserRole::CmcStaff->value,
                UserRole::DeputyGeneralManager->value,
            ], true),
        ) ?? false;
    }
}
