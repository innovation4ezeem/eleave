<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLeaveEntitlementTierRequest;
use App\Models\LeaveEntitlementTier;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class LeaveEntitlementTierController extends Controller
{
    public function index(): View
    {
        $leaveTypes = LeaveType::active()
            ->with(['entitlementTiers' => fn ($q) => $q->orderBy('min_years')])
            ->orderBy('sort_order')
            ->get();

        return view('admin.entitlement-tiers.index', compact('leaveTypes'));
    }

    public function store(StoreLeaveEntitlementTierRequest $request): RedirectResponse
    {
        LeaveEntitlementTier::create($request->validated());

        return redirect()->route('admin.entitlement-tiers.index')->with('success', 'Entitlement tier added.');
    }

    public function destroy(LeaveEntitlementTier $entitlementTier): RedirectResponse
    {
        $entitlementTier->delete();

        return redirect()->route('admin.entitlement-tiers.index')->with('success', 'Entitlement tier removed.');
    }
}
