<?php

namespace App\Http\Requests\Employee;

use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Services\LeaveDayCalculator;
use App\Services\LeaveEntitlementService;
use App\Support\LeaveYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RequestLeaveChangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'change_action' => ['required', 'in:amend,cancel'],
            'reason' => ['required', 'string', 'max:2000'],
        ];

        if ($this->input('change_action') === 'amend') {
            $rules['leave_type_id'] = ['required', 'integer', 'exists:leave_types,id'];
            $rules['start_date'] = ['required', 'date'];
            $rules['end_date'] = ['required', 'date', 'after_or_equal:start_date'];
            $rules['start_half'] = ['required', 'in:full,am,pm'];
            $rules['end_half'] = ['required', 'in:full,am,pm'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->input('change_action') !== 'amend') {
                return;
            }

            /** @var LeaveApplication $current */
            $current = $this->route('leaveApplication');
            $leaveType = LeaveType::find($this->input('leave_type_id'));

            $entitledIds = $this->user()->entitledLeaveTypes()->pluck('leave_types.id')->all();
            if ($leaveType && (! $leaveType->is_active || ! in_array($leaveType->id, $entitledIds))) {
                $validator->errors()->add('leave_type_id', 'You are not entitled to this leave type.');
            }

            $calculator = new LeaveDayCalculator();
            $totalDays = $calculator->calculate(
                $this->input('start_date'),
                $this->input('end_date'),
                $this->input('start_half'),
                $this->input('end_half'),
            );

            if ($totalDays <= 0) {
                $validator->errors()->add('end_date', 'The selected date range contains no working days.');
                return;
            }

            $overlap = LeaveApplication::overlapping(
                $this->user()->id,
                $this->input('start_date'),
                $this->input('end_date'),
                $this->input('start_half'),
            )->where('id', '!=', $current->id)->exists();

            if ($overlap) {
                $validator->errors()->add('start_date', 'The new dates overlap with another leave application of yours.');
            }

            if ($leaveType && $leaveType->requires_document && ! $current->hasDocument()) {
                $validator->errors()->add('leave_type_id', "A supporting document is required for {$leaveType->name}. Please cancel this leave and re-apply instead.");
            }

            $leaveYear = LeaveYear::forDate($this->input('start_date'));
            $this->checkBalance($validator, $leaveType, $totalDays, $leaveYear, $current);
            $this->checkRewardLeave($validator, $leaveType, $leaveYear);
        });
    }

    private function checkBalance(Validator $validator, ?LeaveType $leaveType, float $days, int $leaveYear, LeaveApplication $current): void
    {
        if (! $leaveType || ! $leaveType->affects_balance) {
            return;
        }

        $balance = LeaveBalance::where('user_id', $this->user()->id)
            ->where('leave_type_id', $leaveType->id)
            ->where('leave_year', $leaveYear)
            ->first();

        $remaining = $balance
            ? (float) $balance->entitlement_days + (float) $balance->adjustment_days - (float) $balance->used_days
            : app(LeaveEntitlementService::class)->entitlementFor($this->user(), $leaveType, $leaveYear);

        // If staying on the same leave type and the current leave is already approved
        // (already deducted), add those days back since they will be restored on approval.
        if ($current->status === 'approved' && $current->leave_type_id === $leaveType->id) {
            $remaining += (float) $current->total_days;
        }

        if ($days > $remaining) {
            $validator->errors()->add(
                'leave_type_id',
                "You only have {$remaining} day(s) of {$leaveType->name} remaining for this leave year, but this change requires {$days} day(s)."
            );
        }
    }

    private function checkRewardLeave(Validator $validator, ?LeaveType $leaveType, int $leaveYear): void
    {
        if (! $leaveType || $leaveType->code !== 'reward') {
            return;
        }

        $annualType = LeaveType::where('code', 'annual')->first();
        if (! $annualType) {
            return;
        }

        $annualBalance = LeaveBalance::where('user_id', $this->user()->id)
            ->where('leave_type_id', $annualType->id)
            ->where('leave_year', $leaveYear)
            ->first();

        $annualRemaining = $annualBalance
            ? (float) $annualBalance->entitlement_days + (float) $annualBalance->adjustment_days - (float) $annualBalance->used_days
            : app(LeaveEntitlementService::class)->entitlementFor($this->user(), $annualType, $leaveYear);

        if ($annualRemaining > 0) {
            $validator->errors()->add(
                'leave_type_id',
                "Reward Leave can only be used when your Annual Leave is fully exhausted. You still have {$annualRemaining} Annual Leave day(s) remaining."
            );
        }
    }
}
