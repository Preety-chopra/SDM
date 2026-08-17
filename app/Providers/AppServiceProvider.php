<?php

namespace App\Providers;

use App\Contracts\Repositories\StationRepositoryInterface;
use App\Contracts\Repositories\ServiceDeficiencyMemoRepositoryInterface;
use App\Contracts\Repositories\UserRepositoryInterface;
use App\Models\User;
use App\Models\ServiceDeficiencyMemo;
use App\Policies\UserPolicy;
use App\Policies\ServiceDeficiencyMemoPolicy;
use App\Repositories\Eloquent\StationRepository;
use App\Repositories\Eloquent\ServiceDeficiencyMemoRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(StationRepositoryInterface::class, StationRepository::class);
        $this->app->bind(ServiceDeficiencyMemoRepositoryInterface::class, ServiceDeficiencyMemoRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ServiceDeficiencyMemo::class, ServiceDeficiencyMemoPolicy::class);
    }
}
