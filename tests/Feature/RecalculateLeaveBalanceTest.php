<?php

namespace Tests\Feature;

use App\Models\LeaveBalance;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveEntitlementTier;
use App\Models\LeaveType;
use App\Models\LeaveYearLock;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecalculateLeaveBalanceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private LeaveType $annual;

    private LeaveType $replacement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LeaveTypeSeeder::class);

        $this->annual = LeaveType::where('code', 'annual')->firstOrFail();
        $this->replacement = LeaveType::where('code', 'replacement')->firstOrFail();

        // Annual Leave tiers: 8 days from 0 years, 12 days from 2 years.
        LeaveEntitlementTier::create(['leave_type_id' => $this->annual->id, 'min_years' => 0, 'days' => 8, 'employee_status' => null]);
        LeaveEntitlementTier::create(['leave_type_id' => $this->annual->id, 'min_years' => 2, 'days' => 12, 'employee_status' => null]);

        $this->admin = User::factory()->create(['role' => 'admin', 'employee_status' => 'permanent']);

        // Joined 3 years before the 2026 leave year starts -> 12-day tier.
        $this->employee = User::factory()->create([
            'role' => 'employee',
            'employee_status' => 'permanent',
            'joined_at' => '2023-01-01',
        ]);
        $this->employee->entitledLeaveTypes()->attach([$this->annual->id, $this->replacement->id]);
    }

    private function balance(LeaveType $type, array $values): LeaveBalance
    {
        return LeaveBalance::create(array_merge([
            'user_id' => $this->employee->id,
            'leave_type_id' => $type->id,
            'leave_year' => 2026,
        ], $values));
    }

    private function recalculate(int $leaveYear = 2026)
    {
        return $this->actingAs($this->admin)
            ->post(route('admin.users.balances.recalculate', $this->employee), ['leave_year' => $leaveYear]);
    }

    public function test_recalculate_updates_entitlement_but_keeps_used_and_adjustment_days(): void
    {
        // Stale entitlement from when the employee was on the 8-day tier.
        $annual = $this->balance($this->annual, ['entitlement_days' => 8, 'used_days' => 5.5, 'adjustment_days' => -1]);

        $this->recalculate()->assertRedirect()->assertSessionHas('success');

        $annual->refresh();
        $this->assertSame('12.0', $annual->entitlement_days);
        $this->assertSame('5.5', $annual->used_days);
        $this->assertSame('-1.0', $annual->adjustment_days);
        $this->assertSame('5.5', $annual->remaining_days);

        // Same row, not deleted and recreated.
        $this->assertSame(1, LeaveBalance::where('user_id', $this->employee->id)->where('leave_type_id', $this->annual->id)->count());

        $this->assertDatabaseHas('leave_balance_adjustments', [
            'leave_balance_id' => $annual->id,
            'changed_by' => $this->admin->id,
            'field_changed' => 'entitlement_days',
            'old_value' => 8.0,
            'new_value' => 12.0,
        ]);
    }

    public function test_recalculate_keeps_existing_adjustment_history(): void
    {
        $annual = $this->balance($this->annual, ['entitlement_days' => 8, 'used_days' => 0, 'adjustment_days' => 0]);
        $earlier = LeaveBalanceAdjustment::create([
            'leave_balance_id' => $annual->id,
            'changed_by' => $this->admin->id,
            'field_changed' => 'used_days',
            'old_value' => 0,
            'new_value' => 2,
            'reason' => 'Earlier manual edit',
        ]);

        $this->recalculate();

        $this->assertDatabaseHas('leave_balance_adjustments', ['id' => $earlier->id]);
    }

    public function test_recalculate_leaves_types_without_tiers_untouched(): void
    {
        // Replacement Leave has no tiers; its entitlement is granted manually.
        $replacement = $this->balance($this->replacement, ['entitlement_days' => 3, 'used_days' => 1, 'adjustment_days' => 0]);

        $this->recalculate();

        $replacement->refresh();
        $this->assertSame('3.0', $replacement->entitlement_days);
        $this->assertSame('1.0', $replacement->used_days);
        $this->assertSame(0, LeaveBalanceAdjustment::where('leave_balance_id', $replacement->id)->count());
    }

    public function test_recalculate_with_nothing_to_change_writes_no_adjustments(): void
    {
        $this->balance($this->annual, ['entitlement_days' => 12, 'used_days' => 2, 'adjustment_days' => 0]);

        $this->recalculate()->assertSessionHas('success', 'Entitlements already match the current tiers. Nothing changed.');

        $this->assertSame(0, LeaveBalanceAdjustment::count());
    }

    public function test_recalculate_creates_missing_balances_from_tiers(): void
    {
        $this->recalculate();

        $annual = LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type_id', $this->annual->id)
            ->where('leave_year', 2026)
            ->firstOrFail();

        $this->assertSame('12.0', $annual->entitlement_days);
        $this->assertSame('0.0', $annual->used_days);
    }

    public function test_recalculate_is_refused_for_a_locked_year(): void
    {
        $annual = $this->balance($this->annual, ['entitlement_days' => 8, 'used_days' => 3, 'adjustment_days' => 0]);
        LeaveYearLock::create(['leave_year' => 2026, 'locked_at' => now()]);

        $this->recalculate()->assertSessionHas('error');

        $this->assertSame('8.0', $annual->refresh()->entitlement_days);
    }
}
