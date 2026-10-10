<?php

use App\Models\Clearance;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Document Request & Guards Feature Tests (FR-13, FR-14, FR-18, FR-19, FR-20)
|--------------------------------------------------------------------------
|
| Covers:
| - FR-13 / FR-14: ClearanceGuard and BalanceGuard
| - FR-18: POST /api/documents/requests
| - FR-19: PATCH /api/documents/requests/{id}/status state transitions
| - Edge Case 3: Duplicate open request of same type -> 409 DUPLICATE_REQUEST
| - Edge Case 7: Blank or whitespace-only purpose -> 422 VALIDATION_FAILED
|
*/

describe('FR-18: Student Document Request Submission', function () {
    it('creates document request when balance is zero and all clearances are approved', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'balance_centavos' => 0,
        ]);

        $dept = Department::factory()->create();
        Clearance::factory()->create([
            'student_id' => $student->id,
            'department_id' => $dept->id,
            'status' => 'approved',
        ]);

        $token = $studentUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/documents/requests', [
            'type' => 'tor',
            'purpose' => 'Employment Application',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'pending',
            ])
            ->assertJsonStructure(['id', 'status']);

        $this->assertDatabaseHas('document_requests', [
            'student_id' => $student->id,
            'type' => 'tor',
            'purpose' => 'Employment Application',
            'status' => 'pending',
        ]);
    });

    it('returns 422 OUTSTANDING_BALANCE when student balance is greater than zero', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'balance_centavos' => 150000, // ₱1,500.00
        ]);

        $dept = Department::factory()->create();
        Clearance::factory()->create([
            'student_id' => $student->id,
            'department_id' => $dept->id,
            'status' => 'approved',
        ]);

        $token = $studentUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/documents/requests', [
            'type' => 'tor',
            'purpose' => 'Employment Application',
        ]);

        $response->assertStatus(422)
            ->assertExactJson(['error' => 'OUTSTANDING_BALANCE']);

        expect(DocumentRequest::count())->toBe(0);
    });

    it('returns 422 CLEARANCE_INCOMPLETE when any department clearance is pending or denied', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'balance_centavos' => 0,
        ]);

        $dept1 = Department::factory()->create();
        $dept2 = Department::factory()->create();

        Clearance::factory()->create([
            'student_id' => $student->id,
            'department_id' => $dept1->id,
            'status' => 'approved',
        ]);
        Clearance::factory()->create([
            'student_id' => $student->id,
            'department_id' => $dept2->id,
            'status' => 'pending', // Incomplete!
        ]);

        $token = $studentUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/documents/requests', [
            'type' => 'cor',
            'purpose' => 'Scholarship Renewal',
        ]);

        $response->assertStatus(422)
            ->assertExactJson(['error' => 'CLEARANCE_INCOMPLETE']);

        expect(DocumentRequest::count())->toBe(0);
    });

    it('returns 422 CLEARANCE_INCOMPLETE when student has no clearances at all', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        Student::factory()->create([
            'user_id' => $studentUser->id,
            'balance_centavos' => 0,
        ]);

        $token = $studentUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/documents/requests', [
            'type' => 'certification',
            'purpose' => 'Visa Application',
        ]);

        $response->assertStatus(422)
            ->assertExactJson(['error' => 'CLEARANCE_INCOMPLETE']);

        expect(DocumentRequest::count())->toBe(0);
    });

    it('returns 409 DUPLICATE_REQUEST when an open request of the same type already exists', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'balance_centavos' => 0,
        ]);

        $dept = Department::factory()->create();
        Clearance::factory()->create([
            'student_id' => $student->id,
            'department_id' => $dept->id,
            'status' => 'approved',
        ]);

        // Existing open request
        DocumentRequest::factory()->create([
            'student_id' => $student->id,
            'type' => 'tor',
            'status' => 'processing',
        ]);

        $token = $studentUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/documents/requests', [
            'type' => 'tor',
            'purpose' => 'Second TOR Request',
        ]);

        $response->assertStatus(409)
            ->assertExactJson(['error' => 'DUPLICATE_REQUEST']);
    });

    it('allows new request of same type if prior request is released or rejected', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'balance_centavos' => 0,
        ]);

        $dept = Department::factory()->create();
        Clearance::factory()->create([
            'student_id' => $student->id,
            'department_id' => $dept->id,
            'status' => 'approved',
        ]);

        // Prior request is already released
        DocumentRequest::factory()->create([
            'student_id' => $student->id,
            'type' => 'tor',
            'status' => 'released',
        ]);

        $token = $studentUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/documents/requests', [
            'type' => 'tor',
            'purpose' => 'Second TOR Request after released',
        ]);

        $response->assertStatus(201)
            ->assertJson(['status' => 'pending']);
    });

    it('returns 422 VALIDATION_FAILED when purpose is blank or whitespace only', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'balance_centavos' => 0,
        ]);

        $dept = Department::factory()->create();
        Clearance::factory()->create([
            'student_id' => $student->id,
            'department_id' => $dept->id,
            'status' => 'approved',
        ]);

        $token = $studentUser->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/documents/requests', [
            'type' => 'tor',
            'purpose' => '    ',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'VALIDATION_FAILED']);
    });

    it('returns 403 UNAUTHORIZED_ROLE when non-student attempts to submit document request', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/documents/requests', [
            'type' => 'tor',
            'purpose' => 'Testing',
        ]);

        $response->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);
    });
});

describe('FR-19: Registrar Document Request Status Transitions', function () {
    it('allows valid sequential status transitions from pending to processing to ready to released', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('token')->plainTextToken;

        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'balance_centavos' => 0,
        ]);
        $dept = Department::factory()->create();
        Clearance::factory()->create([
            'student_id' => $student->id,
            'department_id' => $dept->id,
            'status' => 'approved',
        ]);

        $docReq = DocumentRequest::factory()->create([
            'student_id' => $student->id,
            'status' => 'pending',
            'type' => 'tor',
        ]);

        // 1. pending -> processing
        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/documents/requests/{$docReq->id}/status", [
            'status' => 'processing',
        ]);
        $res1->assertStatus(200)->assertJson(['id' => $docReq->id, 'status' => 'processing']);

        // 2. processing -> ready
        $res2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/documents/requests/{$docReq->id}/status", [
            'status' => 'ready',
        ]);
        $res2->assertStatus(200)->assertJson(['id' => $docReq->id, 'status' => 'ready']);

        // 3. ready -> released (re-checks guards)
        $res3 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/documents/requests/{$docReq->id}/status", [
            'status' => 'released',
        ]);
        $res3->assertStatus(200)->assertJson(['id' => $docReq->id, 'status' => 'released']);

        $docReq->refresh();
        expect($docReq->status)->toBe('released');
        expect($docReq->released_at)->not->toBeNull();
        expect($docReq->processed_by)->toBe($registrar->id);
    });

    it('allows transitions to rejected from pending or processing', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('token')->plainTextToken;

        $docPending = DocumentRequest::factory()->create(['status' => 'pending']);
        $res1 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/documents/requests/{$docPending->id}/status", [
            'status' => 'rejected',
        ]);
        $res1->assertStatus(200)->assertJson(['status' => 'rejected']);

        $docProcessing = DocumentRequest::factory()->create(['status' => 'processing']);
        $res2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/documents/requests/{$docProcessing->id}/status", [
            'status' => 'rejected',
        ]);
        $res2->assertStatus(200)->assertJson(['status' => 'rejected']);
    });

    it('rejects invalid state transitions with 409 INVALID_TRANSITION', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('token')->plainTextToken;

        $doc = DocumentRequest::factory()->create(['status' => 'released']);

        // released -> processing (invalid transition out of terminal state)
        $res = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/documents/requests/{$doc->id}/status", [
            'status' => 'processing',
        ]);
        $res->assertStatus(409)->assertExactJson(['error' => 'INVALID_TRANSITION']);

        // released -> released (self transition on terminal state)
        $resSame = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/documents/requests/{$doc->id}/status", [
            'status' => 'released',
        ]);
        $resSame->assertStatus(409)->assertExactJson(['error' => 'INVALID_TRANSITION']);

        // rejected -> pending
        $docRejected = DocumentRequest::factory()->create(['status' => 'rejected']);
        $resRejected = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/documents/requests/{$docRejected->id}/status", [
            'status' => 'pending',
        ]);
        $resRejected->assertStatus(409)->assertExactJson(['error' => 'INVALID_TRANSITION']);
    });

    it('re-checks BalanceGuard and ClearanceGuard on transition into released', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('token')->plainTextToken;

        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'balance_centavos' => 50000, // Balance accumulated!
        ]);
        $dept = Department::factory()->create();
        Clearance::factory()->create([
            'student_id' => $student->id,
            'department_id' => $dept->id,
            'status' => 'approved',
        ]);

        $doc = DocumentRequest::factory()->create([
            'student_id' => $student->id,
            'status' => 'ready',
        ]);

        // Attempt transition to released with outstanding balance
        $resBalance = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/documents/requests/{$doc->id}/status", [
            'status' => 'released',
        ]);
        $resBalance->assertStatus(422)->assertExactJson(['error' => 'OUTSTANDING_BALANCE']);

        // Fix balance, but clearance gets revoked/denied
        $student->update(['balance_centavos' => 0]);
        Clearance::where('student_id', $student->id)->update(['status' => 'denied']);

        $resClearance = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/documents/requests/{$doc->id}/status", [
            'status' => 'released',
        ]);
        $resClearance->assertStatus(422)->assertExactJson(['error' => 'CLEARANCE_INCOMPLETE']);
    });

    it('returns 404 for unknown document request id', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson('/api/documents/requests/999999/status', [
            'status' => 'processing',
        ]);

        $response->assertStatus(404)->assertExactJson(['error' => 'NOT_FOUND']);
    });

    it('returns 403 for non-registrar roles trying to patch status', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('token')->plainTextToken;

        $doc = DocumentRequest::factory()->create(['status' => 'pending']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/documents/requests/{$doc->id}/status", [
            'status' => 'processing',
        ]);

        $response->assertStatus(403)->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);
    });
});

describe('FR-20: Student Document Request Status Polling', function () {
    it('allows student to list their document requests and see updated statuses', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id]);

        $doc1 = DocumentRequest::factory()->create([
            'student_id' => $student->id,
            'type' => 'tor',
            'purpose' => 'Job Application',
            'status' => 'processing',
            'requested_at' => now()->subDay(),
        ]);

        $doc2 = DocumentRequest::factory()->create([
            'student_id' => $student->id,
            'type' => 'cor',
            'purpose' => 'Scholarship',
            'status' => 'pending',
            'requested_at' => now(),
        ]);

        // Another student's request
        $otherStudent = Student::factory()->create();
        DocumentRequest::factory()->create([
            'student_id' => $otherStudent->id,
            'type' => 'certification',
        ]);

        $token = $studentUser->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/documents/requests');

        $response->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJson([
                ['id' => $doc2->id, 'type' => 'cor', 'status' => 'pending'],
                ['id' => $doc1->id, 'type' => 'tor', 'status' => 'processing'],
            ]);
    });
});
