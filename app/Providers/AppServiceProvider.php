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
        // the person themselves, any admin, or the HOD of their department
        // (but a HOD only manages plain employees in their department, not
        // admins or fellow HODs — managing a peer/superior would be a privilege
        // inversion; a HOD's own profile is covered by the self check).
        // $target is nullable so an orphaned record (owner soft-deleted) denies
        // cleanly with 403 instead of crashing the gate closure.
        Gate::define('manage-profile', function (User $user, ?User $target = null) {
            if ($target === null) {
                return false;
            }

            return $user->id === $target->id
                || $user->isAdmin()
                || ($user->isHod()
                    && ! $target->isAdminOrHod()
                    && $user->department_id !== null
                    && $user->department_id === $target->department_id);
        });
    }
}
