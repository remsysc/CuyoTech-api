<?php

use App\Models\Clearance;
use App\Models\Course;
use App\Models\Department;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Tier 5: Complete Cross-Sprint End-to-End System Integration Test
|--------------------------------------------------------------------------
|
| Validates the full university lifecycle end-to-end adhering strictly to
| PRD, SPEC, and all 20 Functional Requirements across Sprints 1 to 4:
|
| 1. Admin provisions staff accounts and logs audit trails (FR-15, FR-17).
| 2. Registrar sets up courses and enrolls students, tuition running balance
|    increments automatically in the same atomic transaction (FR-5, A-2, A-12).
| 3. Registrar pulls Course Roster (FR-7).
| 4. Cashier collects installment tuition payment with sequential OR and
|    running balance decrements (FR-8, Edge Case 2).
| 5. Printable HTML receipt is retrieved by student and cashier (FR-9).
| 6. Cashier reviews student's payment history (FR-10).
| 7. Registrar grades course and it immediately reflects in Student Portal
|    subjects & grades (FR-2, FR-3, FR-4, FR-6).
| 8. Student applies for graduation document request but gets BLOCKED
|    by ClearanceGuard (FR-13) and BalanceGuard (FR-14).
| 9. Cashier records final payment settling balance to zero.
| 10. Department staff reviews clearance within their own department;
|     unauthorized cross-department patch is rejected with 403 (FR-11).
| 11. Student, staff, and registrar inspect clearances view (FR-12).
| 12. Student resubmits document request -> succeeds with status=pending (FR-18).
| 13. Duplicate open request is rejected with 409 DUPLICATE_REQUEST (Edge Case 3).
| 14. Registrar progresses document request through state machine (FR-19):
|     pending -> processing -> ready -> released.
| 15. Student polls document requests and views released status (FR-20).
| 16. Admin deactivates account -> immediate 403 mid-session kickout (FR-15, A-9).
| 17. Admin resets password -> immediate 401 token invalidation across
|     pre-existing tokens (FR-16, A-5).
| 18. Admin reads and filters audit log verifying every audit action (FR-17).
|
*/

describe('Tier 5: Complete Cross-Sprint End-to-End System Integration Test', function () {
    it('executes the complete university lifecycle from enrollment to graduation document release', function () {
        // =========================================================================
        // Step 1: Admin Provisions System Users (FR-15, FR-17)
        // =========================================================================
        $adminUser = User::factory()->create([
            'name' => 'System Administrator',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $adminToken = $adminUser->createToken('admin_token')->plainTextToken;
        $adminHeaders = ['Authorization' => 'Bearer '.$adminToken, 'Accept' => 'application/json'];

        $ccsDept = Department::factory()->create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
        $libDept = Department::factory()->create(['name' => 'University Library', 'code' => 'LIB']);

        // Admin creates Registrar
        $resReg = $this->withHeaders($adminHeaders)->postJson('/api/admin/users', [
            'name' => 'Regina Registrar',
            'email' => 'regina@cuyotech.edu.ph',
            'role' => 'registrar',
            'password' => 'Password123!',
        ])->assertStatus(201);
        $registrarId = $resReg->json('id');

        // Admin creates Cashier
        $resCash = $this->withHeaders($adminHeaders)->postJson('/api/admin/users', [
            'name' => 'Carl Cashier',
            'email' => 'carl@cuyotech.edu.ph',
            'role' => 'cashier',
            'password' => 'Password123!',
        ])->assertStatus(201);
        $cashierId = $resCash->json('id');

        // Admin creates Library Staff (department_id required)
        $resLib = $this->withHeaders($adminHeaders)->postJson('/api/admin/users', [
            'name' => 'Lisa Librarian',
            'email' => 'lisa@cuyotech.edu.ph',
            'role' => 'department_staff',
            'department_id' => $libDept->id,
            'password' => 'Password123!',
        ])->assertStatus(201);
        $libStaffId = $resLib->json('id');

        // Create Student User & Model
        $resStudent = $this->withHeaders($adminHeaders)->postJson('/api/admin/users', [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@cuyotech.edu.ph',
            'role' => 'student',
            'password' => 'Password123!',
        ])->assertStatus(201);
        $studentUserId = $resStudent->json('id');

        $student = Student::factory()->create([
            'user_id' => $studentUserId,
            'student_number' => '2026-00001',
            'program' => 'BSCS',
            'year_level' => 4,
            'status' => 'active',
            'balance_centavos' => 0,
        ]);

        // =========================================================================
        // Step 2: Actor Authentication via POST /api/login (FR-1)
        // =========================================================================
        $studentLogin = $this->postJson('/api/login', [
            'student_number_or_email' => 'juan@cuyotech.edu.ph',
            'password' => 'Password123!',
        ])->assertStatus(200)->assertJson(['role' => 'student']);
        $studentToken = $studentLogin->json('token');
        $studentHeaders = ['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'];

        $regLogin = $this->postJson('/api/login', [
            'student_number_or_email' => 'regina@cuyotech.edu.ph',
            'password' => 'Password123!',
        ])->assertStatus(200)->assertJson(['role' => 'registrar']);
        $registrarToken = $regLogin->json('token');
        $registrarHeaders = ['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'];

        $cashLogin = $this->postJson('/api/login', [
            'student_number_or_email' => 'carl@cuyotech.edu.ph',
            'password' => 'Password123!',
        ])->assertStatus(200)->assertJson(['role' => 'cashier']);
        $cashierToken = $cashLogin->json('token');
        $cashierHeaders = ['Authorization' => 'Bearer '.$cashierToken, 'Accept' => 'application/json'];

        $libLogin = $this->postJson('/api/login', [
            'student_number_or_email' => 'lisa@cuyotech.edu.ph',
            'password' => 'Password123!',
        ])->assertStatus(200)->assertJson(['role' => 'department_staff']);
        $libStaffToken = $libLogin->json('token');
        $libStaffHeaders = ['Authorization' => 'Bearer '.$libStaffToken, 'Accept' => 'application/json'];

        // Student checks initial profile (FR-2)
        $this->withHeaders($studentHeaders)->getJson('/api/student/profile')
            ->assertStatus(200)
            ->assertExactJson([
                'student_number' => '2026-00001',
                'name' => 'Juan Dela Cruz',
                'program' => 'BSCS',
                'year_level' => 4,
                'status' => 'active',
            ]);

        // =========================================================================
        // Step 3: Registrar Enrolls Student & Assesses Tuition (FR-5, A-2, A-12)
        // =========================================================================
        $thesis = Course::factory()->create([
            'department_id' => $ccsDept->id,
            'code' => 'CS499',
            'title' => 'Thesis 2',
            'units' => 3, // 3 units * 50,000 = 150,000 centavos
        ]);
        $ethics = Course::factory()->create([
            'department_id' => $ccsDept->id,
            'code' => 'GE108',
            'title' => 'Ethics',
            'units' => 3, // 3 units * 50,000 = 150,000 centavos
        ]);

        $term = ['school_year' => '2026-2027', 'semester' => 1];

        $enroll1 = $this->withHeaders($registrarHeaders)->postJson('/api/registrar/enrollments', array_merge($term, [
            'student_id' => $student->id,
            'course_id' => $thesis->id,
        ]))->assertStatus(201)->assertJson(['charge_applied_centavos' => 150000]);

        $enroll2 = $this->withHeaders($registrarHeaders)->postJson('/api/registrar/enrollments', array_merge($term, [
            'student_id' => $student->id,
            'course_id' => $ethics->id,
        ]))->assertStatus(201)->assertJson(['charge_applied_centavos' => 150000]);

        $thesisEnrollmentId = $enroll1->json('id');
        $ethicsEnrollmentId = $enroll2->json('id');

        // Verify balance in database is exactly 300,000 centavos (₱3,000.00)
        expect($student->fresh()->balance_centavos)->toBe(300000);

        // =========================================================================
        // Step 4: Registrar Pulls Course Roster (FR-7)
        // =========================================================================
        $this->withHeaders($registrarHeaders)
            ->getJson("/api/registrar/courses/{$thesis->id}/roster?school_year=2026-2027&semester=1")
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([
                [
                    'student_id' => $student->id,
                    'student_number' => '2026-00001',
                    'name' => 'Juan Dela Cruz',
                    'grade' => null,
                    'status' => 'enrolled',
                ],
            ]);

        // =========================================================================
        // Step 5: Cashier Records Installment Payment (FR-8, FR-9, FR-10)
        // =========================================================================
        $payRes1 = $this->withHeaders($cashierHeaders)->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 200000, // Pay ₱2,000.00 -> remaining balance: ₱1,000.00 (100,000)
            'payment_type' => 'tuition',
        ])->assertStatus(201);

        $orNumber1 = $payRes1->json('or_number');
        expect($student->fresh()->balance_centavos)->toBe(100000);

        // Printable HTML Receipt verification (FR-9)
        $this->withHeaders($studentHeaders)
            ->get("/receipts/{$orNumber1}")
            ->assertStatus(200)
            ->assertSee($orNumber1)
            ->assertSee('Juan Dela Cruz');

        // Payment History (FR-10)
        $this->withHeaders($cashierHeaders)
            ->getJson("/api/cashier/students/{$student->id}/payments")
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJson([
                [
                    'or_number' => $orNumber1,
                    'amount_centavos' => 200000,
                    'payment_type' => 'tuition',
                ],
            ]);

        // =========================================================================
        // Step 6: Registrar Grades Courses (FR-6) -> Reflected in Student Portal (FR-3, FR-4)
        // =========================================================================
        $this->withHeaders($registrarHeaders)
            ->patchJson("/api/registrar/enrollments/{$thesisEnrollmentId}/grade", ['grade' => 1.25])
            ->assertStatus(200)
            ->assertJson(['grade' => 1.25, 'status' => 'completed']);

        $this->withHeaders($registrarHeaders)
            ->patchJson("/api/registrar/enrollments/{$ethicsEnrollmentId}/grade", ['grade' => 1.50])
            ->assertStatus(200)
            ->assertJson(['grade' => 1.50, 'status' => 'completed']);

        // Student checks grades
        $this->withHeaders($studentHeaders)
            ->getJson('/api/student/grades?school_year=2026-2027&semester=1')
            ->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJson([
                ['course_code' => 'CS499', 'grade' => '1.25'],
                ['course_code' => 'GE108', 'grade' => '1.50'],
            ]);

        // =========================================================================
        // Step 7: Clearance & Document Request Guards Enforcement (FR-13, FR-14, FR-18)
        // =========================================================================
        // Library clearance is pending
        $libClearance = Clearance::create([
            'student_id' => $student->id,
            'department_id' => $libDept->id,
            'school_year' => '2026-2027',
            'semester' => 1,
            'status' => 'pending',
        ]);

        // Student attempts to request TOR while balance > 0 -> 422 OUTSTANDING_BALANCE (FR-14)
        $this->withHeaders($studentHeaders)->postJson('/api/documents/requests', [
            'type' => 'tor',
            'purpose' => 'Graduation Evaluation',
        ])->assertStatus(422)->assertExactJson(['error' => 'OUTSTANDING_BALANCE']);

        // Cashier pays remaining balance (100,000 centavos) -> balance becomes 0
        $this->withHeaders($cashierHeaders)->postJson('/api/cashier/payments', [
            'student_id' => $student->id,
            'amount_centavos' => 100000,
            'payment_type' => 'tuition',
        ])->assertStatus(201);
        expect($student->fresh()->balance_centavos)->toBe(0);

        // Student attempts to request TOR with balance = 0 but pending clearance -> 422 CLEARANCE_INCOMPLETE (FR-13)
        $this->withHeaders($studentHeaders)->postJson('/api/documents/requests', [
            'type' => 'tor',
            'purpose' => 'Graduation Evaluation',
        ])->assertStatus(422)->assertExactJson(['error' => 'CLEARANCE_INCOMPLETE']);

        // =========================================================================
        // Step 8: Department Staff Clearance Review (FR-11, FR-12)
        // =========================================================================
        // Another staff member from different department cannot review (FR-11.3)
        $otherDept = Department::factory()->create(['code' => 'ACC']);
        $accStaff = User::factory()->create(['role' => 'department_staff', 'department_id' => $otherDept->id, 'is_active' => true]);
        $accStaffToken = $accStaff->createToken('token')->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer '.$accStaffToken, 'Accept' => 'application/json'])
            ->patchJson("/api/department/clearances/{$libClearance->id}", ['status' => 'approved'])
            ->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);

        // Proper library staff approves clearance (FR-11.2)
        $this->withHeaders($libStaffHeaders)
            ->patchJson("/api/department/clearances/{$libClearance->id}", [
                'status' => 'approved',
                'remarks' => 'All library books returned and clearance signed',
            ])->assertStatus(200)->assertJson(['status' => 'approved']);

        // Student views their multi-department clearances (FR-12)
        $this->withHeaders($studentHeaders)
            ->getJson("/api/students/{$student->id}/clearances")
            ->assertStatus(200)
            ->assertJson([
                [
                    'department_code' => 'LIB',
                    'status' => 'approved',
                    'remarks' => 'All library books returned and clearance signed',
                ],
            ]);

        // =========================================================================
        // Step 9: Student Successfully Submits Document Request (FR-18, Edge Case 3)
        // =========================================================================
        $docReqRes = $this->withHeaders($studentHeaders)->postJson('/api/documents/requests', [
            'type' => 'tor',
            'purpose' => 'Graduation Evaluation',
        ])->assertStatus(201)->assertJson(['status' => 'pending']);

        $docRequestId = $docReqRes->json('id');

        // Duplicate request of same type while open -> 409 DUPLICATE_REQUEST (Edge Case 3)
        $this->withHeaders($studentHeaders)->postJson('/api/documents/requests', [
            'type' => 'tor',
            'purpose' => 'Second attempt',
        ])->assertStatus(409)->assertExactJson(['error' => 'DUPLICATE_REQUEST']);

        // =========================================================================
        // Step 10: Registrar Walks Document Through Lifecycle (FR-19, FR-20)
        // =========================================================================
        // pending -> processing
        $this->withHeaders($registrarHeaders)
            ->patchJson("/api/documents/requests/{$docRequestId}/status", ['status' => 'processing'])
            ->assertStatus(200)->assertJson(['status' => 'processing']);

        // processing -> ready
        $this->withHeaders($registrarHeaders)
            ->patchJson("/api/documents/requests/{$docRequestId}/status", ['status' => 'ready'])
            ->assertStatus(200)->assertJson(['status' => 'ready']);

        // ready -> released (guards re-evaluated and pass)
        $this->withHeaders($registrarHeaders)
            ->patchJson("/api/documents/requests/{$docRequestId}/status", ['status' => 'released'])
            ->assertStatus(200)->assertJson(['status' => 'released']);

        // Invalid transition out of terminal released state -> 409 INVALID_TRANSITION
        $this->withHeaders($registrarHeaders)
            ->patchJson("/api/documents/requests/{$docRequestId}/status", ['status' => 'processing'])
            ->assertStatus(409)->assertExactJson(['error' => 'INVALID_TRANSITION']);

        // Student polls document status (FR-20)
        $this->withHeaders($studentHeaders)
            ->getJson('/api/documents/requests')
            ->assertStatus(200)
            ->assertJson([
                [
                    'id' => $docRequestId,
                    'type' => 'tor',
                    'status' => 'released',
                ],
            ]);

        // =========================================================================
        // Step 11: Security & Admin Account Governance (FR-15, FR-16, FR-17)
        // =========================================================================
        // Deactivate Cashier (FR-15) -> Immediate 403 on next request with existing token (A-9)
        $this->withHeaders($adminHeaders)
            ->patchJson("/api/admin/users/{$cashierId}", ['is_active' => false])
            ->assertStatus(200)->assertJson(['is_active' => false]);

        $this->withHeaders($cashierHeaders)
            ->postJson('/api/cashier/payments', [
                'student_id' => $student->id,
                'amount_centavos' => 1000,
                'payment_type' => 'tuition',
            ])->assertStatus(403)->assertExactJson(['error' => 'ACCOUNT_DEACTIVATED']);

        // Admin resets Registrar password (FR-16) -> Revokes tokens (A-5)
        $this->withHeaders($adminHeaders)
            ->patchJson("/api/admin/users/{$registrarId}/password", ['new_password' => 'NewRegPass123!'])
            ->assertStatus(204);

        $this->flushHeaders();
        auth()->forgetGuards();

        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->getJson('/api/user')
            ->assertStatus(401)->assertExactJson(['error' => 'UNAUTHENTICATED']);

        // Admin verifies Audit Trail (FR-17)
        $auditRes = $this->withHeaders($adminHeaders)->getJson('/api/admin/audit-logs');
        $auditRes->assertStatus(200);

        // Verify actions logged: user.created, user.updated, user.password_reset
        $actions = collect($auditRes->json())->pluck('action')->all();
        expect($actions)->toContain('user.created');
        expect($actions)->toContain('user.updated');
        expect($actions)->toContain('user.password_reset');
    });
});
