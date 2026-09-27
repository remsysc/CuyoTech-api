<?php

namespace App\Policies;

use App\Models\User;

class DepartmentStaffPolicy
{
    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user): ?bool
    {
        if ($user->hasRole('department_staff')) {
            return true;
        }

        return null;
    }
}
