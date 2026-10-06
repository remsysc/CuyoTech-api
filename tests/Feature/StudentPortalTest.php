<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| GET /api/student/profile
|--------------------------------------------------------------------------
*/

describe('Student Profile Endpoint', function () {
    it('allows an authenticated student to retrieve their own profile with exact expected fields', function () {
        $user = User::factory()->create([
            'name' => 'Maria Clara',
            'role' => 'student',
            'is_active' => true,
        ]);

        $student = Student::factory()->create([
            'user_id' => $user->id,
            'student_number' => '2026-00001',
            'program' => 'BSCS',
            'year_level' => 2,
            'status' => 'active',
            'balance_centavos' => 500000,
        ]);

        $token = $user->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(200)
            ->assertExactJson([
                'student_number' => '2026-00001',
                'name' => 'Maria Clara',
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
            'student_number' => '2026-00002',
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
                'student_number' => '2026-00002',
                'name' => 'Juan Dela Cruz',
                'program' => 'BSIT',
                'year_level' => 4,
                'status' => 'active',
            ]);
    });

    it('ensures a student cannot retrieve another student profile', function () {
        $userA = User::factory()->create(['name' => 'Student A', 'role' => 'student']);
        $studentA = Student::factory()->create(['user_id' => $userA->id, 'student_number' => 'SN-00001']);

        $userB = User::factory()->create(['name' => 'Student B', 'role' => 'student']);
        $studentB = Student::factory()->create(['user_id' => $userB->id, 'student_number' => 'SN-00002']);

        $tokenA = $userA->createToken('token_a')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$tokenA,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(200)
            ->assertJson([
                'student_number' => 'SN-00001',
                'name' => 'Student A',
            ])
            ->assertJsonMissing([
                'student_number' => 'SN-00002',
                'name' => 'Student B',
            ]);
    });

    it('ignores spoofed query parameter attempts to access another student data', function () {
        $userA = User::factory()->create(['name' => 'Alice', 'role' => 'student']);
        $studentA = Student::factory()->create(['user_id' => $userA->id, 'student_number' => 'SN-ALICE']);

        $userB = User::factory()->create(['name' => 'Bob', 'role' => 'student']);
        $studentB = Student::factory()->create(['user_id' => $userB->id, 'student_number' => 'SN-BOB']);

        $tokenA = $userA->createToken('auth')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$tokenA])
            ->getJson("/api/student/profile?student_id={$studentB->id}&student_number=SN-BOB")
            ->assertStatus(200)
            ->assertJsonFragment(['student_number' => 'SN-ALICE'])
            ->assertJsonMissing(['student_number' => 'SN-BOB']);
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
            ->and($data)->not->toHaveKey('user_id')
            ->and($data)->not->toHaveKey('balance_centavos');
    });

    it('rejects non-student roles with 403 UNAUTHORIZED_ROLE when requesting profile', function (string $role) {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        $token = $user->createToken('role_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);
    })->with(['registrar', 'cashier', 'department_staff', 'admin']);

    it('returns 401 UNAUTHENTICATED when requesting profile without token', function () {
        $response = $this->getJson('/api/student/profile');

        $response->assertStatus(401)
            ->assertExactJson([
                'error' => 'UNAUTHENTICATED',
            ]);
    });

    it('returns 401 UNAUTHENTICATED when requesting profile without Accept application/json header', function () {
        $response = $this->get('/api/student/profile');

        $response->assertStatus(401);
        expect($response->json())->toBe(['error' => 'UNAUTHENTICATED']);
    });

    it('returns 401 UNAUTHENTICATED when requesting profile with invalid token', function () {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token_value',
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(401)
            ->assertExactJson([
                'error' => 'UNAUTHENTICATED',
            ]);
    });

    it('returns 401 UNAUTHENTICATED when token has been revoked after logout', function () {
        $user = User::factory()->create(['role' => 'student']);
        $token = $user->createToken('auth')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->postJson('/api/logout')
            ->assertStatus(204);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/student/profile')
            ->assertStatus(401)
            ->assertExactJson(['error' => 'UNAUTHENTICATED']);
    });

    it('returns 403 ACCOUNT_DEACTIVATED when requesting profile with deactivated student account', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => false,
        ]);

        Student::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'ACCOUNT_DEACTIVATED',
            ]);
    });

    it('enforces live deactivation immediately on the next request holding valid token', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        Student::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('auth')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/student/profile')
            ->assertStatus(200);

        $user->update(['is_active' => false]);

        $this->withHeaders(['Authorization' => 'Bearer '.$token])
            ->getJson('/api/student/profile')
            ->assertStatus(403)
            ->assertExactJson(['error' => 'ACCOUNT_DEACTIVATED']);
    });

    it('handles user with student role but missing student record without crashing', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);

        $token = $user->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        expect($response->status())->toBeIn([403, 404])
            ->and($response->status())->not->toBe(500);

        expect($response->json())->toHaveKey('error');
    });
});

/*
|--------------------------------------------------------------------------
| GET /api/student/subjects
|--------------------------------------------------------------------------
*/

describe('Student Subjects Endpoint', function () {
    it('returns authenticated student courses for specified school_year and semester', function () {
        $user = User::factory()->create(['role' => 'student']);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $course1 = Course::factory()->create(['code' => 'CS101', 'title' => 'Introduction to Programming', 'units' => 3]);
        $course2 = Course::factory()->create(['code' => 'MATH101', 'title' => 'Calculus I', 'units' => 4]);

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
            'grade' => 1.50,
        ]);

        $token = $user->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJson([
                [
                    'course_code' => 'CS101',
                    'title' => 'Introduction to Programming',
                    'units' => 3,
                    'status' => 'enrolled',
                ],
                [
                    'course_code' => 'MATH101',
                    'title' => 'Calculus I',
                    'units' => 4,
                    'status' => 'completed',
                ],
            ]);
    });

    it('returns empty array when student has no subjects enrolled for the term', function () {
        $user = User::factory()->create(['role' => 'student']);
        Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=2');

        $response->assertStatus(200)
            ->assertExactJson([]);
    });

    it('filters subjects strictly by specified school_year and semester', function () {
        $user = User::factory()->create(['role' => 'student']);
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

        $token = $user->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['course_code' => 'SEM1-COURSE'])
            ->assertJsonMissing(['course_code' => 'SEM2-COURSE']);
    });

    it('ensures student cannot see subjects enrolled by other students', function () {
        $userA = User::factory()->create(['role' => 'student']);
        $studentA = Student::factory()->create(['user_id' => $userA->id]);

        $userB = User::factory()->create(['role' => 'student']);
        $studentB = Student::factory()->create(['user_id' => $userB->id]);

        $courseA = Course::factory()->create(['code' => 'STUDENT-A-COURSE']);
        $courseB = Course::factory()->create(['code' => 'STUDENT-B-COURSE']);

        Enrollment::factory()->create([
            'student_id' => $studentA->id,
            'course_id' => $courseA->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        Enrollment::factory()->create([
            'student_id' => $studentB->id,
            'course_id' => $courseB->id,
            'school_year' => '2026-2027',
            'semester' => 1,
        ]);

        $tokenA = $userA->createToken('token_a')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$tokenA,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['course_code' => 'STUDENT-A-COURSE'])
            ->assertJsonMissing(['course_code' => 'STUDENT-B-COURSE']);
    });

    it('returns 400 MISSING_TERM when subjects query omits school_year or semester', function (array $queryParams) {
        $user = User::factory()->create(['role' => 'student']);
        Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('token')->plainTextToken;

        $url = '/api/student/subjects?'.http_build_query($queryParams);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson($url);

        $response->assertStatus(400)
            ->assertExactJson([
                'error' => 'MISSING_TERM',
            ]);
    })->with([
        'missing school_year' => [['semester' => 1]],
        'missing semester' => [['school_year' => '2026-2027']],
        'missing both' => [[]],
        'empty school_year' => [['school_year' => '', 'semester' => 1]],
        'empty semester' => [['school_year' => '2026-2027', 'semester' => '']],
    ]);

    it('rejects non-student roles when requesting subjects', function (string $role) {
        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);
    })->with(['registrar', 'cashier', 'department_staff', 'admin']);

    it('returns 401 UNAUTHENTICATED when requesting subjects without token', function () {
        $response = $this->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(401)
            ->assertExactJson(['error' => 'UNAUTHENTICATED']);
    });

    it('returns 403 ACCOUNT_DEACTIVATED when requesting subjects with deactivated student', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => false]);
        Student::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(403)
            ->assertExactJson(['error' => 'ACCOUNT_DEACTIVATED']);
    });
});

/*
|--------------------------------------------------------------------------
| GET /api/student/grades
|--------------------------------------------------------------------------
*/

describe('Student Grades Endpoint', function () {
    it('returns only status=completed enrollments with course code, title, and grade', function () {
        $user = User::factory()->create(['role' => 'student']);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $completedCourse = Course::factory()->create(['code' => 'CS201', 'title' => 'Data Structures']);
        $inProgressCourse = Course::factory()->create(['code' => 'CS202', 'title' => 'Algorithms']);
        $droppedCourse = Course::factory()->create(['code' => 'CS203', 'title' => 'Operating Systems']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $completedCourse->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => 1.75,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $inProgressCourse->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'enrolled',
            'grade' => null,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $droppedCourse->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'dropped',
            'grade' => null,
        ]);

        $token = $user->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([
                [
                    'course_code' => 'CS201',
                    'title' => 'Data Structures',
                    'grade' => '1.75',
                ],
            ])
            ->assertJsonMissing(['course_code' => 'CS202'])
            ->assertJsonMissing(['course_code' => 'CS203']);
    });

    it('returns empty array when student has enrollments but none are completed', function () {
        $user = User::factory()->create(['role' => 'student']);
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

        $token = $user->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertExactJson([]);
    });

    it('filters completed grades strictly by specified school_year and semester', function () {
        $user = User::factory()->create(['role' => 'student']);
        $student = Student::factory()->create(['user_id' => $user->id]);

        $courseSem1 = Course::factory()->create(['code' => 'GRADE-SEM1']);
        $courseSem2 = Course::factory()->create(['code' => 'GRADE-SEM2']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $courseSem1->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => 1.25,
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $courseSem2->id,
            'school_year' => '2026-2027',
            'semester' => 2,
            'status' => 'completed',
            'grade' => 2.00,
        ]);

        $token = $user->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['course_code' => 'GRADE-SEM1'])
            ->assertJsonMissing(['course_code' => 'GRADE-SEM2']);
    });

    it('ensures student cannot view completed grades of another student', function () {
        $userA = User::factory()->create(['role' => 'student']);
        $studentA = Student::factory()->create(['user_id' => $userA->id]);

        $userB = User::factory()->create(['role' => 'student']);
        $studentB = Student::factory()->create(['user_id' => $userB->id]);

        $courseA = Course::factory()->create(['code' => 'STUDENT-A-GRADE']);
        $courseB = Course::factory()->create(['code' => 'STUDENT-B-GRADE']);

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
            'grade' => 1.25,
        ]);

        $tokenA = $userA->createToken('token_a')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$tokenA,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonFragment(['course_code' => 'STUDENT-A-GRADE'])
            ->assertJsonMissing(['course_code' => 'STUDENT-B-GRADE']);
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
        expect((float) $grades['CS-HIGH']['grade'])->toBe(1.0)
            ->and((float) $grades['CS-FAIL']['grade'])->toBe(5.0);
    });

    it('returns 400 MISSING_TERM when grades query omits school_year or semester', function (array $queryParams) {
        $user = User::factory()->create(['role' => 'student']);
        Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('token')->plainTextToken;

        $url = '/api/student/grades?'.http_build_query($queryParams);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson($url);

        $response->assertStatus(400)
            ->assertExactJson([
                'error' => 'MISSING_TERM',
            ]);
    })->with([
        'missing school_year' => [['semester' => 1]],
        'missing semester' => [['school_year' => '2026-2027']],
        'missing both' => [[]],
        'empty school_year' => [['school_year' => '', 'semester' => 1]],
        'empty semester' => [['school_year' => '2026-2027', 'semester' => '']],
    ]);

    it('rejects non-student roles when requesting grades', function (string $role) {
        $user = User::factory()->create(['role' => $role]);
        $token = $user->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);
    })->with(['registrar', 'cashier', 'department_staff', 'admin']);

    it('returns 401 UNAUTHENTICATED when requesting grades without token', function () {
        $response = $this->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(401)
            ->assertExactJson(['error' => 'UNAUTHENTICATED']);
    });

    it('returns 403 ACCOUNT_DEACTIVATED when requesting grades with deactivated student', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => false]);
        Student::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(403)
            ->assertExactJson(['error' => 'ACCOUNT_DEACTIVATED']);
    });
});
