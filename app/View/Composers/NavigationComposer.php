<?php

namespace App\View\Composers;

use App\Enums\UserPermission;
use App\Services\RoleService;
use App\Services\ServiceDeficiencyMemoService;
use Illuminate\View\View;

final class NavigationComposer
{
    public function __construct(
        private RoleService $roles,
        private ServiceDeficiencyMemoService $memos,
    ) {}

    public function compose(View $view): void
    {
        $user = request()->user();
        $view->with([
            'navigationRoles' => $this->roles->managedRoles(),
            'pendingMemoCount' => $user?->can(UserPermission::ActOnRoutedMemos->value)
                ? $this->memos->pendingActionCount($user)
                : 0,
        ]);
    }
}
