<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Tier 1: Feature Coverage E2E Tests
|--------------------------------------------------------------------------
|
| Core happy path contracts, authenticated student/registrar/cashier flows,
| role authorization (403), unauthenticated rejection (401), and database
| persistence across all 7 Sprint 2 features.
|
*/

// =========================================================================
// Feature 1: Student Profile (GET /api/student/profile)
// =========================================================================

describe('Feature 1: Student Profile', function () {
    it('allows an authenticated student to retrieve their own profile with exact expected fields', function () {
        $user = User::factory()->create([
            'name' => 'Maria Santos',
            'role' => 'student',
            'is_active' => true,
        ]);

        $student = Student::factory()->create([
            'user_id' => $user->id,
            'student_number' => '2026-0001',
            'program' => 'BSCS',
            'year_level' => 2,
            'status' => 'active',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(200)
            ->assertExactJson([
                'student_number' => '2026-0001',
                'name' => 'Maria Santos',
                'program' => 'BSCS',
                'year_level' => 2,
                'status' => 'active',
            ]);
    });

    it('ensures profile values accurately reflect updated student records', function () {
        $user = User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'role' => 'student',
            'is_active' => true,
        ]);

        $student = Student::factory()->create([
            'user_id' => $user->id,
            'student_number' => '2026-0002',
            'program' => 'BSIT',
            'year_level' => 4,
            'status' => 'active',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(200)
            ->assertJson([
                'student_number' => '2026-0002',
                'name' => 'Juan Dela Cruz',
                'program' => 'BSIT',
                'year_level' => 4,
                'status' => 'active',
            ]);
    });

    it('rejects unauthenticated requests with 401 UNAUTHENTICATED', function () {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(401)
            ->assertJson(['error' => 'UNAUTHENTICATED']);
    });

    it('rejects registrar from accessing student profile with 403 UNAUTHORIZED_ROLE', function () {
        $registrar = User::factory()->create([
            'role' => 'registrar',
            'is_active' => true,
        ]);

        $token = $registrar->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(403)
            ->assertJson(['error' => 'UNAUTHORIZED_ROLE']);
    });

    it('rejects cashier from accessing student profile with 403 UNAUTHORIZED_ROLE', function () {
        $cashier = User::factory()->create([
            'role' => 'cashier',
            'is_active' => true,
        ]);

        $token = $cashier->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(403)
            ->assertJson(['error' => 'UNAUTHORIZED_ROLE']);
    });

    it('rejects admin from accessing student profile with 403 UNAUTHORIZED_ROLE', function () {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $token = $admin->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(403)
            ->assertJson(['error' => 'UNAUTHORIZED_ROLE']);
    });

    it('rejects deactivated student with 403 ACCOUNT_DEACTIVATED', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => false,
        ]);

        Student::factory()->create([
            'user_id' => $user->id,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(403)
            ->assertJson(['error' => 'ACCOUNT_DEACTIVATED']);
    });
});

// =========================================================================
// Feature 2: Student Subjects (GET /api/student/subjects)
// =========================================================================

describe('Feature 2: Student Subjects', function () {
    it('allows authenticated student to list enrolled subjects for a specific term', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $dept = Department::factory()->create();
        $course1 = Course::factory()->create(['department_id' => $dept->id, 'code' => 'CS101', 'title' => 'Intro to CS', 'units' => 3]);
        $course2 = Course::factory()->create(['department_id' => $dept->id, 'code' => 'CS102', 'title' => 'Data Structures', 'units' => 4]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course1->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'enrolled',
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course2->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'enrolled',
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJson([
                ['course_code' => 'CS101', 'title' => 'Intro to CS', 'units' => 3, 'status' => 'enrolled'],
                ['course_code' => 'CS102', 'title' => 'Data Structures', 'units' => 4, 'status' => 'enrolled'],
            ]);
    });

    it('returns an empty array when student has no subjects enrolled for the requested term', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertExactJson([]);
    });

    it('does not leak subjects from another student in the same term', function () {
        $userA = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $studentA = Student::factory()->create(['user_id' => $userA->id]);

        $userB = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $studentB = Student::factory()->create(['user_id' => $userB->id]);

        $courseA = Course::factory()->create(['code' => 'CS101']);
        $courseB = Course::factory()->create(['code' => 'MATH101']);

        Enrollment::factory()->create([
            'student_id' => $studentA->id,
            'course_id' => $courseA->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'enrolled',
        ]);

        Enrollment::factory()->create([
            'student_id' => $studentB->id,
            'course_id' => $courseB->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'enrolled',
        ]);

        $tokenA = $userA->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$tokenA,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([
                ['course_code' => 'CS101'],
            ])
            ->assertJsonMissing(['course_code' => 'MATH101']);
    });

    it('includes subjects with different enrollment statuses for the requested term', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $course1 = Course::factory()->create(['code' => 'ENG101']);
        $course2 = Course::factory()->create(['code' => 'HIST101']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course1->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'enrolled',
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course2->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => 1.75,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJson([
                ['course_code' => 'ENG101', 'status' => 'enrolled'],
                ['course_code' => 'HIST101', 'status' => 'completed'],
            ]);
    });

    it('filters out subjects enrolled in a different academic term', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $courseSem1 = Course::factory()->create(['code' => 'SEM1-COURSE']);
        $courseSem2 = Course::factory()->create(['code' => 'SEM2-COURSE']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $courseSem1->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $courseSem2->id,
            'school_year' => '2026-2027',
            'semester' => 2,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([['course_code' => 'SEM1-COURSE']])
            ->assertJsonMissing(['course_code' => 'SEM2-COURSE']);
    });

    it('rejects unauthenticated requests to subjects endpoint with 401 UNAUTHENTICATED', function () {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(401)
            ->assertJson(['error' => 'UNAUTHENTICATED']);
    });

    it('rejects non-student roles from querying subjects with 403 UNAUTHORIZED_ROLE', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(403)
            ->assertJson(['error' => 'UNAUTHORIZED_ROLE']);
    });
});

// =========================================================================
// Feature 3: Student Grades (GET /api/student/grades)
// =========================================================================

describe('Feature 3: Student Grades', function () {
    it('allows authenticated student to retrieve completed grades for a term', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $course1 = Course::factory()->create(['code' => 'CS101', 'title' => 'Intro to CS']);
        $course2 = Course::factory()->create(['code' => 'CS102', 'title' => 'Data Structures']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course1->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => 1.25,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course2->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => 2.00,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJson([
                ['course_code' => 'CS101', 'title' => 'Intro to CS'],
                ['course_code' => 'CS102', 'title' => 'Data Structures'],
            ]);
    });

    it('excludes in-progress enrollments (status=enrolled) from the grades response', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $courseCompleted = Course::factory()->create(['code' => 'COMPLETED-101']);
        $courseEnrolled = Course::factory()->create(['code' => 'ENROLLED-102']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $courseCompleted->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => 1.50,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $courseEnrolled->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'enrolled',
            'grade' => null,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([['course_code' => 'COMPLETED-101']])
            ->assertJsonMissing(['course_code' => 'ENROLLED-102']);
    });

    it('excludes dropped enrollments from the grades response', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $courseDropped = Course::factory()->create(['code' => 'DROPPED-103']);

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
            ->assertExactJson([]);
    });

    it('returns empty array if student has subjects but none are completed', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);
        $course = Course::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'enrolled',
            'grade' => null,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertExactJson([]);
    });

    it('does not leak grades of another student in the same term', function () {
        $userA = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $studentA = Student::factory()->create(['user_id' => $userA->id]);

        $userB = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $studentB = Student::factory()->create(['user_id' => $userB->id]);

        $courseA = Course::factory()->create(['code' => 'CS101']);
        $courseB = Course::factory()->create(['code' => 'MATH101']);

        Enrollment::factory()->create([
            'student_id' => $studentA->id,
            'course_id' => $courseA->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => 1.00,
        ]);

        Enrollment::factory()->create([
            'student_id' => $studentB->id,
            'course_id' => $courseB->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => 2.00,
        ]);

        $tokenA = $userA->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$tokenA,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([['course_code' => 'CS101']])
            ->assertJsonMissing(['course_code' => 'MATH101']);
    });

    it('rejects unauthenticated requests to grades endpoint with 401 UNAUTHENTICATED', function () {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(401)
            ->assertJson(['error' => 'UNAUTHENTICATED']);
    });

    it('rejects non-student roles from querying grades with 403 UNAUTHORIZED_ROLE', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(403)
            ->assertJson(['error' => 'UNAUTHORIZED_ROLE']);
    });
});

// =========================================================================
// Feature 4: Registrar Course Enrollment (POST /api/registrar/enrollments)
// =========================================================================

describe('Feature 4: Registrar Course Enrollment', function () {
    it('allows registrar to enroll a student in a course and returns 201 with charge', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 0]);
        $course = Course::factory()->create(['units' => 3]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $expectedCharge = 3 * config('fees.rate_per_unit_centavos', 50000);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'status', 'charge_applied_centavos'])
            ->assertJson([
                'status' => 'enrolled',
                'charge_applied_centavos' => $expectedCharge,
            ]);
    });

    it('atomically increases the student balance by units multiplied by rate per unit', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 100000]);
        $course = Course::factory()->create(['units' => 4]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ])->assertStatus(201);

        $rate = config('fees.rate_per_unit_centavos', 50000);
        $expectedBalance = 100000 + (4 * $rate);

        expect($student->fresh()->balance_centavos)->toBe($expectedBalance);
    });

    it('persists enrollment record in database with correct initial status', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create();
        $course = Course::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 2,
        ]);

        $response->assertStatus(201);
        $enrollmentId = $response->json('id');

        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollmentId,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 2,
            'status' => 'enrolled',
            'grade' => null,
        ]);
    });

    it('handles custom unit counts correctly', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 0]);
        $course = Course::factory()->create(['units' => 5]);

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
        $response->assertStatus(201)
            ->assertJson(['charge_applied_centavos' => 5 * $rate]);

        expect($student->fresh()->balance_centavos)->toBe(5 * $rate);
    });

    it('rejects unauthenticated enrollment requests with 401 UNAUTHENTICATED', function () {
        $student = Student::factory()->create();
        $course = Course::factory()->create();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'UNAUTHENTICATED']);
    });

    it('rejects student role from creating enrollments with 403 UNAUTHORIZED_ROLE', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $token = $user->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create();
        $course = Course::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'UNAUTHORIZED_ROLE']);
    });

    it('rejects cashier role from creating enrollments with 403 UNAUTHORIZED_ROLE', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create();
        $course = Course::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/registrar/enrollments', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'UNAUTHORIZED_ROLE']);
    });
});

// =========================================================================
// Feature 5: Registrar Grade Encoding (PATCH /api/registrar/enrollments/{id}/grade)
// =========================================================================

describe('Feature 5: Registrar Grade Encoding', function () {
    it('allows registrar to record grade 1.00 and marks enrollment completed', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $enrollment = Enrollment::factory()->create([
            'status' => 'enrolled',
            'grade' => null,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment->id}/grade", [
            'grade' => 1.00,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $enrollment->id,
                'status' => 'completed',
            ]);

        expect((float) $response->json('grade'))->toBe(1.0);
        expect($enrollment->fresh()->status)->toBe('completed');
    });

    it('allows registrar to record failing grade 5.00 and marks enrollment completed', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $enrollment = Enrollment::factory()->create([
            'status' => 'enrolled',
            'grade' => null,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment->id}/grade", [
            'grade' => 5.00,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $enrollment->id,
                'status' => 'completed',
            ]);

        expect((float) $response->json('grade'))->toBe(5.0);
        expect($enrollment->fresh()->status)->toBe('completed');
    });

    it('allows registrar to overwrite a previously recorded grade (last-write-wins)', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $enrollment = Enrollment::factory()->create([
            'status' => 'completed',
            'grade' => 2.50,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment->id}/grade", [
            'grade' => 1.75,
        ]);

        $response->assertStatus(200);
        expect((float) $response->json('grade'))->toBe(1.75);
        expect((float) $enrollment->fresh()->grade)->toBe(1.75);
    });

    it('accepts intermediate step grades like 2.25 and 3.00', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('test_token')->plainTextToken;

        $enrollment = Enrollment::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment->id}/grade", [
            'grade' => 2.25,
        ]);

        $response->assertStatus(200);
        expect((float) $response->json('grade'))->toBe(2.25);
    });

    it('rejects unauthenticated requests to grade encoding with 401 UNAUTHENTICATED', function () {
        $enrollment = Enrollment::factory()->create();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment->id}/grade", [
            'grade' => 1.50,
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'UNAUTHENTICATED']);
    });

    it('rejects student role from encoding grades with 403 UNAUTHORIZED_ROLE', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $token = $user->createToken('test_token')->plainTextToken;
        $enrollment = Enrollment::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment->id}/grade", [
            'grade' => 1.50,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'UNAUTHORIZED_ROLE']);
    });

    it('rejects cashier role from encoding grades with 403 UNAUTHORIZED_ROLE', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;
        $enrollment = Enrollment::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/registrar/enrollments/{$enrollment->id}/grade", [
            'grade' => 1.50,
        ]);

        $response->assertStatus(403)
            ->assertJson(['error' => 'UNAUTHORIZED_ROLE']);
    });
});

// =========================================================================
// Feature 6: Cashier Payment Processing (POST /api/cashier/payments)
// =========================================================================

describe('Feature 6: Cashier Payment Processing', function () {
    it('allows cashier to record tuition payment and returns 201 with or_number and receipt_url', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 200000]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 100000,
            'payment_type' => 'tuition',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['or_number', 'receipt_url']);

        expect($response->json('or_number'))->not->toBeEmpty();
        expect($response->json('receipt_url'))->not->toBeEmpty();
    });

    it('atomically decrements student balance by the payment amount', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 300000]);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 125000,
            'payment_type' => 'tuition',
        ])->assertStatus(201);

        expect($student->fresh()->balance_centavos)->toBe(175000);
    });

    it('persists payment record in database with authenticated cashier id and paid_at', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 100000]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 50000,
            'payment_type' => 'tuition',
        ]);

        $response->assertStatus(201);
        $orNumber = $response->json('or_number');

        $this->assertDatabaseHas('payments', [
            'student_id' => $student->id,
            'cashier_id' => $cashier->id,
            'amount_centavos' => 50000,
            'or_number' => $orNumber,
            'payment_type' => 'tuition',
        ]);

        $payment = Payment::where('or_number', $orNumber)->first();
        expect($payment)->not->toBeNull();
        expect($payment->paid_at)->not->toBeNull();
    });

    it('allows cashier to record misc_fee payment type', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 50000]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 25000,
            'payment_type' => 'misc_fee',
        ]);

        $response->assertStatus(201);
        expect($student->fresh()->balance_centavos)->toBe(25000);
    });

    it('allows cashier to record document_fee payment type', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create(['balance_centavos' => 10000]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 10000,
            'payment_type' => 'document_fee',
        ]);

        $response->assertStatus(201);
        expect($student->fresh()->balance_centavos)->toBe(0);
    });

    it('rejects unauthenticated payment requests with 401 UNAUTHENTICATED', function () {
        $student = Student::factory()->create();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 50000,
            'payment_type' => 'tuition',
        ]);

        $response->assertStatus(401)
            ->assertJson(['error' => 'UNAUTHENTICATED']);
    });

    it('rejects student and registrar roles from recording payments with 403 UNAUTHORIZED_ROLE', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $studentToken = $studentUser->createToken('test_token')->plainTextToken;

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create();

        $this->withHeaders([
            'Authorization' => 'Bearer '.$studentToken,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 50000,
            'payment_type' => 'tuition',
        ])->assertStatus(403)->assertJson(['error' => 'UNAUTHORIZED_ROLE']);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$registrarToken,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 50000,
            'payment_type' => 'tuition',
        ])->assertStatus(403)->assertJson(['error' => 'UNAUTHORIZED_ROLE']);
    });
});

// =========================================================================
// Feature 7: Stable Receipt View (GET /receipts/{or_number})
// =========================================================================

describe('Feature 7: Stable Receipt View', function () {
    it('allows authenticated student to view their own payment receipt via Bearer token', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'name' => 'Maria Santos', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id]);

        $cashier = User::factory()->create(['role' => 'cashier']);
        $payment = Payment::create([
            'student_id' => $student->id,
            'cashier_id' => $cashier->id,
            'amount_centavos' => 150000,
            'or_number' => 'OR-2026-0001',
            'payment_type' => 'tuition',
            'paid_at' => now(),
        ]);

        $token = $studentUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/receipts/OR-2026-0001');

        $response->assertStatus(200);
        $content = $response->getContent();
        expect($content)->toContain('OR-2026-0001');
    });

    it('allows authenticated cashier to view any payment receipt', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create();
        Payment::create([
            'student_id' => $student->id,
            'cashier_id' => $cashier->id,
            'amount_centavos' => 100000,
            'or_number' => 'OR-2026-0002',
            'payment_type' => 'tuition',
            'paid_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/receipts/OR-2026-0002');

        $response->assertStatus(200);
    });

    it('allows receipt viewing via token query parameter (?token=...)', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $cashier = User::factory()->create(['role' => 'cashier']);

        Payment::create([
            'student_id' => $student->id,
            'cashier_id' => $cashier->id,
            'amount_centavos' => 50000,
            'or_number' => 'OR-2026-0003',
            'payment_type' => 'tuition',
            'paid_at' => now(),
        ]);

        $token = $studentUser->createToken('test_token')->plainTextToken;

        $response = $this->get('/receipts/OR-2026-0003?token='.$token);

        $response->assertStatus(200);
        expect($response->getContent())->toContain('OR-2026-0003');
    });

    it('displays receipt information including OR number, student name, and amount', function () {
        $studentUser = User::factory()->create(['name' => 'Eduardo Manalo', 'role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $cashier = User::factory()->create(['role' => 'cashier']);

        Payment::create([
            'student_id' => $student->id,
            'cashier_id' => $cashier->id,
            'amount_centavos' => 250000,
            'or_number' => 'OR-2026-0004',
            'payment_type' => 'tuition',
            'paid_at' => now(),
        ]);

        $token = $studentUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/receipts/OR-2026-0004');

        $response->assertStatus(200);
        $content = $response->getContent();
        expect($content)->toContain('OR-2026-0004');
        expect($content)->toContain('Eduardo Manalo');
    });

    it('returns 404 for a non-existent or_number', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/receipts/OR-DOES-NOT-EXIST');

        $response->assertStatus(404);
    });

    it('rejects unauthenticated requests to receipt endpoint with 401 UNAUTHENTICATED', function () {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->get('/receipts/OR-2026-0001');

        $response->assertStatus(401);
    });

    it('allows admin role to view payment receipt with valid token', function () {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $admin->createToken('test_token')->plainTextToken;

        $student = Student::factory()->create();
        $cashier = User::factory()->create(['role' => 'cashier']);

        Payment::create([
            'student_id' => $student->id,
            'cashier_id' => $cashier->id,
            'amount_centavos' => 75000,
            'or_number' => 'OR-2026-0005',
            'payment_type' => 'tuition',
            'paid_at' => now(),
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->get('/receipts/OR-2026-0005');

        $response->assertStatus(200);
    });
});
