<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
    ];

    public function courses()
    {
        return $this->hasMany(Course::class);
    }

    public function departmentStaff()
    {
        return $this->hasMany(User::class);
    }

    public function clearances()
    {
        return $this->hasMany(Clearance::class);
    }
}
