<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePenaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_type'     => ['required', 'in:individual,department'],
            'user_id'         => ['nullable', 'required_if:target_type,individual', 'integer', 'exists:users,id'],
            'department_id'   => ['nullable', 'required_if:target_type,department', 'integer', 'exists:departments,id'],
            'subdivision_id'  => ['nullable', 'integer', 'exists:subdivisions,id'],
            'penalty_date'    => ['required', 'date'],
            'mistake'         => ['required', 'string', 'max:2000'],
            'penalty_type'    => ['required', 'in:warning,cash'],
            'penalty_level'   => ['required', 'in:1,2,3'],
            'penalty_charges' => ['nullable', 'required_if:penalty_type,cash', 'numeric', 'min:0'],
            'document'        => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'reported_by'     => ['nullable', 'string', 'max:255'],
            'remarks'         => ['nullable', 'string', 'max:2000'],
        ];
    }
}
