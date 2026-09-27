<?php

namespace App\Policies;

use App\Models\User;

class CashierPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->hasRole('cashier')) {
            return true;
        }

        return null;
    }
}
