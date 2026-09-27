<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Tuition Fees Configuration
    |--------------------------------------------------------------------------
    |
    | MONEY-HANDLING CONVENTION:
    | All monetary values in this application (including this config, database
    | columns, and API payloads) MUST be stored, calculated, and configured
    | as integer centavos (e.g., 50000 = 500.00 PHP).
    |
    | NEVER use floats for money to prevent precision loss.
    |
    */

    'rate_per_unit_centavos' => (int) env('RATE_PER_UNIT_CENTAVOS', 50000), // Default: 500 PHP per unit

];
