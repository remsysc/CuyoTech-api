<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
    ];

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    public function departmentStaff(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function clearances(): HasMany
    {
        return $this->hasMany(Clearance::class);
    }
}
