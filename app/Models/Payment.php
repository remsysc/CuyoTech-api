<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $student_id
 * @property int $cashier_id
 * @property int $amount_centavos
 * @property string $or_number
 * @property string $payment_type
 * @property Carbon $paid_at
 * @property-read string $amount_formatted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Student $student
 * @property-read User $cashier
 */
class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'cashier_id',
        'amount_centavos',
        'or_number',
        'payment_type',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount_centavos' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    public function getAmountFormattedAttribute(): string
    {
        return Money::format((int) $this->amount_centavos);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
