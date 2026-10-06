<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Tier 3: Cross-Feature Combinations E2E Tests
|--------------------------------------------------------------------------
|
| Pairwise and multi-feature workflow interactions:
| - Enrollment -> Student Subjects view
| - Enrollment -> Student Grades isolation
| - Enrollment -> Grade Encoding -> Student Grades view
| - Enrollment -> Grade Encoding -> Student Subjects status transition
| - Enrollment -> Cashier Payment -> Student Balance ledger reconciliation
| - Cashier Payment -> Stable Receipt Retrieval & verification
| - Multi-term enrollment and grade isolation
| - Duplicate enrollment rejection on already completed courses
|
*/

describe('Tier 3: Cross-Feature Combinations', function () {
    it('cross-feature: registrar enrolls student, student immediately observes course in subjects list', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id, 'balance_centavos' => 0]);
        $studentToken = $studentUser->createToken('student_token')->plainTextToken;

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('registrar_token')->plainTextToken;

        $dept = Department::factory()->create();
        $course = Course::factory()->create([
            'department_id' => $dept->id,
            'code' => 'CS101',
            'title' => 'Introduction to Computing',
            'units' => 3,
        ]);

        // Registrar enrolls student
        $enrollResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$registrarToken,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $enrollResponse->assertStatus(201);
        $rate = config('fees.rate_per_unit_centavos', 50000);
        expect($student->fresh()->balance_centavos)->toBe(3 * $rate);

        // Student immediately checks subjects list
        $subjectResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$studentToken,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $subjectResponse->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([
                [
                    'course_code' => 'CS101',
                    'title' => 'Introduction to Computing',
                    'units' => 3,
                    'status' => 'enrolled',
                ],
            ]);
    });

    it('cross-feature isolation: newly enrolled course does NOT appear in grades list until graded', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $studentToken = $studentUser->createToken('student_token')->plainTextToken;

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('registrar_token')->plainTextToken;

        $course = Course::factory()->create(['code' => 'MATH101']);

        // Registrar enrolls student
        $this->withHeaders([
            'Authorization' => 'Bearer '.$registrarToken,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ])->assertStatus(201);

        // Student checks grades list -> must be empty because status is 'enrolled', not 'completed'
        $gradesResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$studentToken,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $gradesResponse->assertStatus(200)
            ->assertExactJson([]);
    });

    it('cross-feature: registrar encodes grade, student immediately observes course in grades list', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $studentToken = $studentUser->createToken('student_token')->plainTextToken;

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('registrar_token')->plainTextToken;

        $course = Course::factory()->create(['code' => 'CS102', 'title' => 'Data Structures']);

        // Registrar enrolls student
        $enrollResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$registrarToken,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ])->assertStatus(201);

        $enrollmentId = $enrollResponse->json('id');

        // Registrar encodes grade 1.75
        $gradeResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$registrarToken,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollmentId}/grade", [
            'grade' => 1.75,
        ]);

        $gradeResponse->assertStatus(200)
            ->assertJson([
                'id' => $enrollmentId,
                'status' => 'completed',
            ]);

        // Student views grades list
        $studentGradesResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$studentToken,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $studentGradesResponse->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([
                [
                    'course_code' => 'CS102',
                    'title' => 'Data Structures',
                ],
            ]);

        expect((float) $studentGradesResponse->json('0.grade'))->toBe(1.75);
    });

    it('cross-feature: grading updates enrollment status in subjects list from enrolled to completed', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $studentToken = $studentUser->createToken('student_token')->plainTextToken;

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('registrar_token')->plainTextToken;

        $course = Course::factory()->create(['code' => 'PHYS101', 'title' => 'General Physics']);

        // Registrar enrolls student
        $enrollResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$registrarToken,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ])->assertStatus(201);

        $enrollmentId = $enrollResponse->json('id');

        // Check subjects before grading -> 'enrolled'
        $beforeGrade = $this->withHeaders([
            'Authorization' => 'Bearer '.$studentToken,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $beforeGrade->assertStatus(200)
            ->assertJson([['course_code' => 'PHYS101', 'status' => 'enrolled']]);

        // Registrar records grade 2.00
        $this->withHeaders([
            'Authorization' => 'Bearer '.$registrarToken,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollmentId}/grade", [
            'grade' => 2.00,
        ])->assertStatus(200);

        // Check subjects after grading -> 'completed'
        $afterGrade = $this->withHeaders([
            'Authorization' => 'Bearer '.$studentToken,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $afterGrade->assertStatus(200)
            ->assertJson([['course_code' => 'PHYS101', 'status' => 'completed']]);
    });

    it('cross-feature: enrollment balance increase followed by cashier payments reconciles balance ledger', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id, 'balance_centavos' => 0]);

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('registrar_token')->plainTextToken;

        $cashierUser = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $cashierToken = $cashierUser->createToken('cashier_token')->plainTextToken;

        $course = Course::factory()->create(['units' => 3]);

        // 1. Enrollment: 3 units * 50,000 centavos = 150,000 centavos added to balance
        $this->withHeaders([
            'Authorization' => 'Bearer '.$registrarToken,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ])->assertStatus(201);

        expect($student->fresh()->balance_centavos)->toBe(150000);

        // 2. Cashier partial payment: 100,000 centavos
        $this->withHeaders([
            'Authorization' => 'Bearer '.$cashierToken,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 100000,
            'payment_type' => 'tuition',
        ])->assertStatus(201);

        expect($student->fresh()->balance_centavos)->toBe(50000);

        // 3. Cashier final payment: 50,000 centavos
        $this->withHeaders([
            'Authorization' => 'Bearer '.$cashierToken,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 50000,
            'payment_type' => 'tuition',
        ])->assertStatus(201);

        expect($student->fresh()->balance_centavos)->toBe(0);
    });

    it('cross-feature: cashier payment generates valid or_number that resolves to viewable receipt', function () {
        $studentUser = User::factory()->create(['name' => 'Clarissa Dalisay', 'role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id, 'balance_centavos' => 100000]);
        $studentToken = $studentUser->createToken('student_token')->plainTextToken;

        $cashierUser = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $cashierToken = $cashierUser->createToken('cashier_token')->plainTextToken;

        // Cashier creates payment
        $paymentResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$cashierToken,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 75000,
            'payment_type' => 'tuition',
        ]);

        $paymentResponse->assertStatus(201);
        $orNumber = $paymentResponse->json('or_number');
        $receiptUrl = $paymentResponse->json('receipt_url');

        expect($orNumber)->not->toBeEmpty();
        expect($receiptUrl)->not->toBeEmpty();

        // Student views receipt via Bearer token
        $receiptResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$studentToken,
        ])->get("/receipts/{$orNumber}");

        $receiptResponse->assertStatus(200);
        $content = $receiptResponse->getContent();
        expect($content)->toContain($orNumber);
        expect($content)->toContain('Clarissa Dalisay');

        // Cashier also views receipt via Bearer token
        $cashierReceiptResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$cashierToken,
        ])->get("/receipts/{$orNumber}");

        $cashierReceiptResponse->assertStatus(200);
    });

    it('cross-feature: multiple semester enrollments maintain strict term isolation across subjects and grades', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $studentToken = $studentUser->createToken('student_token')->plainTextToken;

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('registrar_token')->plainTextToken;

        $courseSem1A = Course::factory()->create(['code' => 'CS-SEM1-A']);
        $courseSem1B = Course::factory()->create(['code' => 'CS-SEM1-B']);
        $courseSem2A = Course::factory()->create(['code' => 'CS-SEM2-A']);
        $courseSem2B = Course::factory()->create(['code' => 'CS-SEM2-B']);

        // Enroll in Sem 1
        $resSem1A = $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', ['student_id' => $student->id, 'course_id' => $courseSem1A->id, 'school_year' => '2026-2027', 'semester' => 1]);
        $resSem1B = $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', ['student_id' => $student->id, 'course_id' => $courseSem1B->id, 'school_year' => '2026-2027', 'semester' => 1]);

        // Enroll in Sem 2
        $resSem2A = $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', ['student_id' => $student->id, 'course_id' => $courseSem2A->id, 'school_year' => '2026-2027', 'semester' => 2]);
        $resSem2B = $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', ['student_id' => $student->id, 'course_id' => $courseSem2B->id, 'school_year' => '2026-2027', 'semester' => 2]);

        // Grade CS-SEM1-A (Sem 1) and CS-SEM2-A (Sem 2)
        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->patchJson("/api/registrar/enrollments/{$resSem1A->json('id')}/grade", ['grade' => 1.25]);

        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->patchJson("/api/registrar/enrollments/{$resSem2A->json('id')}/grade", ['grade' => 2.25]);

        // Query Sem 1 subjects -> must contain Sem 1 courses only
        $sem1Subjects = $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/subjects?school_year=2026-2027&semester=1');
        $sem1Subjects->assertStatus(200)->assertJsonCount(2)
            ->assertJson([['course_code' => 'CS-SEM1-A'], ['course_code' => 'CS-SEM1-B']])
            ->assertJsonMissing(['course_code' => 'CS-SEM2-A']);

        // Query Sem 1 grades -> must contain only CS-SEM1-A
        $sem1Grades = $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/grades?school_year=2026-2027&semester=1');
        $sem1Grades->assertStatus(200)->assertJsonCount(1)
            ->assertJson([['course_code' => 'CS-SEM1-A']])
            ->assertJsonMissing(['course_code' => 'CS-SEM2-A']);

        // Query Sem 2 subjects -> must contain Sem 2 courses only
        $sem2Subjects = $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/subjects?school_year=2026-2027&semester=2');
        $sem2Subjects->assertStatus(200)->assertJsonCount(2)
            ->assertJson([['course_code' => 'CS-SEM2-A'], ['course_code' => 'CS-SEM2-B']])
            ->assertJsonMissing(['course_code' => 'CS-SEM1-A']);

        // Query Sem 2 grades -> must contain only CS-SEM2-A
        $sem2Grades = $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/grades?school_year=2026-2027&semester=2');
        $sem2Grades->assertStatus(200)->assertJsonCount(1)
            ->assertJson([['course_code' => 'CS-SEM2-A']])
            ->assertJsonMissing(['course_code' => 'CS-SEM1-A']);
    });

    it('cross-feature: duplicate enrollment attempt on completed course returns 409 and protects state', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id, 'balance_centavos' => 0]);

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('registrar_token')->plainTextToken;

        $course = Course::factory()->create(['units' => 3]);

        // 1. Initial enrollment
        $enrollResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$registrarToken,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ])->assertStatus(201);

        $enrollmentId = $enrollResponse->json('id');
        $initialBalance = $student->fresh()->balance_centavos;

        // 2. Grade course as completed
        $this->withHeaders([
            'Authorization' => 'Bearer '.$registrarToken,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollmentId}/grade", [
            'grade' => 1.25,
        ])->assertStatus(200);

        // 3. Attempt duplicate enrollment
        $dupResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$registrarToken,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $dupResponse->assertStatus(409)
            ->assertJson(['error' => 'ALREADY_ENROLLED']);

        // Verify balance and enrollment state were preserved
        expect($student->fresh()->balance_centavos)->toBe($initialBalance);

        $enrollment = Enrollment::find($enrollmentId);
        expect($enrollment->status)->toBe('completed');
        expect((float) $enrollment->grade)->toBe(1.25);
    });
});
