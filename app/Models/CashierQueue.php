<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $queue_date
 * @property string $queue_number
 * @property int $sequence_number
 * @property int $student_id
 * @property int $amount_centavos
 * @property string $payment_type
 * @property string $status
 * @property bool $is_present
 * @property Carbon|null $checked_in_at
 * @property Carbon|null $called_at
 * @property Carbon|null $completed_at
 * @property int|null $called_by
 * @property string|null $or_number
 * @property-read string $amount_formatted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Student $student
 * @property-read User|null $caller
 */
class CashierQueue extends Model
{
    use HasFactory;

    protected $fillable = [
        'queue_date',
        'queue_number',
        'sequence_number',
        'student_id',
        'amount_centavos',
        'payment_type',
        'status',
        'is_present',
        'checked_in_at',
        'called_at',
        'completed_at',
        'called_by',
        'or_number',
    ];

    protected function casts(): array
    {
        return [
            'queue_date' => 'date',
            'sequence_number' => 'integer',
            'amount_centavos' => 'integer',
            'is_present' => 'boolean',
            'checked_in_at' => 'datetime',
            'called_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function getAmountFormattedAttribute(): string
    {
        return Money::format((int) $this->amount_centavos);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function caller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'called_by');
    }
}
