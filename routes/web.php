<?php

use App\Http\Controllers\Admin\CompetenceCategoryController;
use App\Http\Controllers\Admin\CompetenceVoteLogController;
use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\HistoricalLeaveController;
use App\Http\Controllers\Admin\LeaveApplicationController as AdminLeaveApplicationController;
use App\Http\Controllers\Admin\LeaveBalanceController as AdminLeaveBalanceController;
use App\Http\Controllers\Admin\LeaveEntitlementTierController;
use App\Http\Controllers\Admin\LeaveYearController;
use App\Http\Controllers\Admin\PenaltyController as AdminPenaltyController;
use App\Http\Controllers\Admin\PerformanceEntryController;
use App\Http\Controllers\Admin\PerformanceSettingController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\WarningLetterController as AdminWarningLetterController;
use App\Http\Controllers\AchievementController;
use App\Http\Controllers\StaffAchievementController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employee\CompetenceController;
use App\Http\Controllers\Employee\LeaveApplicationController;
use App\Http\Controllers\Employee\LeaveBalanceController;
use App\Http\Controllers\Employee\LeaveReportController;
use App\Http\Controllers\Employee\PenaltyController as EmployeePenaltyController;
use App\Http\Controllers\Employee\PerformanceController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WarningLetterController;
use App\Http\Controllers\WorkExperienceController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password.update');

    // Working experience & achievements — permission enforced by the 'manage-profile' gate
    // (self, admin, or HOD of the target's department).
    Route::post('/users/{user}/experiences', [WorkExperienceController::class, 'store'])->name('experiences.store');
    Route::put('/experiences/{workExperience}', [WorkExperienceController::class, 'update'])->name('experiences.update');
    Route::delete('/experiences/{workExperience}', [WorkExperienceController::class, 'destroy'])->name('experiences.destroy');
    Route::post('/users/{user}/achievements', [AchievementController::class, 'store'])->name('achievements.store');
    Route::put('/achievements/{achievement}', [AchievementController::class, 'update'])->name('achievements.update');
    Route::delete('/achievements/{achievement}', [AchievementController::class, 'destroy'])->name('achievements.destroy');
    Route::post('/users/{user}/staff-achievements', [StaffAchievementController::class, 'store'])->name('staff-achievements.store');
    Route::put('/staff-achievements/{staffAchievement}', [StaffAchievementController::class, 'update'])->name('staff-achievements.update');
    Route::delete('/staff-achievements/{staffAchievement}', [StaffAchievementController::class, 'destroy'])->name('staff-achievements.destroy');

    Route::prefix('leave')->name('leave.')->group(function () {
        Route::get('/', [LeaveApplicationController::class, 'index'])->name('index');
        Route::get('/create', [LeaveApplicationController::class, 'create'])->name('create');
        Route::post('/', [LeaveApplicationController::class, 'store'])->name('store');
        Route::get('/report', [LeaveReportController::class, 'index'])->name('report');
        Route::get('/{leaveApplication}', [LeaveApplicationController::class, 'show'])->name('show');
        Route::post('/{leaveApplication}/cancel', [LeaveApplicationController::class, 'cancel'])->name('cancel');
        Route::post('/{leaveApplication}/change-request', [LeaveApplicationController::class, 'requestChange'])->name('change-request');
        Route::get('/{leaveApplication}/document', [LeaveApplicationController::class, 'downloadDocument'])->name('document');
        Route::get('/{leaveApplication}/document/preview', [LeaveApplicationController::class, 'previewDocument'])->name('document.preview');
    });

    Route::get('/balances', [LeaveBalanceController::class, 'index'])->name('balances.index');

    // Employee-facing penalties (own records)
    Route::get('/penalties', [EmployeePenaltyController::class, 'index'])->name('penalties.index');
    Route::get('/penalties/{penalty}/document', [EmployeePenaltyController::class, 'downloadDocument'])->name('penalties.document');

    // Warning letters — read-only list (employee: own, HOD: own + department) and the one
    // download route for every role. Access is decided by WarningLetterPolicy, not by the menu.
    Route::get('/warning-letters', [WarningLetterController::class, 'index'])->name('warning-letters.index');
    Route::get('/warning-letters/{warningLetter}/download', [WarningLetterController::class, 'download'])->name('warning-letters.download');
    Route::get('/warning-letters/{warningLetter}/preview', [WarningLetterController::class, 'preview'])->name('warning-letters.preview');

    // Personal Competence (peer voting) — any logged-in user
    Route::get('/competence', [CompetenceController::class, 'index'])->name('competence.index');
    Route::post('/competence/vote', [CompetenceController::class, 'vote'])->name('competence.vote');

    // Performance scores — employee sees own; HOD sees own + department; admin redirected to admin page
    Route::get('/performance', [PerformanceController::class, 'index'])->name('performance.index');

    // Admin + HOD shared routes
    Route::middleware('role:admin,hod')->prefix('admin')->name('admin.')->group(function () {

        // Leave applications — controllers scope to HOD's dept automatically
        Route::prefix('applications')->name('applications.')->group(function () {
            Route::get('/', [AdminLeaveApplicationController::class, 'index'])->name('index');
            Route::get('/{leaveApplication}', [AdminLeaveApplicationController::class, 'show'])->name('show');
            Route::post('/{leaveApplication}/approve', [AdminLeaveApplicationController::class, 'approve'])->name('approve');
            Route::post('/{leaveApplication}/reject', [AdminLeaveApplicationController::class, 'reject'])->name('reject');
            Route::post('/{leaveApplication}/change/approve', [AdminLeaveApplicationController::class, 'approveChange'])->name('change.approve');
            Route::post('/{leaveApplication}/change/reject', [AdminLeaveApplicationController::class, 'rejectChange'])->name('change.reject');
            Route::get('/{leaveApplication}/document', [AdminLeaveApplicationController::class, 'downloadDocument'])->name('document');
            Route::get('/{leaveApplication}/document/preview', [AdminLeaveApplicationController::class, 'previewDocument'])->name('document.preview');
        });

        // Users — admin-only create/store must come BEFORE the {user} wildcard routes
        Route::middleware('role:admin')->group(function () {
            Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
            Route::post('/users', [UserController::class, 'store'])->name('users.store');
            Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
            Route::resource('departments', DepartmentController::class)->except(['show']);

            // Leave entitlement tiers (years-of-service based quotas)
            Route::get('/entitlement-tiers', [LeaveEntitlementTierController::class, 'index'])->name('entitlement-tiers.index');
            Route::post('/entitlement-tiers', [LeaveEntitlementTierController::class, 'store'])->name('entitlement-tiers.store');
            Route::get('/entitlement-tiers/{entitlementTier}/edit', [LeaveEntitlementTierController::class, 'edit'])->name('entitlement-tiers.edit');
            Route::put('/entitlement-tiers/{entitlementTier}', [LeaveEntitlementTierController::class, 'update'])->name('entitlement-tiers.update');
            Route::delete('/entitlement-tiers/{entitlementTier}', [LeaveEntitlementTierController::class, 'destroy'])->name('entitlement-tiers.destroy');

            // Warning letters — only admins issue, edit or delete ('create' before the wildcard routes).
            Route::prefix('warning-letters')->name('warning-letters.')->group(function () {
                Route::get('/', [AdminWarningLetterController::class, 'index'])->name('index');
                Route::get('/create', [AdminWarningLetterController::class, 'create'])->name('create');
                Route::post('/', [AdminWarningLetterController::class, 'store'])->name('store');
                Route::get('/{warningLetter}/edit', [AdminWarningLetterController::class, 'edit'])->name('edit');
                Route::put('/{warningLetter}', [AdminWarningLetterController::class, 'update'])->name('update');
                Route::delete('/{warningLetter}', [AdminWarningLetterController::class, 'destroy'])->name('destroy');
            });

            // Leave-year archive / locks
            Route::get('/leave-years', [LeaveYearController::class, 'index'])->name('leave-years.index');
            Route::post('/leave-years/{leaveYear}/lock', [LeaveYearController::class, 'lock'])->name('leave-years.lock');
            Route::post('/leave-years/{leaveYear}/unlock', [LeaveYearController::class, 'unlock'])->name('leave-years.unlock');

            // Personal Competence — category management + voting log
            Route::get('/competence-categories', [CompetenceCategoryController::class, 'index'])->name('competence-categories.index');
            Route::post('/competence-categories', [CompetenceCategoryController::class, 'store'])->name('competence-categories.store');
            Route::post('/competence-categories/{category}/toggle', [CompetenceCategoryController::class, 'toggle'])->name('competence-categories.toggle');
            Route::delete('/competence-categories/{category}', [CompetenceCategoryController::class, 'destroy'])->name('competence-categories.destroy');
            Route::get('/competence-votes', [CompetenceVoteLogController::class, 'index'])->name('competence-votes.index');
            Route::get('/competence-results', [CompetenceVoteLogController::class, 'results'])->name('competence-results.index');

            // Performance — customizable formula settings (inputs + components)
            Route::get('/performance-settings', [PerformanceSettingController::class, 'index'])->name('performance-settings.index');
            Route::post('/performance-settings/inputs', [PerformanceSettingController::class, 'storeInput'])->name('performance-settings.inputs.store');
            Route::delete('/performance-settings/inputs/{input}', [PerformanceSettingController::class, 'destroyInput'])->name('performance-settings.inputs.destroy');
            Route::post('/performance-settings/components', [PerformanceSettingController::class, 'storeComponent'])->name('performance-settings.components.store');
            Route::put('/performance-settings/components/{component}', [PerformanceSettingController::class, 'updateComponent'])->name('performance-settings.components.update');
            Route::delete('/performance-settings/components/{component}', [PerformanceSettingController::class, 'destroyComponent'])->name('performance-settings.components.destroy');

            // Performance — monthly score entries ('create' before the '{performanceEntry}' wildcard)
            Route::get('/performance-entries', [PerformanceEntryController::class, 'index'])->name('performance-entries.index');
            Route::get('/performance-entries/create', [PerformanceEntryController::class, 'create'])->name('performance-entries.create');
            Route::post('/performance-entries', [PerformanceEntryController::class, 'store'])->name('performance-entries.store');
            Route::get('/performance-entries/{performanceEntry}/edit', [PerformanceEntryController::class, 'edit'])->name('performance-entries.edit');
            Route::put('/performance-entries/{performanceEntry}', [PerformanceEntryController::class, 'update'])->name('performance-entries.update');
            Route::delete('/performance-entries/{performanceEntry}', [PerformanceEntryController::class, 'destroy'])->name('performance-entries.destroy');
        });

        // Users — HOD can view and edit their dept's users
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/disable', [UserController::class, 'disable'])->name('users.disable');
        Route::post('/users/{user}/enable', [UserController::class, 'enable'])->name('users.enable');
        Route::get('/users/{user}/balances', [AdminLeaveBalanceController::class, 'edit'])->name('users.balances.edit');
        Route::put('/users/{user}/balances', [AdminLeaveBalanceController::class, 'update'])->name('users.balances.update');
        Route::post('/users/{user}/balances/recalculate', [AdminLeaveBalanceController::class, 'recalculate'])->name('users.balances.recalculate');

        // Historical leave — controllers scope to HOD's dept automatically
        Route::prefix('historical')->name('historical.')->group(function () {
            Route::get('/', [HistoricalLeaveController::class, 'index'])->name('index');
            Route::get('/create', [HistoricalLeaveController::class, 'create'])->name('create');
            Route::post('/', [HistoricalLeaveController::class, 'store'])->name('store');
        });

        // Penalties & warnings — controllers scope to HOD's dept automatically.
        // 'create' must come BEFORE the '{penalty}' wildcard routes.
        Route::prefix('penalties')->name('penalties.')->group(function () {
            Route::get('/', [AdminPenaltyController::class, 'index'])->name('index');
            Route::get('/create', [AdminPenaltyController::class, 'create'])->name('create');
            Route::post('/', [AdminPenaltyController::class, 'store'])->name('store');
            Route::get('/{penalty}', [AdminPenaltyController::class, 'show'])->name('show');
            Route::get('/{penalty}/edit', [AdminPenaltyController::class, 'edit'])->name('edit');
            Route::put('/{penalty}', [AdminPenaltyController::class, 'update'])->name('update');
            Route::delete('/{penalty}', [AdminPenaltyController::class, 'destroy'])->name('destroy');
            Route::post('/{penalty}/collect', [AdminPenaltyController::class, 'markCollected'])->name('collect');
        });
    });
});
