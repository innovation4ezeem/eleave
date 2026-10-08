<?php

namespace App\Http\Requests\Admin;

use App\Models\WarningLetter;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarningLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', WarningLetter::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'issued_date' => ['required', 'date'],
            'level' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'file' => [
                'required',
                'file',
                'mimes:'.implode(',', config('warning_letters.allowed_extensions', ['pdf', 'jpg', 'jpeg', 'png'])),
                'max:'.config('warning_letters.max_kb', 5120),
            ],
        ];
    }
}
