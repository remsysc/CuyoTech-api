<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('creates a clean Student via factory with user relationship', function () {
    $student = Student::factory()->create();

    expect($student->exists)->toBeTrue()
        ->and($student->user)->not->toBeNull()
        ->and($student->user->role)->toBe('student')
        ->and($student->balance_centavos)->toBe(0);
});

it('creates a clean Course via factory with department relationship', function () {
    $course = Course::factory()->create();

    expect($course->exists)->toBeTrue()
        ->and($course->department)->not->toBeNull()
        ->and($course->units)->toBeGreaterThanOrEqual(1);
});

it('creates a clean Enrollment via factory', function () {
    $enrollment = Enrollment::factory()->create();

    expect($enrollment->exists)->toBeTrue()
        ->and($enrollment->student)->not->toBeNull()
        ->and($enrollment->course)->not->toBeNull()
        ->and($enrollment->status)->toBe('enrolled')
        ->and($enrollment->grade)->toBeNull();
});

it('creates a clean Payment via factory with all required fields', function () {
    $payment = Payment::factory()->create();

    expect($payment->exists)->toBeTrue()
        ->and($payment->student)->not->toBeNull()
        ->and($payment->cashier)->not->toBeNull()
        ->and($payment->cashier->role)->toBe('cashier')
        ->and($payment->payment_type)->toBe('tuition')
        ->and($payment->paid_at)->not->toBeNull();
});

it('creates payments with specific payment types using factory states', function () {
    $tuition = Payment::factory()->tuition()->create();
    $misc = Payment::factory()->miscFee()->create();
    $doc = Payment::factory()->documentFee()->create();

    expect($tuition->payment_type)->toBe('tuition')
        ->and($misc->payment_type)->toBe('misc_fee')
        ->and($doc->payment_type)->toBe('document_fee');
});
