<?php

use App\Models\Payment;
use App\Models\Student;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('formats centavos into Philippine Peso format correctly', function () {
    expect(Money::format(0))->toBe('₱0.00')
        ->and(Money::format(50))->toBe('₱0.50')
        ->and(Money::format(100))->toBe('₱1.00')
        ->and(Money::format(15000))->toBe('₱150.00')
        ->and(Money::format(150000))->toBe('₱1,500.00')
        ->and(Money::format(500000))->toBe('₱5,000.00');
});

it('provides balance_formatted attribute on Student model', function () {
    $student = Student::factory()->create(['balance_centavos' => 250000]);

    expect($student->balance_formatted)->toBe('₱2,500.00');
});

it('provides amount_formatted attribute on Payment model', function () {
    $payment = Payment::factory()->create(['amount_centavos' => 125000]);

    expect($payment->amount_formatted)->toBe('₱1,250.00');
});
