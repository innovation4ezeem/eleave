<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaveBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'leave_year' => ['required', 'integer'],
            'balances' => ['required', 'array'],
            'balances.*.id' => ['required', 'integer', 'exists:leave_balances,id'],
            'balances.*.entitlement_days' => ['required', 'numeric', 'min:0'],
            'balances.*.used_days' => ['required', 'numeric', 'min:0'],
            'balances.*.adjustment_days' => ['required', 'numeric'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
