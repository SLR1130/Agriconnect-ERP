<?php

namespace App\Providers;

use App\Models\User;
use App\Contracts\PrivateApplicationDocumentScanner;
use App\Services\ClamAvApplicationDocumentScanner;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PrivateApplicationDocumentScanner::class, ClamAvApplicationDocumentScanner::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('farmer-portal', fn (User $user) => $user->hasRole('farmer'));
    }
}
