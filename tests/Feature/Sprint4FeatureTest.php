<?php

use App\Models\AuditLog;
use App\Models\Clearance;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Sprint 4 P1 / P2 Feature Tests
|--------------------------------------------------------------------------
|
| Covers:
| - FR-7: Course Roster (GET /api/registrar/courses/{id}/roster)
| - FR-10: Payment History (GET /api/cashier/students/{id}/payments)
| - FR-12: Cross-Department Clearance View (GET /api/students/{id}/clearances)
| - FR-16: Admin Password Reset & Token Revocation (PATCH /api/admin/users/{id}/password)
| - FR-17: Audit Log Read & Filter (GET /api/admin/audit-logs)
|
*/

describe('FR-7: Course Roster', function () {
    it('returns all enrolled students with grades and statuses for the specified term', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('token')->plainTextToken;

        $dept = Department::factory()->create();
        $course = Course::factory()->create(['department_id' => $dept->id, 'code' => 'CS101']);

        $user1 = User::factory()->create(['name' => 'Alice Student']);
        $student1 = Student::factory()->create(['user_id' => $user1->id, 'student_number' => '2026-00001']);

        $user2 = User::factory()->create(['name' => 'Bob Student']);
        $student2 = Student::factory()->create(['user_id' => $user2->id, 'student_number' => '2026-00002']);

        // Enrollment for requested term
        Enrollment::factory()->create([
            'student_id' => $student1->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'grade' => 1.75,
            'status' => 'completed',
        ]);
        Enrollment::factory()->create([
            'student_id' => $student2->id,
            'course_id' => $course->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'grade' => null,
            'status' => 'enrolled',
        ]);

        // Enrollment for different term (should NOT appear)
        $user3 = User::factory()->create();
        $student3 = Student::factory()->create(['user_id' => $user3->id]);
        Enrollment::factory()->create([
            'student_id' => $student3->id,
            'course_id' => $course->id,
            'school_year' => '2025-2026',
            'semester' => 2,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson("/api/registrar/courses/{$course->id}/roster?school_year=2026-2027&semester=1");

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJson([
                [
                    'student_id' => $student1->id,
                    'student_number' => '2026-00001',
                    'name' => 'Alice Student',
                    'grade' => 1.75,
                    'status' => 'completed',
                ],
                [
                    'student_id' => $student2->id,
                    'student_number' => '2026-00002',
                    'name' => 'Bob Student',
                    'grade' => null,
                    'status' => 'enrolled',
                ],
            ]);
    });

    it('returns 404 COURSE_NOT_FOUND for unknown course id', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/registrar/courses/999999/roster?school_year=2026-2027&semester=1');

        $response->assertStatus(404)
            ->assertExactJson(['error' => 'COURSE_NOT_FOUND']);
    });

    it('returns 403 UNAUTHORIZED_ROLE when non-registrar requests course roster', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $token = $studentUser->createToken('token')->plainTextToken;

        $course = Course::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson("/api/registrar/courses/{$course->id}/roster?school_year=2026-2027&semester=1");

        $response->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);
    });
});

describe('FR-10: Payment History', function () {
    it('returns student payments ordered newest-first to cashier and admin', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $cashierToken = $cashier->createToken('cashier_token')->plainTextToken;

        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $adminToken = $admin->createToken('admin_token')->plainTextToken;

        $student = Student::factory()->create();

        $payment1 = Payment::factory()->create([
            'student_id' => $student->id,
            'or_number' => 'OR-2026-00000001',
            'amount_centavos' => 50000,
            'payment_type' => 'tuition',
            'paid_at' => now()->subDays(2),
        ]);

        $payment2 = Payment::factory()->create([
            'student_id' => $student->id,
            'or_number' => 'OR-2026-00000002',
            'amount_centavos' => 25000,
            'payment_type' => 'misc_fee',
            'paid_at' => now()->subDay(),
        ]);

        // Cashier check
        $resCashier = $this->withHeaders([
            'Authorization' => 'Bearer '.$cashierToken,
            'Accept' => 'application/json',
        ])->getJson("/api/cashier/students/{$student->id}/payments");

        $resCashier->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJson([
                [
                    'or_number' => 'OR-2026-00000002',
                    'amount_centavos' => 25000,
                    'payment_type' => 'misc_fee',
                ],
                [
                    'or_number' => 'OR-2026-00000001',
                    'amount_centavos' => 50000,
                    'payment_type' => 'tuition',
                ],
            ]);

        // Admin check
        $resAdmin = $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'Accept' => 'application/json',
        ])->getJson("/api/cashier/students/{$student->id}/payments");

        $resAdmin->assertStatus(200)->assertJsonCount(2);
    });

    it('returns 404 STUDENT_NOT_FOUND when student does not exist', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/cashier/students/999999/payments');

        $response->assertStatus(404)
            ->assertExactJson(['error' => 'STUDENT_NOT_FOUND']);
    });

    it('returns 403 UNAUTHORIZED_ROLE when non-cashier/admin requests payments', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('token')->plainTextToken;

        $student = Student::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson("/api/cashier/students/{$student->id}/payments");

        $response->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);
    });
});

describe('FR-12: Cross-Department Clearance View', function () {
    it('allows a student to view only their own clearances across all departments', function () {
        $studentUserA = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $studentA = Student::factory()->create(['user_id' => $studentUserA->id]);

        $dept1 = Department::factory()->create(['code' => 'LIB']);
        $dept2 = Department::factory()->create(['code' => 'ACC']);

        Clearance::factory()->create([
            'student_id' => $studentA->id,
            'department_id' => $dept1->id,
            'status' => 'approved',
            'remarks' => 'All books returned',
        ]);
        Clearance::factory()->create([
            'student_id' => $studentA->id,
            'department_id' => $dept2->id,
            'status' => 'pending',
            'remarks' => null,
        ]);

        $tokenA = $studentUserA->createToken('tokenA')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$tokenA,
            'Accept' => 'application/json',
        ])->getJson("/api/students/{$studentA->id}/clearances");

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJson([
                [
                    'department_code' => 'LIB',
                    'status' => 'approved',
                    'remarks' => 'All books returned',
                ],
                [
                    'department_code' => 'ACC',
                    'status' => 'pending',
                    'remarks' => null,
                ],
            ]);
    });

    it('rejects student attempting to view another student clearances with 403 UNAUTHORIZED_ROLE', function () {
        $studentUserA = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $tokenA = $studentUserA->createToken('tokenA')->plainTextToken;

        $studentUserB = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $studentB = Student::factory()->create(['user_id' => $studentUserB->id]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$tokenA,
            'Accept' => 'application/json',
        ])->getJson("/api/students/{$studentB->id}/clearances");

        $response->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);
    });

    it('allows staff roles (department_staff, registrar, admin) to view any student clearances', function () {
        $student = Student::factory()->create();
        $dept = Department::factory()->create(['code' => 'REG']);
        Clearance::factory()->create(['student_id' => $student->id, 'department_id' => $dept->id]);

        foreach (['department_staff', 'registrar', 'admin'] as $role) {
            $staff = User::factory()->create(['role' => $role, 'is_active' => true, 'department_id' => $dept->id]);
            $token = $staff->createToken('token')->plainTextToken;

            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])->getJson("/api/students/{$student->id}/clearances");

            $response->assertStatus(200)->assertJsonCount(1);
        }
    });

    it('returns 404 STUDENT_NOT_FOUND when student does not exist', function () {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $admin->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/students/999999/clearances');

        $response->assertStatus(404)
            ->assertExactJson(['error' => 'STUDENT_NOT_FOUND']);
    });
});

describe('FR-16: Admin Password Reset', function () {
    it('resets password and revokes all active tokens immediately', function () {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $adminToken = $admin->createToken('admin_token')->plainTextToken;

        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $userToken = $user->createToken('session_token')->plainTextToken;

        // Verify existing token works initially
        $preRes = $this->withHeaders([
            'Authorization' => 'Bearer '.$userToken,
            'Accept' => 'application/json',
        ])->getJson('/api/user');
        $preRes->assertStatus(200);

        // Admin resets password (FR-16)
        $resetRes = $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'Accept' => 'application/json',
        ])->patchJson("/api/admin/users/{$user->id}/password", [
            'new_password' => 'BrandNewPassword123!',
        ]);
        $resetRes->assertStatus(204);

        $user->refresh();
        expect(Hash::check('BrandNewPassword123!', $user->password))->toBeTrue();

        // Very next request with prior token returns 401 UNAUTHENTICATED (A-5)
        $this->flushHeaders();
        auth()->forgetGuards();
        $postRes = $this->withHeaders([
            'Authorization' => 'Bearer '.$userToken,
            'Accept' => 'application/json',
        ])->getJson('/api/user');
        $postRes->assertStatus(401)
            ->assertExactJson(['error' => 'UNAUTHENTICATED']);

        // Logging in with new credentials succeeds
        $loginRes = $this->postJson('/api/login', [
            'student_number_or_email' => $user->email,
            'password' => 'BrandNewPassword123!',
        ]);
        $loginRes->assertStatus(200)->assertJsonStructure(['token', 'role']);
    });

    it('returns 404 USER_NOT_FOUND when user does not exist', function () {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $admin->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson('/api/admin/users/999999/password', [
            'new_password' => 'NewPassword123!',
        ]);

        $response->assertStatus(404)
            ->assertExactJson(['error' => 'USER_NOT_FOUND']);
    });
});

describe('FR-17: Audit Log Read & Filters', function () {
    it('allows admin to retrieve paginated audit logs with optional filters', function () {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $adminToken = $admin->createToken('admin_token')->plainTextToken;

        $otherUser = User::factory()->create();

        AuditLog::create([
            'actor_id' => $admin->id,
            'action' => 'user.created',
            'target_type' => 'user',
            'target_id' => 10,
            'changes' => ['email' => 'test1@cuyotech.edu.ph'],
            'created_at' => now()->subMinutes(10),
        ]);

        AuditLog::create([
            'actor_id' => $otherUser->id,
            'action' => 'user.updated',
            'target_type' => 'user',
            'target_id' => 11,
            'changes' => ['role' => 'registrar'],
            'created_at' => now()->subMinutes(5),
        ]);

        // 1. All logs
        $resAll = $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/audit-logs');

        $resAll->assertStatus(200)
            ->assertJsonCount(2);

        // 2. Filter by actor_id
        $resActor = $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'Accept' => 'application/json',
        ])->getJson("/api/admin/audit-logs?actor_id={$admin->id}");

        $resActor->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([
                [
                    'actor_id' => $admin->id,
                    'action' => 'user.created',
                ],
            ]);
    });

    it('returns 403 UNAUTHORIZED_ROLE when non-admin attempts to view audit logs', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/admin/audit-logs');

        $response->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);
    });
});
