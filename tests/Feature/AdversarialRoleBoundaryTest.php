<?php

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| Adversarial Role Boundary & Permission Tests (Milestone 1)
|--------------------------------------------------------------------------
*/

describe('Role Matrix Verification against Student Endpoints', function () {
    beforeEach(function () {
        DB::statement('PRAGMA ignore_check_constraints = ON');
    });

    $roles = [
        'registrar',
        'cashier',
        'department_staff',
        'admin',
        'guest',
        'superadmin',
    ];

    it('rejects all non-student and invalid roles on GET /api/student/profile with 403 UNAUTHORIZED_ROLE', function (string $role) {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        $token = $user->createToken('role_test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);
    })->with($roles);

    it('rejects all non-student and invalid roles on GET /api/student/subjects with 403 UNAUTHORIZED_ROLE (with term params)', function (string $role) {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        $token = $user->createToken('role_test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);
    })->with($roles);

    it('rejects all non-student and invalid roles on GET /api/student/subjects with 403 UNAUTHORIZED_ROLE even if term params omitted', function (string $role) {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        $token = $user->createToken('role_test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);
    })->with($roles);

    it('rejects all non-student and invalid roles on GET /api/student/grades with 403 UNAUTHORIZED_ROLE (with term params)', function (string $role) {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        $token = $user->createToken('role_test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);
    })->with($roles);

    it('rejects all non-student and invalid roles on GET /api/student/grades with 403 UNAUTHORIZED_ROLE even if term params omitted', function (string $role) {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        $token = $user->createToken('role_test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);
    })->with($roles);
});

describe('Live Mid-Session Role Alteration Without Token Reissue', function () {
    it('immediately rejects GET /api/student/profile when student role is altered to registrar mid-session', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        Student::factory()->create(['user_id' => $user->id]);

        $token = $user->createToken('session_token')->plainTextToken;

        // Step 1: Initial request succeeds with 200
        $response1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response1->assertStatus(200);

        // Step 2: Role altered in database without token reissue
        $user->update(['role' => 'registrar']);

        // Step 3: Next request using identical token must be immediately rejected with 403 UNAUTHORIZED_ROLE
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response2->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);
    });

    it('immediately rejects GET /api/student/subjects when student role is altered to registrar mid-session', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        $student = Student::factory()->create(['user_id' => $user->id]);
        $course = Course::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'enrolled',
        ]);

        $token = $user->createToken('session_token')->plainTextToken;

        // Step 1: Initial request succeeds with 200
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1')
            ->assertStatus(200);

        // Step 2: Role altered in database
        $user->update(['role' => 'registrar']);

        // Step 3: Next request must be rejected with 403 UNAUTHORIZED_ROLE
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1')
            ->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);
    });

    it('immediately rejects GET /api/student/grades when student role is altered to registrar mid-session', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        $student = Student::factory()->create(['user_id' => $user->id]);
        $course = Course::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'completed',
            'grade' => 1.50,
        ]);

        $token = $user->createToken('session_token')->plainTextToken;

        // Step 1: Initial request succeeds with 200
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1')
            ->assertStatus(200);

        // Step 2: Role altered in database
        $user->update(['role' => 'registrar']);

        // Step 3: Next request must be rejected with 403 UNAUTHORIZED_ROLE
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1')
            ->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);
    });

    it('immediately rejects requests across multiple role mutations (student -> cashier -> admin -> student)', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        Student::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('session_token')->plainTextToken;

        $headers = [
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ];

        // 1. Student -> 200
        $this->withHeaders($headers)->getJson('/api/student/profile')->assertStatus(200);

        // 2. Mutated to cashier -> 403
        $user->update(['role' => 'cashier']);
        $this->withHeaders($headers)->getJson('/api/student/profile')
            ->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);

        // 3. Mutated to admin -> 403
        $user->update(['role' => 'admin']);
        $this->withHeaders($headers)->getJson('/api/student/profile')
            ->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);

        // 4. Mutated back to student -> 200
        $user->update(['role' => 'student']);
        $this->withHeaders($headers)->getJson('/api/student/profile')->assertStatus(200);
    });
});

describe('Edge Case: User With Student Role But Null Student Record', function () {
    it('does not trigger a 500 server error on GET /api/student/profile and returns 403 UNAUTHORIZED_ROLE', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        // Note: No Student record created for this user

        $token = $user->createToken('orphan_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);

        expect($response->status())->not->toBe(500);
    });

    it('does not trigger a 500 server error on GET /api/student/subjects and returns 403 UNAUTHORIZED_ROLE', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        // Note: No Student record created for this user

        $token = $user->createToken('orphan_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);

        expect($response->status())->not->toBe(500);
    });

    it('does not trigger a 500 server error on GET /api/student/subjects when term params omitted', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);

        $token = $user->createToken('orphan_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/subjects');

        $response->assertStatus(400)
            ->assertExactJson([
                'error' => 'MISSING_TERM',
            ]);

        expect($response->status())->not->toBe(500);
    });

    it('does not trigger a 500 server error on GET /api/student/grades and returns 403 UNAUTHORIZED_ROLE', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        // Note: No Student record created for this user

        $token = $user->createToken('orphan_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);

        expect($response->status())->not->toBe(500);
    });

    it('does not trigger a 500 server error on GET /api/student/grades when term params omitted', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);

        $token = $user->createToken('orphan_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/grades');

        $response->assertStatus(400)
            ->assertExactJson([
                'error' => 'MISSING_TERM',
            ]);

        expect($response->status())->not->toBe(500);
    });
});

describe('Adversarial Role Spoofing and Boundary Edge Cases', function () {
    beforeEach(function () {
        DB::statement('PRAGMA ignore_check_constraints = ON');
    });

    it('rejects role spoofing attempts via headers, query params, and body payload by registrar user', function () {
        $user = User::factory()->create([
            'role' => 'registrar',
            'is_active' => true,
        ]);
        $token = $user->createToken('spoof_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
            'X-Role' => 'student',
            'X-User-Role' => 'student',
            'X-Original-Role' => 'student',
        ])->getJson('/api/student/profile?role=student&user_role=student');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);
    });

    it('enforces strict case sensitivity rejecting uppercase or mixed case roles (e.g. STUDENT)', function () {
        $user = User::factory()->create([
            'role' => 'STUDENT',
            'is_active' => true,
        ]);
        $token = $user->createToken('case_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);
    });

    it('prioritizes ACCOUNT_DEACTIVATED over null student record when student is inactive', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => false,
        ]);
        // No student record, and is_active is false

        $token = $user->createToken('inactive_orphan_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'ACCOUNT_DEACTIVATED',
            ]);
    });

    it('returns 401 UNAUTHENTICATED when user is deleted from database mid-session', function () {
        $user = User::factory()->create([
            'role' => 'student',
            'is_active' => true,
        ]);
        Student::factory()->create(['user_id' => $user->id]);
        $token = $user->createToken('delete_token')->plainTextToken;

        // 1. Initial request works
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile')->assertStatus(200);

        // 2. User deleted mid-session
        $user->tokens()->delete();
        $user->delete();

        // 3. Next request rejected
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile')
            ->assertStatus(401)
            ->assertExactJson(['error' => 'UNAUTHENTICATED']);
    });
});
