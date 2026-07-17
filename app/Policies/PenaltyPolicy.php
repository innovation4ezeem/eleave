<?php

namespace App\Policies;

use App\Models\Penalty;
use App\Models\User;

class PenaltyPolicy
{
    public function view(User $user, Penalty $penalty): bool
    {
        return $user->id === $penalty->user_id
            || $user->isAdmin()
            || ($user->isHod() && $user->department_id === $penalty->department_id);
    }

    public function viewDocument(User $user, Penalty $penalty): bool
    {
        return $this->view($user, $penalty);
    }
}
