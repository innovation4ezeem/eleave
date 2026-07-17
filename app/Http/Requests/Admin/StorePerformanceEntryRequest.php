<?php

namespace App\Http\Requests\Admin;

use App\Models\PerformanceInput;
use Illuminate\Foundation\Http\FormRequest;

class StorePerformanceEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ];

        // Each input is optional — admins may fill some now and the rest later.
        foreach (PerformanceInput::pluck('key') as $key) {
            $rules["values.$key"] = ['nullable', 'numeric', 'min:0'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        $attributes = [];
        foreach (PerformanceInput::all() as $input) {
            $attributes["values.$input->key"] = $input->label;
        }

        return $attributes;
    }
}
