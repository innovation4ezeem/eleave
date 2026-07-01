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
        return [
            'leave_type_id' => ['required', 'integer', 'exists:leave_types,id'],
            'min_years' => [
                'required', 'integer', 'min:0', 'max:60',
                Rule::unique('leave_entitlement_tiers', 'min_years')
                    ->where('leave_type_id', $this->input('leave_type_id')),
            ],
            'days' => ['required', 'numeric', 'min:0', 'max:365'],
        ];
    }

    public function messages(): array
    {
        return [
            'min_years.unique' => 'A tier with this minimum years already exists for the selected leave type.',
        ];
    }
}
