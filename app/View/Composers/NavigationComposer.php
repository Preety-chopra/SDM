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
        $notificationsAcknowledged = $user
            && (int) request()->session()->get('memo_notifications_acknowledged_by') === $user->id;

        $view->with([
            'navigationRoles' => $this->roles->managedRoles(),
            'pendingMemoCount' => $user?->can(UserPermission::ActOnRoutedMemos->value) && ! $notificationsAcknowledged
                ? $this->memos->pendingActionCount($user)
                : 0,
        ]);
    }
}
