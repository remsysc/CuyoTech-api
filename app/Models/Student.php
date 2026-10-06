<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string $student_number
 * @property string $program
 * @property int $year_level
 * @property string $status
 * @property int $balance_centavos
 * @property-read string $balance_formatted
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_number',
        'program',
        'year_level',
        'status',
        'balance_centavos',
    ];

    protected function casts(): array
    {
        return [
            'year_level' => 'integer',
            'balance_centavos' => 'integer',
        ];
    }

    public function getBalanceFormattedAttribute(): string
    {
        return Money::format((int) $this->balance_centavos);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'enrollments')
            ->withPivot(['school_year', 'semester', 'grade', 'status'])
            ->withTimestamps();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function clearances(): HasMany
    {
        return $this->hasMany(Clearance::class);
    }

    public function documentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class);
    }
}
