<?php

use App\Http\Controllers\Admin\DepartmentController;
use App\Http\Controllers\Admin\HistoricalLeaveController;
use App\Http\Controllers\Admin\LeaveApplicationController as AdminLeaveApplicationController;
use App\Http\Controllers\Admin\LeaveBalanceController as AdminLeaveBalanceController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Employee\LeaveApplicationController;
use App\Http\Controllers\Employee\LeaveBalanceController;
use App\Http\Controllers\Employee\LeaveReportController;
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
        Route::get('/{leaveApplication}/document', [LeaveApplicationController::class, 'downloadDocument'])->name('document');
    });

    Route::get('/balances', [LeaveBalanceController::class, 'index'])->name('balances.index');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::prefix('applications')->name('applications.')->group(function () {
            Route::get('/', [AdminLeaveApplicationController::class, 'index'])->name('index');
            Route::get('/{leaveApplication}', [AdminLeaveApplicationController::class, 'show'])->name('show');
            Route::post('/{leaveApplication}/approve', [AdminLeaveApplicationController::class, 'approve'])->name('approve');
            Route::post('/{leaveApplication}/reject', [AdminLeaveApplicationController::class, 'reject'])->name('reject');
            Route::get('/{leaveApplication}/document', [AdminLeaveApplicationController::class, 'downloadDocument'])->name('document');
        });

        Route::resource('departments', DepartmentController::class)->except(['show']);

        Route::resource('users', UserController::class)->except(['show']);
        Route::get('/users/{user}/balances', [AdminLeaveBalanceController::class, 'edit'])->name('users.balances.edit');
        Route::put('/users/{user}/balances', [AdminLeaveBalanceController::class, 'update'])->name('users.balances.update');

        Route::prefix('historical')->name('historical.')->group(function () {
            Route::get('/', [HistoricalLeaveController::class, 'index'])->name('index');
            Route::get('/create', [HistoricalLeaveController::class, 'create'])->name('create');
            Route::post('/', [HistoricalLeaveController::class, 'store'])->name('store');
        });
    });
});
