<?php

namespace App\View\Composers;

use App\Enums\UserPermission;
use App\Models\AftCashCheckReport;
use App\Services\AftCashCheckReportService;
use App\Services\FeedbackService;
use App\Services\RoleService;
use App\Services\ServiceDeficiencyMemoService;
use Illuminate\View\View;

final class NavigationComposer
{
    public function __construct(
        private RoleService $roles,
        private ServiceDeficiencyMemoService $memos,
        private AftCashCheckReportService $cashChecks,
        private FeedbackService $feedback,
    ) {}

    public function compose(View $view): void
    {
        $user = request()->user();
        $notificationsAcknowledged = $user
            && (int) request()->session()->get('memo_notifications_acknowledged_by') === $user->id;

        $view->with([
            'navigationRoles' => $this->roles->managedRoles(),
            'unreadFeedbackReplyCount' => $user ? $this->feedback->unreadReplyCount($user) : 0,
            'pendingCashCheckCount' => $user?->can('viewAny', AftCashCheckReport::class)
                ? $this->cashChecks->pendingCountFor($user) : 0,
            'pendingMemoCount' => $user?->can(UserPermission::ActOnRoutedMemos->value) && ! $notificationsAcknowledged
                ? $this->memos->pendingActionCount($user)
                : 0,
        ]);
    }
}
