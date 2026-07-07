<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
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

        // Who may add/edit a person's working experience & achievements:
        // the person themselves, any admin, or the HOD of their department.
        Gate::define('manage-profile', function (User $user, User $target) {
            return $user->id === $target->id
                || $user->isAdmin()
                || ($user->isHod() && $user->department_id !== null && $user->department_id === $target->department_id);
        });
    }
}
