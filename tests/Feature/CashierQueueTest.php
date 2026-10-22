<?php

use App\Models\CashierQueue;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->cashier = User::factory()->create([
        'role' => 'cashier',
        'name' => 'Elena Villanueva',
    ]);

    $this->studentUser1 = User::factory()->create([
        'role' => 'student',
        'name' => 'Student One',
    ]);
    $this->student1 = Student::factory()->create([
        'user_id' => $this->studentUser1->id,
        'student_number' => '20269001',
        'balance_centavos' => 150000,
    ]);

    $this->studentUser2 = User::factory()->create([
        'role' => 'student',
        'name' => 'Student Two',
    ]);
    $this->student2 = Student::factory()->create([
        'user_id' => $this->studentUser2->id,
        'student_number' => '20269002',
        'balance_centavos' => 200000,
    ]);
});

test('student can fetch own balance and payment receipts via /api/student/payments', function () {
    Payment::create([
        'student_id' => $this->student1->id,
        'cashier_id' => $this->cashier->id,
        'amount_centavos' => 50000,
        'or_number' => 'OR-2026-11223344',
        'payment_type' => 'tuition',
        'paid_at' => now(),
    ]);

    Sanctum::actingAs($this->studentUser1);

    $this->getJson('/api/student/payments')
        ->assertOk()
        ->assertJsonPath('student_number', '20269001')
        ->assertJsonPath('balance_centavos', 150000)
        ->assertJsonPath('balance_formatted', '₱1,500.00')
        ->assertJsonCount(1, 'payments')
        ->assertJsonPath('payments.0.or_number', 'OR-2026-11223344');
});

test('student receives consecutive queue number and cannot hold duplicate active tickets', function () {
    Sanctum::actingAs($this->studentUser1);

    $first = $this->postJson('/api/student/queue', [
        'amount_centavos' => 50000,
        'payment_type' => 'tuition',
        'is_present' => false,
    ]);

    $first->assertCreated()
        ->assertJsonPath('queue_number', 'C-001')
        ->assertJsonPath('sequence_number', 1)
        ->assertJsonPath('status', 'waiting')
        ->assertJsonPath('is_present', false);

    // Attempting to join again while waiting returns 409
    $this->postJson('/api/student/queue', [
        'amount_centavos' => 50000,
        'payment_type' => 'tuition',
    ])->assertStatus(409)
        ->assertJsonPath('error', 'ACTIVE_QUEUE_EXISTS');

    // Second student gets C-002
    Sanctum::actingAs($this->studentUser2);

    $second = $this->postJson('/api/student/queue', [
        'amount_centavos' => 75000,
        'payment_type' => 'misc_fee',
        'is_present' => true,
    ]);

    $second->assertCreated()
        ->assertJsonPath('queue_number', 'C-002')
        ->assertJsonPath('sequence_number', 2)
        ->assertJsonPath('is_present', true);
});

test('student can check in at window and cancel queue ticket', function () {
    Sanctum::actingAs($this->studentUser1);

    $this->postJson('/api/student/queue', [
        'amount_centavos' => 50000,
        'payment_type' => 'tuition',
        'is_present' => false,
    ])->assertCreated();

    // Check in as present at window
    $this->patchJson('/api/student/queue/presence', [
        'is_present' => true,
    ])->assertOk()
        ->assertJsonPath('is_present', true);

    // Status reflects active ticket
    $this->getJson('/api/student/queue')
        ->assertOk()
        ->assertJsonPath('active_ticket.queue_number', 'C-001')
        ->assertJsonPath('active_ticket.is_present', true)
        ->assertJsonPath('summary.waiting_present', 1);

    // Cancel ticket
    $this->deleteJson('/api/student/queue')
        ->assertOk()
        ->assertJsonPath('status', 'cancelled')
        ->assertJsonPath('is_present', false);
});

test('cashier call-next prioritizes physically present waiting students over absent earlier numbers', function () {
    // Student 1 gets C-001 (Not present / En route)
    Sanctum::actingAs($this->studentUser1);
    $this->postJson('/api/student/queue', [
        'amount_centavos' => 50000,
        'payment_type' => 'tuition',
        'is_present' => false,
    ])->assertCreated();

    // Student 2 gets C-002 (Physically present at window)
    Sanctum::actingAs($this->studentUser2);
    $this->postJson('/api/student/queue', [
        'amount_centavos' => 75000,
        'payment_type' => 'tuition',
        'is_present' => true,
    ])->assertCreated();

    // Cashier inspects queue
    Sanctum::actingAs($this->cashier);
    $this->getJson('/api/cashier/queue')
        ->assertOk()
        ->assertJsonPath('summary.waiting_total', 2)
        ->assertJsonPath('summary.waiting_present', 1)
        ->assertJsonPath('summary.waiting_en_route', 1);

    // Cashier clicks Call Next Present -> Calls C-002 (Student 2) because C-001 is not at the window
    $this->postJson('/api/cashier/queue/call-next')
        ->assertOk()
        ->assertJsonPath('queue_number', 'C-002')
        ->assertJsonPath('status', 'serving')
        ->assertJsonPath('student_number', '20269002');
});

test('recording a payment automatically completes the student active queue ticket with OR number', function () {
    Sanctum::actingAs($this->studentUser1);
    $ticketResp = $this->postJson('/api/student/queue', [
        'amount_centavos' => 50000,
        'payment_type' => 'tuition',
        'is_present' => true,
    ])->assertCreated();

    $ticketId = $ticketResp->json('id');

    Sanctum::actingAs($this->cashier);
    $paymentResp = $this->postJson('/api/cashier/payments', [
        'student_id' => $this->student1->id,
        'amount_centavos' => 50000,
        'payment_type' => 'tuition',
    ])->assertCreated();

    $orNumber = $paymentResp->json('or_number');

    $ticket = CashierQueue::findOrFail($ticketId);
    expect($ticket->status)->toBe('completed')
        ->and($ticket->or_number)->toBe($orNumber)
        ->and($ticket->completed_at)->not->toBeNull();
});

test('role boundaries block students from cashier queue management and cashiers from student queue endpoints', function () {
    Sanctum::actingAs($this->studentUser1);
    $this->getJson('/api/cashier/queue')->assertForbidden();
    $this->postJson('/api/cashier/queue/call-next')->assertForbidden();

    Sanctum::actingAs($this->cashier);
    $this->getJson('/api/student/queue')->assertForbidden();
    $this->postJson('/api/student/queue', [
        'amount_centavos' => 50000,
        'payment_type' => 'tuition',
    ])->assertForbidden();
});
