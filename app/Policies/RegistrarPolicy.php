<?php

namespace App\Policies;

use App\Models\User;

class RegistrarPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->hasRole('registrar')) {
            return true;
        }

        return null;
    }
}
