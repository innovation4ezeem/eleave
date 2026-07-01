<?php

namespace App\Http\Requests\Employee;

use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveType;
use App\Models\LeaveYearLock;
use App\Rules\NoOverlappingLeave;
use App\Services\LeaveDayCalculator;
use App\Services\LeaveEntitlementService;
use App\Support\LeaveYear;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreLeaveApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => [
                'required',
                'date',
                new NoOverlappingLeave($this->user()->id, $this->input('end_date'), null, $this->input('start_half')),
            ],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_half' => ['required', 'in:full,am,pm'],
            'end_half' => ['required', 'in:full,am,pm'],
            'reason' => ['required', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'split_leave' => ['nullable', 'boolean'],
        ];

        if ($this->boolean('split_leave')) {
            $rules['leave_type_id_pm'] = ['required', 'integer', 'exists:leave_types,id'];
        }

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('start_date') || ! $this->filled('end_date')) {
                return;
            }

            // Block applying into a locked (archived) leave year.
            if (LeaveYearLock::isLocked(LeaveYear::forDate($this->input('start_date')))) {
                $validator->errors()->add('start_date', 'That leave year is closed (archived) and no longer accepts new applications.');
                return;
            }

            $isSplit = $this->boolean('split_leave');
            $leaveType = LeaveType::find($this->input('leave_type_id'));

            // Entitlement check — employees may only apply for leave types assigned to them.
            $entitledIds = $this->user()->entitledLeaveTypes()->pluck('leave_types.id')->all();

            if ($leaveType && ! in_array($leaveType->id, $entitledIds)) {
                $validator->errors()->add('leave_type_id', 'You are not entitled to this leave type.');
            }

            if ($isSplit) {
                $pmType = LeaveType::find($this->input('leave_type_id_pm'));
                if ($pmType && ! in_array($pmType->id, $entitledIds)) {
                    $validator->errors()->add('leave_type_id_pm', 'You are not entitled to this leave type.');
                }
            }

            if ($isSplit) {
                if ($this->input('start_date') !== $this->input('end_date')) {
                    $validator->errors()->add('end_date', 'Split leave must be a single day.');
                    return;
                }

                $leaveYear = LeaveYear::forDate($this->input('start_date'));

                // Validate AM half
                $this->checkBalance($validator, $leaveType, 0.5, $leaveYear, 'leave_type_id');
                $this->checkRewardLeave($validator, $leaveType, $leaveYear, 'leave_type_id');

                // Validate PM overlap
                $pmOverlap = LeaveApplication::overlapping(
                    $this->user()->id,
                    $this->input('start_date'),
                    $this->input('start_date'),
                    'pm'
                )->exists();

                if ($pmOverlap) {
                    $validator->errors()->add('leave_type_id_pm', 'You already have a leave application on the PM half of this date.');
                }

                // Validate PM balance and Reward Leave
                $pmLeaveType = LeaveType::find($this->input('leave_type_id_pm'));
                $this->checkBalance($validator, $pmLeaveType, 0.5, $leaveYear, 'leave_type_id_pm');
                $this->checkRewardLeave($validator, $pmLeaveType, $leaveYear, 'leave_type_id_pm');

                return;
            }

            // Non-split: existing validation
            if ($leaveType && $leaveType->requires_document && ! $this->hasFile('document')) {
                $validator->errors()->add('document', "A supporting document is required for {$leaveType->name}.");
            }

            $calculator = new LeaveDayCalculator();
            $totalDays = $calculator->calculate(
                $this->input('start_date'),
                $this->input('end_date'),
                $this->input('start_half', 'full'),
                $this->input('end_half', 'full'),
            );

            if ($totalDays <= 0) {
                $validator->errors()->add('end_date', 'The selected date range contains no working days.');
                return;
            }

            $leaveYear = LeaveYear::forDate($this->input('start_date'));
            $this->checkBalance($validator, $leaveType, $totalDays, $leaveYear, 'leave_type_id');
            $this->checkRewardLeave($validator, $leaveType, $leaveYear, 'leave_type_id');
        });
    }

    private function checkBalance(Validator $validator, ?LeaveType $leaveType, float $days, int $leaveYear, string $field): void
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

        if ($days > $remaining) {
            $validator->errors()->add(
                $field,
                "You only have {$remaining} day(s) of {$leaveType->name} remaining for this leave year, but this application requires {$days} day(s)."
            );
        }
    }

    private function checkRewardLeave(Validator $validator, ?LeaveType $leaveType, int $leaveYear, string $field): void
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
                $field,
                "Reward Leave can only be used when your Annual Leave is fully exhausted. You still have {$annualRemaining} Annual Leave day(s) remaining."
            );
        }
    }
}
