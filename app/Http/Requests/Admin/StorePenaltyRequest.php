<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StorePenaltyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'penalty_date' => ['required', 'date'],
            'mistake' => ['required', 'string', 'max:2000'],
            'penalty_type' => ['required', 'in:warning,cash'],
            'penalty_level' => ['required', 'in:1,2,3'],
            'penalty_charges' => ['nullable', 'required_if:penalty_type,cash', 'numeric', 'min:0'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'reported_by' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
