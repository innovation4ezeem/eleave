<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveBalanceService;
use App\Services\LeaveYearRolloverService;
use Database\Seeders\LeaveTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveYearRolloverServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeaveYearRolloverService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LeaveTypeSeeder::class);

        $this->service = new LeaveYearRolloverService(new LeaveBalanceService());
    }

    public function test_carry_forward_is_capped_at_five_days(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $annualLeaveType = LeaveType::where('code', 'annual')->firstOrFail();

        LeaveBalance::create([
            'user_id' => $user->id,
            'leave_type_id' => $annualLeaveType->id,
            'leave_year' => 2025,
            'entitlement_days' => 14.0,
            'used_days' => 2.0,
            'adjustment_days' => 0.0,
        ]);

        $carryForwardDays = $this->service->rolloverUser($user, 2026);

        $this->assertSame(5.0, $carryForwardDays);

        $carryForwardType = LeaveType::where('code', 'carry_forward')->firstOrFail();
        $balance = LeaveBalance::where('user_id', $user->id)
            ->where('leave_type_id', $carryForwardType->id)
            ->where('leave_year', 2026)
            ->first();

        $this->assertNotNull($balance);
        $this->assertSame('5.0', $balance->entitlement_days);
    }

    public function test_carry_forward_uses_remaining_days_when_below_cap(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $annualLeaveType = LeaveType::where('code', 'annual')->firstOrFail();

        LeaveBalance::create([
            'user_id' => $user->id,
            'leave_type_id' => $annualLeaveType->id,
            'leave_year' => 2025,
            'entitlement_days' => 14.0,
            'used_days' => 12.0,
            'adjustment_days' => 0.0,
        ]);

        $carryForwardDays = $this->service->rolloverUser($user, 2026);

        $this->assertSame(2.0, $carryForwardDays);
    }

    public function test_carry_forward_is_zero_when_no_previous_balance_exists(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $carryForwardDays = $this->service->rolloverUser($user, 2026);

        $this->assertSame(0.0, $carryForwardDays);
    }

    public function test_carry_forward_is_zero_when_previous_balance_is_negative(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $annualLeaveType = LeaveType::where('code', 'annual')->firstOrFail();

        LeaveBalance::create([
            'user_id' => $user->id,
            'leave_type_id' => $annualLeaveType->id,
            'leave_year' => 2025,
            'entitlement_days' => 14.0,
            'used_days' => 16.0,
            'adjustment_days' => 0.0,
        ]);

        $carryForwardDays = $this->service->rolloverUser($user, 2026);

        $this->assertSame(0.0, $carryForwardDays);
    }
}
