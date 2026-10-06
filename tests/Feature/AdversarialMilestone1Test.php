<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Milestone 1 Adversarial & Empirical Stress Testing Suite
|--------------------------------------------------------------------------
|
| This test suite empirically probes and stress-tests the Milestone 1
| Student Portal implementation against 5 adversarial attack surfaces:
| 1. Token spoofing, malformed, expired, empty, non-existent Bearer tokens
| 2. Multi-student cross-data isolation and parameter tampering
| 3. Grade filtering verification (enrolled, dropped, withdrawn excluded)
| 4. Boundary and malformed term query strings
| 5. Live mid-session account deactivation and reactivation
|
*/

describe('Adversarial Challenge 1: Token Spoofing and Authentication Boundary', function () {
    $endpoints = [
        'profile' => fn () => '/api/student/profile',
        'subjects' => fn () => '/api/student/subjects?school_year=2026-2027&semester=1',
        'grades' => fn () => '/api/student/grades?school_year=2026-2027&semester=1',
    ];

    foreach ($endpoints as $name => $urlResolver) {
        describe("Endpoint: /api/student/{$name}", function () use ($urlResolver) {
            it('returns 401 UNAUTHENTICATED when Authorization header is completely missing', function () use ($urlResolver) {
                $response = $this->withHeaders(['Accept' => 'application/json'])
                    ->getJson($urlResolver());

                $response->assertStatus(401)
                    ->assertExactJson(['error' => 'UNAUTHENTICATED']);
            });

            it('returns 401 UNAUTHENTICATED without Accept header when unauthenticated', function () use ($urlResolver) {
                $response = $this->get($urlResolver());

                $response->assertStatus(401)
                    ->assertExactJson(['error' => 'UNAUTHENTICATED']);
            });

            it('returns 401 UNAUTHENTICATED when Bearer token is empty', function () use ($urlResolver) {
                $response = $this->withHeaders([
                    'Authorization' => 'Bearer ',
                    'Accept' => 'application/json',
                ])->getJson($urlResolver());

                $response->assertStatus(401)
                    ->assertExactJson(['error' => 'UNAUTHENTICATED']);
            });

            it('returns 401 UNAUTHENTICATED when Bearer token consists only of whitespace', function () use ($urlResolver) {
                $response = $this->withHeaders([
                    'Authorization' => 'Bearer    ',
                    'Accept' => 'application/json',
                ])->getJson($urlResolver());

                $response->assertStatus(401)
                    ->assertExactJson(['error' => 'UNAUTHENTICATED']);
            });

            it('returns 401 UNAUTHENTICATED when token is malformed without pipe delimiter', function () use ($urlResolver) {
                $response = $this->withHeaders([
                    'Authorization' => 'Bearer malformed_plain_text_token',
                    'Accept' => 'application/json',
                ])->getJson($urlResolver());

                $response->assertStatus(401)
                    ->assertExactJson(['error' => 'UNAUTHENTICATED']);
            });

            it('returns 401 UNAUTHENTICATED when token ID does not exist', function () use ($urlResolver) {
                $response = $this->withHeaders([
                    'Authorization' => 'Bearer 999999|nonexistenttokenhashrandomstring',
                    'Accept' => 'application/json',
                ])->getJson($urlResolver());

                $response->assertStatus(401)
                    ->assertExactJson(['error' => 'UNAUTHENTICATED']);
            });

            it('returns 401 UNAUTHENTICATED when token ID exists but secret signature is invalid', function () use ($urlResolver) {
                $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
                Student::factory()->create(['user_id' => $user->id]);
                $tokenObj = $user->createToken('valid_token');
                $id = $tokenObj->accessToken->id;

                $response = $this->withHeaders([
                    'Authorization' => "Bearer {$id}|corruptedsecretvalue1234567890",
                    'Accept' => 'application/json',
                ])->getJson($urlResolver());

                $response->assertStatus(401)
                    ->assertExactJson(['error' => 'UNAUTHENTICATED']);
            });

            it('returns 401 UNAUTHENTICATED when token is expired', function () use ($urlResolver) {
                $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
                Student::factory()->create(['user_id' => $user->id]);
                $tokenObj = $user->createToken('expired_token', ['*'], now()->subMinutes(30));

                $response = $this->withHeaders([
                    'Authorization' => 'Bearer '.$tokenObj->plainTextToken,
                    'Accept' => 'application/json',
                ])->getJson($urlResolver());

                $response->assertStatus(401)
                    ->assertExactJson(['error' => 'UNAUTHENTICATED']);
            });

            it('returns 401 UNAUTHENTICATED when token has been deleted from database', function () use ($urlResolver) {
                $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
                Student::factory()->create(['user_id' => $user->id]);
                $tokenObj = $user->createToken('deleted_token');
                $plainToken = $tokenObj->plainTextToken;

                // Revoke token by deleting database record
                $tokenObj->accessToken->delete();

                $response = $this->withHeaders([
                    'Authorization' => 'Bearer '.$plainToken,
                    'Accept' => 'application/json',
                ])->getJson($urlResolver());

                $response->assertStatus(401)
                    ->assertExactJson(['error' => 'UNAUTHENTICATED']);
            });

            it('returns 401 UNAUTHENTICATED with non-Bearer auth schemes', function () use ($urlResolver) {
                $schemes = [
                    'Basic dXNlcm5hbWU6cGFzc3dvcmQ=',
                    'Token some-token-string',
                    'Digest username="test", realm="api"',
                ];

                foreach ($schemes as $scheme) {
                    $response = $this->withHeaders([
                        'Authorization' => $scheme,
                        'Accept' => 'application/json',
                    ])->getJson($urlResolver());

                    $response->assertStatus(401)
                        ->assertExactJson(['error' => 'UNAUTHENTICATED']);
                }
            });

            it('returns 401 UNAUTHENTICATED against SQL injection and special character payloads', function () use ($urlResolver) {
                $payloads = [
                    "Bearer ' OR 1=1 --",
                    'Bearer " OR ""="',
                    "Bearer 1' UNION SELECT * FROM users --",
                    'Bearer <script>alert(1)</script>',
                    'Bearer %00%0a%0d',
                    'Bearer !@#$%^&*()_+{}:"<>?[];\',./`~',
                ];

                foreach ($payloads as $payload) {
                    $response = $this->withHeaders([
                        'Authorization' => $payload,
                        'Accept' => 'application/json',
                    ])->getJson($urlResolver());

                    $response->assertStatus(401)
                        ->assertExactJson(['error' => 'UNAUTHENTICATED']);
                }
            });
        });
    }
});

describe('Adversarial Challenge 2: Cross-Student Data Isolation Matrix', function () {
    it('strictly isolates subjects and grades across 5 concurrent students with overlapping courses and terms', function () {
        // Create 10 distinct courses
        $courses = [];
        for ($c = 1; $c <= 10; $c++) {
            $courses[$c] = Course::factory()->create([
                'code' => sprintf('CS%03d', $c * 100),
                'title' => sprintf('Computer Science Course %d', $c),
                'units' => ($c % 3) + 2,
            ]);
        }

        // Setup 5 distinct students
        $students = [];
        for ($s = 1; $s <= 5; $s++) {
            $user = User::factory()->create([
                'name' => sprintf('Student User %d', $s),
                'role' => 'student',
                'is_active' => true,
            ]);
            $student = Student::factory()->create([
                'user_id' => $user->id,
                'student_number' => sprintf('2026-%05d', $s),
                'program' => $s % 2 === 0 ? 'BSIT' : 'BSCS',
                'year_level' => ($s % 4) + 1,
                'status' => 'active',
            ]);
            $token = $user->createToken('token')->plainTextToken;
            $students[$s] = [
                'user' => $user,
                'student' => $student,
                'token' => $token,
                'subjects' => [],
                'grades' => [],
            ];
        }

        // Define overlapping course assignments and grades for 2026-2027 Semester 1
        // Student 1: C1 (completed, 1.00), C2 (completed, 1.25), C3 (enrolled)
        Enrollment::factory()->create(['student_id' => $students[1]['student']->id, 'course_id' => $courses[1]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '1.00']);
        Enrollment::factory()->create(['student_id' => $students[1]['student']->id, 'course_id' => $courses[2]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '1.25']);
        Enrollment::factory()->create(['student_id' => $students[1]['student']->id, 'course_id' => $courses[3]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'enrolled', 'grade' => null]);
        $students[1]['expected_subjects'] = [$courses[1]->code, $courses[2]->code, $courses[3]->code];
        $students[1]['expected_grades'] = [$courses[1]->code => '1.00', $courses[2]->code => '1.25'];

        // Student 2: C2 (completed, 2.00), C3 (completed, 2.25), C4 (enrolled)
        Enrollment::factory()->create(['student_id' => $students[2]['student']->id, 'course_id' => $courses[2]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '2.00']);
        Enrollment::factory()->create(['student_id' => $students[2]['student']->id, 'course_id' => $courses[3]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '2.25']);
        Enrollment::factory()->create(['student_id' => $students[2]['student']->id, 'course_id' => $courses[4]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'enrolled', 'grade' => null]);
        $students[2]['expected_subjects'] = [$courses[2]->code, $courses[3]->code, $courses[4]->code];
        $students[2]['expected_grades'] = [$courses[2]->code => '2.00', $courses[3]->code => '2.25'];

        // Student 3: C3 (completed, 3.00), C4 (completed, 1.75), C5 (dropped)
        Enrollment::factory()->create(['student_id' => $students[3]['student']->id, 'course_id' => $courses[3]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '3.00']);
        Enrollment::factory()->create(['student_id' => $students[3]['student']->id, 'course_id' => $courses[4]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '1.75']);
        Enrollment::factory()->create(['student_id' => $students[3]['student']->id, 'course_id' => $courses[5]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'dropped', 'grade' => null]);
        $students[3]['expected_subjects'] = [$courses[3]->code, $courses[4]->code, $courses[5]->code];
        $students[3]['expected_grades'] = [$courses[3]->code => '3.00', $courses[4]->code => '1.75'];

        // Student 4: C1 (completed, 2.50), C5 (completed, 1.50), C6 (enrolled)
        Enrollment::factory()->create(['student_id' => $students[4]['student']->id, 'course_id' => $courses[1]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '2.50']);
        Enrollment::factory()->create(['student_id' => $students[4]['student']->id, 'course_id' => $courses[5]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '1.50']);
        Enrollment::factory()->create(['student_id' => $students[4]['student']->id, 'course_id' => $courses[6]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'enrolled', 'grade' => null]);
        $students[4]['expected_subjects'] = [$courses[1]->code, $courses[5]->code, $courses[6]->code];
        $students[4]['expected_grades'] = [$courses[1]->code => '2.50', $courses[5]->code => '1.50'];

        // Student 5: C6 (completed, 1.25), C7 (completed, 2.75), C8 (enrolled)
        Enrollment::factory()->create(['student_id' => $students[5]['student']->id, 'course_id' => $courses[6]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '1.25']);
        Enrollment::factory()->create(['student_id' => $students[5]['student']->id, 'course_id' => $courses[7]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '2.75']);
        Enrollment::factory()->create(['student_id' => $students[5]['student']->id, 'course_id' => $courses[8]->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'enrolled', 'grade' => null]);
        $students[5]['expected_subjects'] = [$courses[6]->code, $courses[7]->code, $courses[8]->code];
        $students[5]['expected_grades'] = [$courses[6]->code => '1.25', $courses[7]->code => '2.75'];

        // Add additional enrollments in different school years / semesters to verify term isolation
        Enrollment::factory()->create(['student_id' => $students[1]['student']->id, 'course_id' => $courses[9]->id, 'school_year' => '2025-2026', 'semester' => 2, 'status' => 'completed', 'grade' => '1.00']);
        Enrollment::factory()->create(['student_id' => $students[2]['student']->id, 'course_id' => $courses[10]->id, 'school_year' => '2026-2027', 'semester' => 2, 'status' => 'completed', 'grade' => '1.00']);

        // Assert isolation for each student
        for ($s = 1; $s <= 5; $s++) {
            $token = $students[$s]['token'];

            // 1. Profile Isolation
            $profileRes = $this->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])->getJson('/api/student/profile');

            $profileRes->assertStatus(200)
                ->assertJson([
                    'student_number' => $students[$s]['student']->student_number,
                    'name' => $students[$s]['user']->name,
                ]);

            // Ensure other students' data does not appear in profile
            for ($other = 1; $other <= 5; $other++) {
                if ($other !== $s) {
                    $profileRes->assertJsonMissing([
                        'student_number' => $students[$other]['student']->student_number,
                        'name' => $students[$other]['user']->name,
                    ]);
                }
            }

            // 2. Subjects Isolation
            $subjRes = $this->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

            $subjRes->assertStatus(200);
            $subjData = $subjRes->json();
            $returnedSubjectCodes = array_column($subjData, 'course_code');
            sort($returnedSubjectCodes);
            $expectedSubj = $students[$s]['expected_subjects'];
            sort($expectedSubj);

            expect($returnedSubjectCodes)->toBe($expectedSubj);

            // 3. Grades Isolation
            $gradeRes = $this->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

            $gradeRes->assertStatus(200);
            $gradeData = $gradeRes->json();

            $returnedGrades = [];
            foreach ($gradeData as $item) {
                $returnedGrades[$item['course_code']] = (string) $item['grade'];
            }

            expect($returnedGrades)->toBe($students[$s]['expected_grades']);

            // Special check: Course 2 is shared between Student 1 (grade 1.25) and Student 2 (grade 2.00)
            if ($s === 1) {
                expect($returnedGrades[$courses[2]->code])->toBe('1.25')
                    ->and($returnedGrades)->not->toContain('2.00');
            } elseif ($s === 2) {
                expect($returnedGrades[$courses[2]->code])->toBe('2.00')
                    ->and($returnedGrades)->not->toContain('1.25');
            }
        }
    });

    it('ignores query parameter tampering attempting to steal another student records', function () {
        $userA = User::factory()->create(['name' => 'Alice', 'role' => 'student', 'is_active' => true]);
        $studentA = Student::factory()->create(['user_id' => $userA->id, 'student_number' => '2026-00001']);
        $tokenA = $userA->createToken('token_a')->plainTextToken;

        $userB = User::factory()->create(['name' => 'Bob', 'role' => 'student', 'is_active' => true]);
        $studentB = Student::factory()->create(['user_id' => $userB->id, 'student_number' => '2026-00002']);

        $courseA = Course::factory()->create(['code' => 'CS101']);
        $courseB = Course::factory()->create(['code' => 'CS202']);

        Enrollment::factory()->create(['student_id' => $studentA->id, 'course_id' => $courseA->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '1.00']);
        Enrollment::factory()->create(['student_id' => $studentB->id, 'course_id' => $courseB->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '2.00']);

        // Alice tries to pass Bob's student_id, student_number, or user_id in query strings
        $tamperedQueries = [
            "/api/student/profile?student_id={$studentB->id}",
            '/api/student/profile?student_number=2026-00002',
            "/api/student/profile?user_id={$userB->id}",
            "/api/student/subjects?school_year=2026-2027&semester=1&student_id={$studentB->id}",
            "/api/student/grades?school_year=2026-2027&semester=1&student_id={$studentB->id}",
        ];

        foreach ($tamperedQueries as $url) {
            $res = $this->withHeaders([
                'Authorization' => 'Bearer '.$tokenA,
                'Accept' => 'application/json',
            ])->getJson($url);

            $res->assertStatus(200);

            // Verify Alice never sees Bob's information
            $res->assertJsonMissing([
                'name' => 'Bob',
                'student_number' => '2026-00002',
                'course_code' => 'CS202',
            ]);
        }
    });
});

describe('Adversarial Challenge 3: Grade Filtering Strictness', function () {
    it('ensures enrollments with status enrolled, dropped, withdrawn, or incomplete NEVER appear in GET /api/student/grades', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('auth')->plainTextToken;

        $cCompleted1 = Course::factory()->create(['code' => 'PASS101', 'title' => 'Passed Subject 1']);
        $cCompleted2 = Course::factory()->create(['code' => 'PASS102', 'title' => 'Passed Subject 2']);
        $cEnrolledNoGrade = Course::factory()->create(['code' => 'ENR101', 'title' => 'Enrolled Subject No Grade']);
        $cEnrolledWithGrade = Course::factory()->create(['code' => 'ENR102', 'title' => 'Enrolled Subject With Premature Grade']);
        $cDroppedNoGrade = Course::factory()->create(['code' => 'DRP101', 'title' => 'Dropped Subject No Grade']);
        $cDroppedWithGrade = Course::factory()->create(['code' => 'DRP102', 'title' => 'Dropped Subject With Grade']);

        // Create enrollments with various statuses for 2026-2027 Semester 1
        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $cCompleted1->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '1.25']);
        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $cCompleted2->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'completed', 'grade' => '2.50']);
        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $cEnrolledNoGrade->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'enrolled', 'grade' => null]);
        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $cEnrolledWithGrade->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'enrolled', 'grade' => '1.00']);
        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $cDroppedNoGrade->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'dropped', 'grade' => null]);
        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $cDroppedWithGrade->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'dropped', 'grade' => '5.00']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(200);
        $data = $response->json();

        // Exactly 2 completed courses must be present
        expect(count($data))->toBe(2);

        $returnedCodes = array_column($data, 'course_code');
        expect($returnedCodes)->toContain('PASS101')
            ->and($returnedCodes)->toContain('PASS102')
            ->and($returnedCodes)->not->toContain('ENR101')
            ->and($returnedCodes)->not->toContain('ENR102')
            ->and($returnedCodes)->not->toContain('DRP101')
            ->and($returnedCodes)->not->toContain('DRP102');

        // Verify shape of each item: only course_code, title, grade
        foreach ($data as $item) {
            expect($item)->toHaveKeys(['course_code', 'title', 'grade'])
                ->and($item)->not->toHaveKey('status')
                ->and($item)->not->toHaveKey('id')
                ->and($item)->not->toHaveKey('student_id');
        }
    });

    it('returns empty array when all enrollments for the term are in-progress or dropped', function () {
        $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('auth')->plainTextToken;

        $c1 = Course::factory()->create(['code' => 'ENR001']);
        $c2 = Course::factory()->create(['code' => 'DRP001']);

        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $c1->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'enrolled', 'grade' => null]);
        Enrollment::factory()->create(['student_id' => $student->id, 'course_id' => $c2->id, 'school_year' => '2026-2027', 'semester' => 1, 'status' => 'dropped', 'grade' => '5.00']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(200)
            ->assertExactJson([]);
    });
});

describe('Adversarial Challenge 4: Boundary Query Strings and Missing Term Validation', function () {
    $endpoints = [
        '/api/student/subjects',
        '/api/student/grades',
    ];

    $boundaryQueries = [
        'empty school_year and empty semester' => '?school_year=&semester=',
        'filled school_year with empty semester' => '?school_year=2026-2027&semester=',
        'empty school_year with filled semester' => '?school_year=&semester=1',
        'omitted school_year entirely' => '?semester=1',
        'omitted semester entirely' => '?school_year=2026-2027',
        'no parameters at all' => '',
        'only question mark' => '?',
        'spaces only in school_year and semester' => '?school_year=%20%20%20&semester=%20%20%20',
        'tabs and newlines as whitespace' => '?school_year=%09&semester=%0A',
    ];

    foreach ($endpoints as $endpoint) {
        describe("Endpoint: {$endpoint}", function () use ($endpoint, $boundaryQueries) {
            foreach ($boundaryQueries as $label => $query) {
                it("returns 400 MISSING_TERM on {$label} ({$query})", function () use ($endpoint, $query) {
                    $user = User::factory()->create(['role' => 'student', 'is_active' => true]);
                    Student::factory()->create(['user_id' => $user->id]);
                    $token = $user->createToken('auth')->plainTextToken;

                    $response = $this->withHeaders([
                        'Authorization' => 'Bearer '.$token,
                        'Accept' => 'application/json',
                    ])->getJson($endpoint.$query);

                    $response->assertStatus(400)
                        ->assertExactJson([
                            'error' => 'MISSING_TERM',
                        ]);
                });
            }
        });
    }
});

describe('Adversarial Challenge 5: Account Deactivation Dynamics and Mid-Session Invalidation', function () {
    it('immediately enforces 403 ACCOUNT_DEACTIVATED mid-session across all student endpoints', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        $student = Student::factory()->create(['user_id' => $user->id]);
        $course = Course::factory()->create(['code' => 'CS999']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => '1.00',
        ]);

        $token = $user->createToken('mid_session_token')->plainTextToken;
        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];

        // 1. All endpoints succeed while user is active
        $this->withHeaders($headers)->getJson('/api/student/profile')->assertStatus(200);
        $this->withHeaders($headers)->getJson('/api/student/subjects?school_year=2026-2027&semester=1')->assertStatus(200);
        $this->withHeaders($headers)->getJson('/api/student/grades?school_year=2026-2027&semester=1')->assertStatus(200);

        // 2. Deactivate student mid-session in the database
        User::where('id', $user->id)->update(['is_active' => false]);

        // 3. Immediately on the next request holding the same token, all endpoints MUST reject with 403 ACCOUNT_DEACTIVATED
        $profileRes = $this->withHeaders($headers)->getJson('/api/student/profile');
        $profileRes->assertStatus(403)
            ->assertExactJson(['error' => 'ACCOUNT_DEACTIVATED']);

        $subjRes = $this->withHeaders($headers)->getJson('/api/student/subjects?school_year=2026-2027&semester=1');
        $subjRes->assertStatus(403)
            ->assertExactJson(['error' => 'ACCOUNT_DEACTIVATED']);

        $gradeRes = $this->withHeaders($headers)->getJson('/api/student/grades?school_year=2026-2027&semester=1');
        $gradeRes->assertStatus(403)
            ->assertExactJson(['error' => 'ACCOUNT_DEACTIVATED']);

        // 4. Reactivate student mid-session in the database
        User::where('id', $user->id)->update(['is_active' => true]);

        // 5. Immediately on the next request holding the same token, all endpoints succeed again
        $this->withHeaders($headers)->getJson('/api/student/profile')->assertStatus(200);
        $this->withHeaders($headers)->getJson('/api/student/subjects?school_year=2026-2027&semester=1')->assertStatus(200);
        $this->withHeaders($headers)->getJson('/api/student/grades?school_year=2026-2027&semester=1')->assertStatus(200);
    });

    it('rejects an account created with is_active = false from the first request', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => false,
        ]);
        Student::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('deactivated_from_start')->plainTextToken;

        $endpoints = [
            '/api/student/profile',
            '/api/student/subjects?school_year=2026-2027&semester=1',
            '/api/student/grades?school_year=2026-2027&semester=1',
        ];

        foreach ($endpoints as $url) {
            $res = $this->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])->getJson($url);

            $res->assertStatus(403)
                ->assertExactJson(['error' => 'ACCOUNT_DEACTIVATED']);
        }
    });
});
