<?php

namespace App\Policies;

use App\Models\Penalty;
use App\Models\User;

class PenaltyPolicy
{
    public function view(User $user, Penalty $penalty): bool
    {
        if ($user->isAdmin()) return true;

        // HOD sees their dept
        if ($user->isHod() && $user->department_id === $penalty->department_id) return true;

        // Personal penalty
        if ($penalty->user_id && $user->id === $penalty->user_id) return true;

        // Dept-wide penalty (no subdivision scope)
        if (! $penalty->user_id && $penalty->department_id === $user->department_id && ! $penalty->subdivision_id) return true;

        // Subdivision-scoped penalty: employee must be in that subdivision
        if (! $penalty->user_id && $penalty->subdivision_id) {
            return $user->subdivisions()->where('subdivisions.id', $penalty->subdivision_id)->exists();
        }

        return false;
    }

    public function viewDocument(User $user, Penalty $penalty): bool
    {
        return $this->view($user, $penalty);
    }
}
