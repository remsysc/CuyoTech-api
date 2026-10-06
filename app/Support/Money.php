<?php

namespace App\Support;

class Money
{
    /**
     * Format integer centavos into a Philippine Peso string (e.g. 150000 -> "₱1,500.00").
     */
    public static function format(int $centavos): string
    {
        return '₱'.number_format($centavos / 100, 2);
    }
}
