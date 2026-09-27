<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }
}
