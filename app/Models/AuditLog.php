<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $actor_id
 * @property string $action
 * @property string $target_type
 * @property int $target_id
 * @property array<string, mixed>|null $changes
 * @property Carbon|null $created_at
 * @property-read User $actor
 */
class AuditLog extends Model
{
    use HasFactory;

    // We only need created_at for Audit logs
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_id',
        'action',
        'target_type',
        'target_id',
        'changes',
    ];

    protected function casts(): array
    {
        return [
            'changes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
