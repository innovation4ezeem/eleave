<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The app uses Bootstrap 5 (CDN); render pagination with matching markup
        // instead of Laravel's default Tailwind view.
        Paginator::useBootstrapFive();
    }
}
