<?php

namespace App\Providers;

use App\Contracts\Repositories\MasterDataRepositoryInterface;
use App\Contracts\Repositories\RoleRepositoryInterface;
use App\Contracts\Repositories\ServiceDeficiencyMemoRepositoryInterface;
use App\Contracts\Repositories\StationHierarchyRepositoryInterface;
use App\Contracts\Repositories\StationRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\ServiceDeficiencyMemo;
use App\Models\User;
use App\Policies\RolePolicy;
use App\Policies\ServiceDeficiencyMemoPolicy;
use App\Policies\UserPolicy;
use App\Repositories\Eloquent\MasterDataRepository;
use App\Repositories\Eloquent\RoleRepository;
use App\Repositories\Eloquent\ServiceDeficiencyMemoRepository;
use App\Repositories\Eloquent\StationHierarchyRepository;
use App\Repositories\Eloquent\StationRepository;
use App\Repositories\Eloquent\UserRepository;
use App\View\Composers\NavigationComposer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(StationRepositoryInterface::class, StationRepository::class);
        $this->app->bind(StationHierarchyRepositoryInterface::class, StationHierarchyRepository::class);
        $this->app->bind(ServiceDeficiencyMemoRepositoryInterface::class, ServiceDeficiencyMemoRepository::class);
        $this->app->bind(MasterDataRepositoryInterface::class, MasterDataRepository::class);
        $this->app->bind(RoleRepositoryInterface::class, RoleRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        DB::prohibitDestructiveCommands(! $this->app->environment('testing'));

        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ServiceDeficiencyMemo::class, ServiceDeficiencyMemoPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);

        View::composer('layouts.navigation', NavigationComposer::class);
    }
}
