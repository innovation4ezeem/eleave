<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarningLetterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $letter = $this->route('warningLetter');

        return $letter !== null && ($this->user()?->can('update', $letter) ?? false);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'issued_date' => ['required', 'date'],
            'level' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'file' => [
                'nullable',
                'file',
                'mimes:'.implode(',', config('warning_letters.allowed_extensions', ['pdf', 'jpg', 'jpeg', 'png'])),
                'max:'.config('warning_letters.max_kb', 5120),
            ],
        ];
    }
}
