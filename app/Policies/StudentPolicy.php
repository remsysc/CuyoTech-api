<?php

namespace App\Policies;

use App\Models\User;

class StudentPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->hasRole('student')) {
            return true;
        }

        return null;
    }
}
