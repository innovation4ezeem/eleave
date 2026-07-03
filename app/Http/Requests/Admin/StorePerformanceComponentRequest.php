<?php

namespace App\Http\Requests\Admin;

use App\Models\PerformanceInput;
use App\Services\ExpressionEvaluator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StorePerformanceComponentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'expression' => ['required', 'string', 'max:500'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (! $this->filled('expression')) {
                return;
            }

            $allowedKeys = PerformanceInput::pluck('key')->all();
            $error = (new ExpressionEvaluator())->validate($this->input('expression'), $allowedKeys);

            if ($error !== null) {
                $validator->errors()->add('expression', $error);
            }
        });
    }
}
