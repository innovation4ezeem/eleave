<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreWorkExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorize before validation runs. On update the owner may be
        // soft-deleted, so resolve with trashed to avoid a null target
        // (which the gate denies) and still let admins manage orphaned rows.
        $target = $this->route('user')
            ?? $this->route('workExperience')?->user()->withTrashed()->first();

        return $target instanceof User && Gate::allows('manage-profile', $target);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'The end date must be on or after the start date.',
        ];
    }
}
