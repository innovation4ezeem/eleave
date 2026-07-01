<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->whereNull('deleted_at')],
            'password' => ['required', 'string', Password::min(8)],
            'role' => ['required', 'in:admin,hod,employee'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'employee_no' => ['nullable', 'string', 'max:50'],
            'designation' => ['nullable', 'string', 'max:100'],
            'joined_at' => ['nullable', 'date'],
            'is_active' => ['sometimes', 'boolean'],
            'leave_type_ids' => ['array'],
            'leave_type_ids.*' => ['integer', 'exists:leave_types,id'],
        ];
    }
}
