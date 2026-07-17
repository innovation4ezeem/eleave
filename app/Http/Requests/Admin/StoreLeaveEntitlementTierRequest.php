<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeaveEntitlementTierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $uniqueRule = Rule::unique('leave_entitlement_tiers')
            ->where('leave_type_id', $this->input('leave_type_id'));

        // Include employee_status in the uniqueness check, handling NULLs properly
        if ($this->input('employee_status')) {
            $uniqueRule->where('employee_status', $this->input('employee_status'));
        } else {
            $uniqueRule->whereNull('employee_status');
        }

        // When updating, exclude the current tier from uniqueness check
        if ($this->method() === 'PUT' && $this->route('entitlementTier')) {
            $uniqueRule->ignore($this->route('entitlementTier')->id);
        }

        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'min_years' => ['required', 'integer', 'min:0', 'max:60', $uniqueRule],
            'employee_status' => ['nullable', 'string', 'in:probation,intern,permanent'],
            'days' => ['required', 'numeric', 'min:0', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            'min_years.unique' => 'A tier with this minimum years and employee status already exists for the selected leave type.',
            'employee_status.in' => 'The selected employee status is invalid.',
        ];
    }
}
