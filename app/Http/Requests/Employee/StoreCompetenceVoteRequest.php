<?php

namespace App\Http\Requests\Employee;

use App\Models\CompetenceCategory;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreCompetenceVoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'votee_id' => ['required', 'integer', 'exists:users,id'],
            'category_id' => ['required', 'integer', 'exists:competence_categories,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $voteeId = (int) $this->input('votee_id');

            if ($voteeId === $this->user()->id) {
                $validator->errors()->add('votee_id', 'You cannot vote for yourself.');
                return;
            }

            $votee = User::find($voteeId);
            if (! $votee || ! $votee->is_active || $votee->isAdmin()) {
                $validator->errors()->add('votee_id', 'You can only vote for an active employee or HOD.');
            }

            $category = CompetenceCategory::find($this->input('category_id'));
            if (! $category || ! $category->is_active) {
                $validator->errors()->add('category_id', 'That category is not available for voting.');
            }
        });
    }
}
