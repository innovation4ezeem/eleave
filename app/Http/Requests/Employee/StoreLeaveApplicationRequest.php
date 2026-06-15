<?php

namespace App\Http\Requests\Employee;

use App\Models\LeaveType;
use App\Rules\NoOverlappingLeave;
use App\Services\LeaveDayCalculator;
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
        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => [
                'required',
                'date',
                new NoOverlappingLeave($this->user()->id, $this->input('end_date')),
            ],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_half' => ['required', 'in:full,am,pm'],
            'end_half' => ['required', 'in:full,am,pm'],
            'reason' => ['required', 'string', 'max:2000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('start_date') || ! $this->filled('end_date')) {
                return;
            }

            $leaveType = LeaveType::find($this->input('leave_type_id'));

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
            }
        });
    }
}
