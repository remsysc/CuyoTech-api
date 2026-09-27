<?php

namespace App\Policies;

use App\Models\User;

class AdminPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        return null;
    }
}
