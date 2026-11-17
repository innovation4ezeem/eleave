<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\LeaveYearLock;
use App\Models\User;
use Database\Seeders\LeaveTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LockedYearApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private LeaveType $annual;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->seed(LeaveTypeSeeder::class);

        $this->annual = LeaveType::where('code', 'annual')->firstOrFail();
        $department = Department::create(['name' => 'Front Office']);

        $this->admin = User::factory()->create(['role' => 'admin', 'employee_status' => 'permanent']);
        $this->employee = User::factory()->create([
            'role' => 'employee',
            'employee_status' => 'permanent',
            'department_id' => $department->id,
        ]);
    }

    private function application(int $leaveYear, array $overrides = []): LeaveApplication
    {
        return LeaveApplication::create(array_merge([
            'user_id' => $this->employee->id,
            'leave_type_id' => $this->annual->id,
            'department_id' => $this->employee->department_id,
            'start_date' => "{$leaveYear}-12-29",
            'end_date' => "{$leaveYear}-12-30",
            'start_half' => 'full',
            'end_half' => 'full',
            'total_days' => 2,
            'reason' => 'Year-end trip',
            'status' => 'pending',
            'applied_at' => now(),
            'leave_year' => $leaveYear,
        ], $overrides));
    }

    private function balance(int $leaveYear, float $used = 0): LeaveBalance
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

    private function lock(int $leaveYear): void
    {
        LeaveYearLock::create(['leave_year' => $leaveYear, 'locked_at' => now()]);
    }

    public function test_approving_in_an_open_year_still_deducts_the_balance(): void
    {
        $balance = $this->balance(2026);
        $application = $this->application(2026);

        $this->actingAs($this->admin)
            ->post(route('admin.applications.approve', $application))
            ->assertRedirect();

        $this->assertSame('approved', $application->refresh()->status);
        $this->assertSame('2.0', $balance->refresh()->used_days);
    }

    public function test_approving_in_a_locked_year_is_forbidden_and_changes_nothing(): void
    {
        $balance = $this->balance(2025);
        $application = $this->application(2025);
        $this->lock(2025);

        $this->actingAs($this->admin)
            ->post(route('admin.applications.approve', $application))
            ->assertForbidden();

        $this->assertSame('pending', $application->refresh()->status);
        $this->assertSame('0.0', $balance->refresh()->used_days);
    }

    public function test_rejecting_in_a_locked_year_is_still_allowed(): void
    {
        $this->balance(2025);
        $application = $this->application(2025);
        $this->lock(2025);

        $this->actingAs($this->admin)
            ->post(route('admin.applications.reject', $application))
            ->assertRedirect();

        $this->assertSame('rejected', $application->refresh()->status);
    }

    public function test_application_page_explains_the_lock_instead_of_showing_approve(): void
    {
        $this->balance(2025);
        $application = $this->application(2025);
        $this->lock(2025);

        $this->actingAs($this->admin)
            ->get(route('admin.applications.show', $application))
            ->assertOk()
            ->assertSee('cannot be approved here')
            ->assertDontSee(route('admin.applications.approve', $application));
    }

    public function test_approving_a_cancellation_in_a_locked_year_is_forbidden(): void
    {
        $balance = $this->balance(2025, used: 2);
        $application = $this->application(2025, [
            'status' => 'change_requested',
            'prev_status' => 'approved',
            'change_action' => 'cancel',
        ]);
        $this->lock(2025);

        $this->actingAs($this->admin)
            ->post(route('admin.applications.change.approve', $application))
            ->assertForbidden();

        $this->assertSame('change_requested', $application->refresh()->status);
        $this->assertSame('2.0', $balance->refresh()->used_days);
    }

    public function test_amending_into_a_locked_year_is_forbidden(): void
    {
        $this->balance(2026, used: 2);
        $application = $this->application(2026, [
            'start_date' => '2026-01-05',
            'end_date' => '2026-01-06',
            'status' => 'change_requested',
            'prev_status' => 'approved',
            'change_action' => 'amend',
            'requested_leave_type_id' => $this->annual->id,
            'requested_start_date' => '2025-12-29',
            'requested_end_date' => '2025-12-30',
            'requested_start_half' => 'full',
            'requested_end_half' => 'full',
            'requested_total_days' => 2,
        ]);
        $this->lock(2025);

        $this->actingAs($this->admin)
            ->post(route('admin.applications.change.approve', $application))
            ->assertForbidden();
    }

    public function test_historical_tool_can_still_backfill_a_locked_year(): void
    {
        $balance = $this->balance(2025);
        $this->lock(2025);

        $this->actingAs($this->admin)->post(route('admin.historical.store'), [
            'user_id' => $this->employee->id,
            'leave_type_id' => $this->annual->id,
            'start_date' => '2025-03-03',
            'end_date' => '2025-03-03',
            'start_half' => 'full',
            'end_half' => 'full',
            'reason' => 'Backfill from paper records',
            'status' => 'approved',
        ])->assertRedirect();

        $this->assertSame('1.0', $balance->refresh()->used_days);
    }
}
