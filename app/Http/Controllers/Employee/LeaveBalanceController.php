<?php

namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Support\LeaveYear;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveBalanceController extends Controller
{
    public function index(Request $request): View
    {
        $leaveYear = (int) $request->query('leave_year', LeaveYear::current());

        $balances = $request->user()->leaveBalances()
            ->with('leaveType')
            ->where('leave_year', $leaveYear)
            ->get()
            ->sortBy(fn ($balance) => $balance->leaveType->sort_order);

        return view('employee.balances.index', compact('balances', 'leaveYear'));
    }
}
