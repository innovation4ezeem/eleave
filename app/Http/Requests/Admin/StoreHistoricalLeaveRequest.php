<?php

namespace App\Http\Requests\Admin;

use App\Services\LeaveDayCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreHistoricalLeaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'start_half' => ['required', 'in:full,am,pm'],
            'end_half' => ['required', 'in:full,am,pm'],
            'reason' => ['required', 'string', 'max:2000'],
            'status' => ['required', 'in:pending,approved,rejected,cancelled'],
            'remarks' => ['nullable', 'string', 'max:1000'],
            'applied_at' => ['nullable', 'date'],
            'decided_at' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('start_date') || ! $this->filled('end_date')) {
                return;
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
