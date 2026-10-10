<?php

namespace App\Services;

use App\Models\Student;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BalanceGuard
{
    /**
     * Ensure student has zero balance.
     *
     * @throws HttpException with 422 OUTSTANDING_BALANCE
     */
    public function ensureZeroBalance(Student $student): void
    {
        if ((int) $student->balance_centavos > 0) {
            abort(422, 'OUTSTANDING_BALANCE');
        }
    }
}
