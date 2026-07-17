<?php

use App\Support\LeaveYear;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// Automatically roll over leave balances on the first day of each new leave year (1 Jan)
Schedule::call(function () {
    Artisan::call('leave:rollover', ['leave_year' => LeaveYear::current()]);
})->yearlyOn(1, 1, '00:05');

// Expire any remaining Carry Forward Leave on 1 March (carry forward is usable in January and February only)
Schedule::call(function () {
    Artisan::call('leave:expire-carry-forward', ['leave_year' => LeaveYear::current()]);
})->yearlyOn(3, 1, '00:05');
