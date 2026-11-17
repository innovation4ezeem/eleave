<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeaveType;
use App\Models\LeaveYearLock;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveYearRolloverButtonTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private LeaveType $annual;

    private LeaveType $carryForward;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(LeaveTypeSeeder::class);

        $this->annual = LeaveType::where('code', 'annual')->firstOrFail();
        $this->carryForward = LeaveType::where('code', 'carry_forward')->firstOrFail();

        $this->admin = User::factory()->create(['role' => 'admin', 'employee_status' => 'permanent', 'name' => 'Admin']);
        $this->employee = User::factory()->create(['role' => 'employee', 'employee_status' => 'permanent', 'name' => 'Aina']);

        // 2026: 12 days entitled, 9 used -> 3 unused.
        $this->annualBalance(2026, used: 9);

        $this->travelTo('2027-01-02 09:00:00');
    }

    private function annualBalance(int $leaveYear, float $used): LeaveBalance
    {
        return LeaveBalance::create([
            'user_id' => $this->employee->id,
            'leave_type_id' => $this->annual->id,
            'leave_year' => $leaveYear,
            'entitlement_days' => 12,
            'used_days' => $used,
            'adjustment_days' => 0,
        ]);
    }

    private function carryForwardFor(int $leaveYear): ?LeaveBalance
    {
        return LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type_id', $this->carryForward->id)
            ->where('leave_year', $leaveYear)
            ->first();
    }

    public function test_leave_years_page_offers_the_button_for_the_current_year_in_january(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.leave-years.index'))
            ->assertOk()
            ->assertSee(route('admin.leave-years.rollover.preview', 2027))
            ->assertSee('Run year-end rollover');
    }

    public function test_button_is_hidden_outside_january_and_february(): void
    {
        $this->travelTo('2026-11-17 09:00:00');

        $this->actingAs($this->admin)
            ->get(route('admin.leave-years.index'))
            ->assertOk()
            ->assertDontSee(route('admin.leave-years.rollover.preview', 2026))
            ->assertDontSee('Run year-end rollover');
    }

    public function test_preview_shows_the_carry_forward_without_changing_anything(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.leave-years.rollover.preview', 2027))
            ->assertOk()
            ->assertSee('Aina')
            ->assertSee('3.0')
            ->assertSee('Run rollover');

        $this->assertNull($this->carryForwardFor(2027));
        $this->assertFalse(LeaveYearLock::isLocked(2026));
    }

    public function test_running_sets_carry_forward_and_locks_the_previous_year(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.leave-years.rollover', 2027))
            ->assertRedirect(route('admin.leave-years.index'))
            ->assertSessionHas('success');

        $this->assertSame('3.0', $this->carryForwardFor(2027)->entitlement_days);
        $this->assertTrue(LeaveYearLock::isLocked(2026));
        $this->assertSame($this->admin->id, LeaveYearLock::where('leave_year', 2026)->value('locked_by'));

        // New-year Annual Leave was provisioned too (if entitled).
        $this->employee->entitledLeaveTypes()->attach($this->annual->id);
        $this->actingAs($this->admin)->post(route('admin.leave-years.rollover', 2027));
        $this->assertNotNull(LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type_id', $this->annual->id)->where('leave_year', 2027)->first());
    }

    public function test_re_run_after_late_approval_updates_and_logs_the_carry_forward(): void
    {
        $this->actingAs($this->admin)->post(route('admin.leave-years.rollover', 2027));
        $this->assertSame('3.0', $this->carryForwardFor(2027)->entitlement_days);

        // A late 2-day December approval (after unlocking) leaves 1 unused day.
        LeaveBalance::where('user_id', $this->employee->id)
            ->where('leave_type_id', $this->annual->id)
            ->where('leave_year', 2026)
            ->update(['used_days' => 11]);

        $this->actingAs($this->admin)->post(route('admin.leave-years.rollover', 2027));

        $carryForward = $this->carryForwardFor(2027);
        $this->assertSame('1.0', $carryForward->entitlement_days);
        $this->assertDatabaseHas('leave_balance_adjustments', [
            'leave_balance_id' => $carryForward->id,
            'changed_by' => $this->admin->id,
            'field_changed' => 'entitlement_days',
            'old_value' => 3.0,
            'new_value' => 1.0,
        ]);
    }

    public function test_cannot_run_for_a_future_year(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.leave-years.rollover', 2028))
            ->assertSessionHas('error');

        $this->assertFalse(LeaveYearLock::isLocked(2027));
        $this->assertNull($this->carryForwardFor(2028));
    }

    public function test_cannot_run_after_carry_forward_expired_on_1_march(): void
    {
        $this->travelTo('2027-03-01 00:10:00');

        $this->actingAs($this->admin)
            ->post(route('admin.leave-years.rollover', 2027))
            ->assertSessionHas('error');

        $this->assertNull($this->carryForwardFor(2027));
        $this->assertFalse(LeaveYearLock::isLocked(2026));
    }

    public function test_hod_cannot_run_the_rollover(): void
    {
        $department = Department::create(['name' => 'Housekeeping']);
        $hod = User::factory()->create(['role' => 'hod', 'employee_status' => 'permanent', 'department_id' => $department->id]);

        $this->actingAs($hod)->post(route('admin.leave-years.rollover', 2027))->assertForbidden();

        $this->assertNull($this->carryForwardFor(2027));
    }

    public function test_scheduled_command_still_rolls_over_and_locks(): void
    {
        $this->artisan('leave:rollover', ['leave_year' => 2027])->assertSuccessful();

        $this->assertSame('3.0', $this->carryForwardFor(2027)->entitlement_days);
        $this->assertTrue(LeaveYearLock::isLocked(2026));
        $this->assertSame(0, LeaveBalanceAdjustment::count());
    }
}
