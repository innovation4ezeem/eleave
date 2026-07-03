<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StorePerformanceInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('key')) {
            // Normalise the key to a safe identifier: lowercase, underscores.
            $this->merge([
                'key' => Str::snake(Str::lower(trim($this->input('key')))),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'key' => ['required', 'string', 'max:50', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:performance_inputs,key'],
            'label' => ['required', 'string', 'max:191'],
        ];
    }

    public function messages(): array
    {
        return [
            'key.regex' => 'The key must start with a letter and contain only lowercase letters, numbers, and underscores (used as the variable name in formulas).',
        ];
    }
}
