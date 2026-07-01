<?php

namespace App\Rules;

use App\Models\LeaveApplication;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoOverlappingLeave implements ValidationRule
{
    public function __construct(
        private int $userId,
        private ?string $endDate,
        private ?int $ignoreApplicationId = null,
        private ?string $startHalf = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value || ! $this->endDate) {
            return;
        }

        $query = LeaveApplication::overlapping($this->userId, $value, $this->endDate, $this->startHalf);

        if ($this->ignoreApplicationId !== null) {
            $query->where('id', '!=', $this->ignoreApplicationId);
        }

        if ($query->exists()) {
            $fail('You already have a pending or approved leave application that overlaps with these dates.');
        }
    }
}
