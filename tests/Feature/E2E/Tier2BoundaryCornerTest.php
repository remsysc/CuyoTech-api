<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Tier 2: Boundary & Corner Cases E2E Tests
|--------------------------------------------------------------------------
|
| Min/max limits, edge conditions, invalid input combinations, missing query
| params (400), non-existent resources (404), duplicates (409), invalid
| grades (422), negative/zero amounts (400), and defensive failure handling.
|
*/

// =========================================================================
// Feature 1: Student Profile Boundaries
// =========================================================================

describe('Feature 1: Student Profile Boundaries', function () {
    it('returns 200 with all fields for a student with zero balance and default state', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'balance_centavos' => 0,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(200)
            ->assertJson([
                'student_number' => $student->student_number,
                'name' => $user->name,
                'program' => $student->program,
                'year_level' => $student->year_level,
                'status' => $student->status,
            ]);
    });

    it('handles irregular/extended year levels (e.g. year_level 5) correctly', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'year_level' => 5,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(200)
            ->assertJson(['year_level' => 5]);
    });

    it('allows a student with status on_leave to retrieve their profile', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'status' => 'on_leave',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(200)
            ->assertJson(['status' => 'on_leave']);
    });

    it('allows a graduated student to retrieve their profile', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'status' => 'graduated',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(200)
            ->assertJson(['status' => 'graduated']);
    });

    it('does not expose sensitive attributes in the student profile response', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(200);
        $data = $response->json();

        expect($data)->not->toHaveKey('password')
            ->and($data)->not->toHaveKey('remember_token')
            ->and($data)->not->toHaveKey('email_verified_at')
            ->and($data)->not->toHaveKey('id')
            ->and($data)->not->toHaveKey('user_id');
    });

    it('preserves alphanumeric student numbers with special characters and dashes', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $user->id,
            'student_number' => 'CTU-2026-ENG-9988',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(200)
            ->assertJson(['student_number' => 'CTU-2026-ENG-9988']);
    });
});

// =========================================================================
// Feature 2: Student Subjects Boundaries
// =========================================================================

describe('Feature 2: Student Subjects Boundaries', function () {
    it('returns 400 MISSING_TERM when school_year is omitted', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?semester=1');

        $response->assertStatus(400)
            ->assertJson(['error' => 'MISSING_TERM']);
    });

    it('returns 400 MISSING_TERM when semester is omitted', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027');

        $response->assertStatus(400)
            ->assertJson(['error' => 'MISSING_TERM']);
    });

    it('returns 400 MISSING_TERM when both parameters are omitted', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects');

        $response->assertStatus(400)
            ->assertJson(['error' => 'MISSING_TERM']);
    });

    it('returns 400 MISSING_TERM when query parameters are empty strings', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=&semester=');

        $response->assertStatus(400)
            ->assertJson(['error' => 'MISSING_TERM']);
    });

    it('accepts string formatted semester parameter and matches integer column', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);
        $course = Course::factory()->create(['code' => 'CS201']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 2,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=2');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([['course_code' => 'CS201']]);
    });

    it('returns empty array when querying a future term with zero subjects', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2030-2031&semester=1');

        $response->assertStatus(200)
            ->assertExactJson([]);
    });
});

// =========================================================================
// Feature 3: Student Grades Boundaries
// =========================================================================

describe('Feature 3: Student Grades Boundaries', function () {
    it('returns 400 MISSING_TERM when school_year is omitted in grades query', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?semester=1');

        $response->assertStatus(400)
            ->assertJson(['error' => 'MISSING_TERM']);
    });

    it('returns 400 MISSING_TERM when semester is omitted in grades query', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027');

        $response->assertStatus(400)
            ->assertJson(['error' => 'MISSING_TERM']);
    });

    it('returns 400 MISSING_TERM when both parameters are empty strings in grades query', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=&semester=');

        $response->assertStatus(400)
            ->assertJson(['error' => 'MISSING_TERM']);
    });

    it('returns boundary grade 1.00 and failing boundary grade 5.00 accurately', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $courseHigh = Course::factory()->create(['code' => 'CS-HIGH']);
        $courseLow = Course::factory()->create(['code' => 'CS-FAIL']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $courseHigh->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => 1.00,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $courseLow->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => 5.00,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(2);

        $grades = collect($response->json())->keyBy('course_code');
        expect((float) $grades['CS-HIGH']['grade'])->toBe(1.0);
        expect((float) $grades['CS-FAIL']['grade'])->toBe(5.0);
    });

    it('returns only completed courses when student has multiple subjects in various states', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $courseCompleted = Course::factory()->create(['code' => 'COMPLETED-1']);
        $courseEnrolled = Course::factory()->create(['code' => 'ENROLLED-2']);
        $courseDropped = Course::factory()->create(['code' => 'DROPPED-3']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $courseCompleted->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => 1.75,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $courseEnrolled->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'enrolled',
            'grade' => null,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $courseDropped->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'dropped',
            'grade' => null,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([['course_code' => 'COMPLETED-1']]);
    });
});

// =========================================================================
// Feature 4: Registrar Course Enrollment Boundaries
// =========================================================================

describe('Feature 4: Registrar Course Enrollment Boundaries', function () {
    it('returns 404 STUDENT_NOT_FOUND when student_id does not exist', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $course = Course::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => 9999999,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'STUDENT_NOT_FOUND']);
    });

    it('returns 404 COURSE_NOT_FOUND when course_id does not exist', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => 9999999,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'COURSE_NOT_FOUND']);
    });

    it('returns 409 ALREADY_ENROLLED on duplicate enrollment attempt', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 0]);
        $course = Course::factory()->create(['units' => 3]);

        // First enrollment
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ])->assertStatus(201);

        // Duplicate enrollment attempt
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $response->assertStatus(409)
            ->assertJson(['error' => 'ALREADY_ENROLLED']);
    });

    it('does NOT charge student balance again when duplicate enrollment returns 409', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 0]);
        $course = Course::factory()->create(['units' => 3]);

        // First enrollment
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ])->assertStatus(201);

        $balanceAfterFirst = $student->fresh()->balance_centavos;

        // Duplicate attempt
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ])->assertStatus(409);

        expect($student->fresh()->balance_centavos)->toBe($balanceAfterFirst);
    });

    it('correctly calculates tuition charge for high-unit courses without overflow', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 0]);
        $course = Course::factory()->create(['units' => 12]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $rate = config('fees.rate_per_unit_centavos', 50000);
        $expectedCharge = 12 * $rate;

        $response->assertStatus(201)
            ->assertJson(['charge_applied_centavos' => $expectedCharge]);

        expect($student->fresh()->balance_centavos)->toBe($expectedCharge);
    });

    it('rejects incomplete enrollment payload with validation error', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => 1,
            // missing course_id, school_year, semester
        ]);

        $response->assertStatus(422);
    });
});

// =========================================================================
// Feature 5: Registrar Grade Encoding Boundaries
// =========================================================================

describe('Feature 5: Registrar Grade Encoding Boundaries', function () {
    it('returns 404 ENROLLMENT_NOT_FOUND when enrollment ID does not exist', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson('/api/registrar/enrollments/9999999/grade', [
            'grade' => 1.50,
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'ENROLLMENT_NOT_FOUND']);
    });

    it('returns 422 INVALID_GRADE_RANGE when grade is lower than 1.00', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $enrollment = Enrollment::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment->id}/grade", [
            'grade' => 0.75,
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'INVALID_GRADE_RANGE']);
    });

    it('returns 422 INVALID_GRADE_RANGE when grade is higher than 5.00', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $enrollment = Enrollment::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment->id}/grade", [
            'grade' => 5.25,
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'INVALID_GRADE_RANGE']);
    });

    it('returns 422 INVALID_GRADE_RANGE when grade is not in 0.25 step increments', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $enrollment = Enrollment::factory()->create();

        // 1.10 is within 1.00-5.00 but not an increment of 0.25
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment->id}/grade", [
            'grade' => 1.10,
        ]);

        $response1->assertStatus(422)
            ->assertJson(['error' => 'INVALID_GRADE_RANGE']);

        // 2.30 is also invalid step
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment->id}/grade", [
            'grade' => 2.30,
        ]);

        $response2->assertStatus(422)
            ->assertJson(['error' => 'INVALID_GRADE_RANGE']);
    });

    it('accepts exact lower boundary grade 1.00 and upper boundary grade 5.00', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $enrollment1 = Enrollment::factory()->create();
        $enrollment2 = Enrollment::factory()->create();

        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment1->id}/grade", [
            'grade' => 1.00,
        ]);
        $res1->assertStatus(200);

        $res2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment2->id}/grade", [
            'grade' => 5.00,
        ]);
        $res2->assertStatus(200);
    });

    it('returns 422 INVALID_GRADE_RANGE for negative grade values', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $enrollment = Enrollment::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment->id}/grade", [
            'grade' => -1.00,
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'INVALID_GRADE_RANGE']);
    });
});

// =========================================================================
// Feature 6: Cashier Payment Boundaries
// =========================================================================

describe('Feature 6: Cashier Payment Boundaries', function () {
    it('returns 400 INVALID_AMOUNT when amount_centavos is zero', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 0,
            'payment_type' => 'tuition',
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'INVALID_AMOUNT']);
    });

    it('returns 400 INVALID_AMOUNT when amount_centavos is negative', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => -50000,
            'payment_type' => 'tuition',
        ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'INVALID_AMOUNT']);
    });

    it('returns 404 STUDENT_NOT_FOUND when student does not exist', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => 9999999,
            'amount_centavos' => 50000,
            'payment_type' => 'tuition',
        ]);

        $response->assertStatus(404)
            ->assertJson(['error' => 'STUDENT_NOT_FOUND']);
    });

    it('accepts minimum positive amount of 1 centavo and decrements balance accordingly', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 100]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 1,
            'payment_type' => 'tuition',
        ]);

        $response->assertStatus(201);
        expect($student->fresh()->balance_centavos)->toBe(99);
    });

    it('allows payment greater than current balance resulting in credit balance', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 50000]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 60000,
            'payment_type' => 'tuition',
        ]);

        $response->assertStatus(201);
        expect($student->fresh()->balance_centavos)->toBe(-10000);
    });

    it('rejects invalid payment_type enum with validation error', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 50000,
            'payment_type' => 'unsupported_type',
        ]);

        $response->assertStatus(422);
    });
});

// =========================================================================
// Feature 7: Stable Receipt View Boundaries
// =========================================================================

describe('Feature 7: Stable Receipt View Boundaries', function () {
    it('returns 404 for non-existent OR number', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/receipts/OR-NONEXISTENT-999');

        $response->assertStatus(404);
    });

    it('returns 404 for empty or whitespace OR number string', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/receipts/%20');

        $response->assertStatus(404);
    });

    it('returns 404 safely when OR number contains special meta-characters', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get("/receipts/' OR '1'='1");

        $response->assertStatus(404);
    });

    it('rejects receipt access with invalid or expired bearer token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_fake_token_12345',
            'Accept' => 'application/json',
        ])->get('/receipts/OR-2026-0001');

        $response->assertStatus(401);
    });

    it('rejects receipt access with invalid query token (?token=invalid)', function () {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->get('/receipts/OR-2026-0001?token=invalid_fake_token');

        $response->assertStatus(401);
    });

    it('renders receipt correctly for students with UTF-8 special characters in name', function () {
        $studentUser = User::factory()->create([
            'name' => 'Ana Peña-Rodriguez',
            'role' => 'student',
            'is_active' => true,
        ]);
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $cashier = User::factory()->create(['role' => 'cashier']);

        Payment::create([
            'student_id' => $student->id,
            'cashier_id' => $cashier->id,
            'amount_centavos' => 200000,
            'or_number' => 'OR-UTF8-001',
            'payment_type' => 'tuition',
            'paid_at' => now(),
        ]);

        $token = $studentUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/receipts/OR-UTF8-001');

        $response->assertStatus(200);
        expect($response->getContent())->toContain('Ana Peña-Rodriguez');
    });
});
