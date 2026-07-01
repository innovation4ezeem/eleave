<?php

use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\HistoricalLeaveController;
use App\Http\Controllers\Admin\LeaveApplicationController as AdminLeaveApplicationController;
use App\Http\Controllers\Admin\LeaveBalanceController as AdminLeaveBalanceController;
use App\Http\Controllers\Admin\LeaveEntitlementTierController;
use App\Http\Controllers\Admin\LeaveYearController;
use App\Http\Controllers\Admin\PenaltyController as AdminPenaltyController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employee\LeaveApplicationController;
use App\Http\Controllers\Employee\LeaveBalanceController;
use App\Http\Controllers\Employee\LeaveReportController;
use App\Http\Controllers\Employee\PenaltyController as EmployeePenaltyController;
use App\Http\Controllers\ProfileController;
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

    Route::prefix('leave')->name('leave.')->group(function () {
        Route::get('/', [LeaveApplicationController::class, 'index'])->name('index');
        Route::get('/create', [LeaveApplicationController::class, 'create'])->name('create');
        Route::post('/', [LeaveApplicationController::class, 'store'])->name('store');
        Route::get('/report', [LeaveReportController::class, 'index'])->name('report');
        Route::get('/{leaveApplication}', [LeaveApplicationController::class, 'show'])->name('show');
        Route::post('/{leaveApplication}/cancel', [LeaveApplicationController::class, 'cancel'])->name('cancel');
        Route::post('/{leaveApplication}/change-request', [LeaveApplicationController::class, 'requestChange'])->name('change-request');
        Route::get('/{leaveApplication}/document', [LeaveApplicationController::class, 'downloadDocument'])->name('document');
    });

    Route::get('/balances', [LeaveBalanceController::class, 'index'])->name('balances.index');

    // Employee-facing penalties (own records)
    Route::get('/penalties', [EmployeePenaltyController::class, 'index'])->name('penalties.index');
    Route::get('/penalties/{penalty}/document', [EmployeePenaltyController::class, 'downloadDocument'])->name('penalties.document');

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
            Route::delete('/entitlement-tiers/{entitlementTier}', [LeaveEntitlementTierController::class, 'destroy'])->name('entitlement-tiers.destroy');

            // Leave-year archive / locks
            Route::get('/leave-years', [LeaveYearController::class, 'index'])->name('leave-years.index');
            Route::post('/leave-years/{leaveYear}/lock', [LeaveYearController::class, 'lock'])->name('leave-years.lock');
            Route::post('/leave-years/{leaveYear}/unlock', [LeaveYearController::class, 'unlock'])->name('leave-years.unlock');
        });

        // Users — HOD can view and edit their dept's users
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
        Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::get('/users/{user}/balances', [AdminLeaveBalanceController::class, 'edit'])->name('users.balances.edit');
        Route::put('/users/{user}/balances', [AdminLeaveBalanceController::class, 'update'])->name('users.balances.update');

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
