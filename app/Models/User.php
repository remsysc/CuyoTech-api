<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department_id',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // -------------------------------------------------------------------------
    // STI Role Checks
    // -------------------------------------------------------------------------

    public function hasRole(string $role): bool
    {
        return $this->role === $role;
    }

    public function isStudent(): bool
    {
        return $this->hasRole('student');
    }

    public function isRegistrar(): bool
    {
        return $this->hasRole('registrar');
    }

    public function isCashier(): bool
    {
        return $this->hasRole('cashier');
    }

    public function isDepartmentStaff(): bool
    {
        return $this->hasRole('department_staff');
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    public function processedDocumentRequests(): HasMany
    {
        return $this->hasMany(DocumentRequest::class, 'processed_by');
    }

    public function reviewedClearances(): HasMany
    {
        return $this->hasMany(Clearance::class, 'reviewed_by');
    }

    public function processedPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'cashier_id');
    }

    public function auditLogsAsActor(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'actor_id');
    }
}
