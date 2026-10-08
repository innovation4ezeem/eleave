<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarningLetter;

class WarningLetterPolicy
{
    /** Every role has a (scoped) read-only list. */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /** Admin-only management area (list with filters, forms). */
    public function manage(User $user): bool
    {
        return $user->isAdmin();
    }

    /** The audit history is for admins only; HODs and employees never see it. */
    public function viewHistory(User $user, WarningLetter $warningLetter): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, WarningLetter $warningLetter): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($warningLetter->user_id === $user->id) {
            return true;
        }

        if ($user->isHod()) {
            $employeeDepartment = $warningLetter->employee?->department_id;

            // A missing department on either side must never match.
            return $user->department_id !== null
                && $employeeDepartment !== null
                && (int) $employeeDepartment === (int) $user->department_id;
        }

        return false;
    }

    /**
     * Admins may download any letter; everyone else only their own. A HOD can SEE a department
     * member's letter in the list (view) but cannot download it.
     */
    public function download(User $user, WarningLetter $warningLetter): bool
    {
        return $user->isAdmin() || $warningLetter->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, WarningLetter $warningLetter): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, WarningLetter $warningLetter): bool
    {
        return $user->isAdmin();
    }

    public function restore(User $user, WarningLetter $warningLetter): bool
    {
        return false;
    }

    public function forceDelete(User $user, WarningLetter $warningLetter): bool
    {
        return false;
    }
}
