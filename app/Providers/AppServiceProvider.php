<?php

namespace App\Providers;

use App\Contracts\Repositories\AftCashCheckReportRepositoryInterface;
use App\Contracts\Repositories\AuditLogRepositoryInterface;
use App\Contracts\Repositories\DesignationRepositoryInterface;
use App\Contracts\Repositories\FeedbackRepositoryInterface;
use App\Contracts\Repositories\LineManagerDashboardRepositoryInterface;
use App\Contracts\Repositories\MasterDataRepositoryInterface;
use App\Contracts\Repositories\OutsourcedStaffRepositoryInterface;
use App\Contracts\Repositories\RoleRepositoryInterface;
use App\Contracts\Repositories\ServiceContractRepositoryInterface;
use App\Contracts\Repositories\ServiceDeficiencyMemoRepositoryInterface;
use App\Contracts\Repositories\StationControllerDashboardRepositoryInterface;
use App\Contracts\Repositories\StationHierarchyRepositoryInterface;
use App\Contracts\Repositories\StationManagerDashboardRepositoryInterface;
use App\Contracts\Repositories\StationRepositoryInterface;
use App\Contracts\Repositories\UserActivityReportRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Contracts\Repositories\WorkflowRoleDashboardRepositoryInterface;
use App\Models\AftCashCheckReport;
use App\Models\ServiceContract;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use App\Policies\AftCashCheckReportPolicy;
use App\Policies\RolePolicy;
use App\Policies\ServiceContractPolicy;
use App\Policies\ServiceDeficiencyMemoPolicy;
use App\Policies\UserPolicy;
use App\Repositories\Eloquent\AftCashCheckReportRepository;
use App\Repositories\Eloquent\AuditLogRepository;
use App\Repositories\Eloquent\DesignationRepository;
use App\Repositories\Eloquent\FeedbackRepository;
use App\Repositories\Eloquent\LineManagerDashboardRepository;
use App\Repositories\Eloquent\MasterDataRepository;
use App\Repositories\Eloquent\OutsourcedStaffRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\ServiceContractRepository;
use App\Repositories\Eloquent\ServiceDeficiencyMemoRepository;
use App\Repositories\Eloquent\StationControllerDashboardRepository;
use App\Repositories\Eloquent\StationHierarchyRepository;
use App\Repositories\Eloquent\StationManagerDashboardRepository;
use App\Repositories\Eloquent\StationRepository;
use App\Repositories\Eloquent\UserActivityReportRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\WorkflowRoleDashboardRepository;
use App\View\Composers\NavigationComposer;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AftCashCheckReportRepositoryInterface::class, AftCashCheckReportRepository::class);
        $this->app->bind(AuditLogRepositoryInterface::class, AuditLogRepository::class);
        $this->app->bind(FeedbackRepositoryInterface::class, FeedbackRepository::class);
        $this->app->bind(UserActivityReportRepositoryInterface::class, UserActivityReportRepository::class);
        $this->app->bind(DesignationRepositoryInterface::class, DesignationRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(LineManagerDashboardRepositoryInterface::class, LineManagerDashboardRepository::class);
        $this->app->bind(WorkflowRoleDashboardRepositoryInterface::class, WorkflowRoleDashboardRepository::class);
        $this->app->bind(StationRepositoryInterface::class, StationRepository::class);
        $this->app->bind(StationHierarchyRepositoryInterface::class, StationHierarchyRepository::class);
        $this->app->bind(ServiceDeficiencyMemoRepositoryInterface::class, ServiceDeficiencyMemoRepository::class);
        $this->app->bind(StationControllerDashboardRepositoryInterface::class, StationControllerDashboardRepository::class);
        $this->app->bind(StationManagerDashboardRepositoryInterface::class, StationManagerDashboardRepository::class);
        $this->app->bind(ServiceContractRepositoryInterface::class, ServiceContractRepository::class);
        $this->app->bind(MasterDataRepositoryInterface::class, MasterDataRepository::class);
        $this->app->bind(OutsourcedStaffRepositoryInterface::class, OutsourcedStaffRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(AftCashCheckReport::class, AftCashCheckReportPolicy::class);
        Password::defaults(fn (): Password => Password::min(8)->mixedCase()->numbers()->rules([
            // Laravel's symbols() rule also accepts spaces; require a symbol or punctuation.
            function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && ! preg_match('/[\p{P}\p{S}]/u', $value)) {
                    $fail(__('validation.password.symbols', ['attribute' => $attribute]));
                }
            },
        ]));

        DB::prohibitDestructiveCommands(! $this->app->environment('testing'));

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ServiceDeficiencyMemo::class, ServiceDeficiencyMemoPolicy::class);
        Gate::policy(ServiceContract::class, ServiceContractPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        View::composer('layouts.navigation', NavigationComposer::class);
    }
}
