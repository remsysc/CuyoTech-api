<?php

use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->cashier = User::factory()->create([
        'role' => 'cashier',
    ]);
    $this->studentUser = User::factory()->create([
        'role' => 'student',
    ]);
    $this->student = Student::factory()->create([
        'user_id' => $this->studentUser->id,
        'student_number' => '20268888',
        'balance_centavos' => 125000,
    ]);
});

test('cashier can list students with balance', function () {
    Sanctum::actingAs($this->cashier);

    $response = $this->getJson('/api/cashier/students');

    $response->assertOk()
        ->assertJsonFragment([
            'student_number' => '20268888',
            'balance_centavos' => 125000,
        ]);
});

test('cashier can list recent payments', function () {
    Sanctum::actingAs($this->cashier);

    $payment = Payment::create([
        'student_id' => $this->student->id,
        'cashier_id' => $this->cashier->id,
        'amount_centavos' => 50000,
        'or_number' => 'OR-2026-99998888',
        'payment_type' => 'tuition',
        'paid_at' => now(),
    ]);

    $response = $this->getJson('/api/cashier/payments');

    $response->assertOk()
        ->assertJsonFragment([
            'or_number' => 'OR-2026-99998888',
            'amount_centavos' => 50000,
            'payment_type' => 'tuition',
        ]);
});

test('student cannot access cashier endpoints', function () {
    Sanctum::actingAs($this->studentUser);

    $this->getJson('/api/cashier/students')->assertForbidden();
    $this->getJson('/api/cashier/payments')->assertForbidden();
});
