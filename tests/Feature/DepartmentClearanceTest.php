<?php

use App\Models\Clearance;
use App\Models\Department;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Department Clearances Feature Tests (FR-11)
|--------------------------------------------------------------------------
| Covers FR-11.1, FR-11.2, FR-11.3, and FR-11.4:
| - GET /api/department/clearances (auto-scoped to caller's department)
| - PATCH /api/department/clearances/{id} (department review & cross-department boundary)
*/

describe('FR-11.1: Department Clearances Index', function () {
    it('returns clearances auto-scoped to the caller department', function () {
        $deptA = Department::factory()->create(['name' => 'College of Computer Studies', 'code' => 'CCS']);
        $deptB = Department::factory()->create(['name' => 'University Library', 'code' => 'LIB']);

        $staffA = User::factory()->create([
            'role' => 'department_staff',
            'department_id' => $deptA->id,
            'is_active' => true,
        ]);

        $student1 = Student::factory()->create(['student_number' => '2026-00001']);
        $student2 = Student::factory()->create(['student_number' => '2026-00002']);

        $clearanceA1 = Clearance::factory()->create([
            'department_id' => $deptA->id,
            'student_id' => $student1->id,
            'school_year' => '2026-2027',
            'semester' => 2,
            'status' => 'pending',
        ]);
        $clearanceA2 = Clearance::factory()->create([
            'department_id' => $deptA->id,
            'student_id' => $student2->id,
            'school_year' => '2026-2027',
            'semester' => 2,
            'status' => 'approved',
        ]);

        // Other department clearance
        Clearance::factory()->create([
            'department_id' => $deptB->id,
            'student_id' => $student1->id,
            'school_year' => '2026-2027',
            'semester' => 2,
            'status' => 'pending',
        ]);

        $token = $staffA->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/department/clearances');

        $response->assertStatus(200);
        $data = $response->json();

        expect($data)->toHaveCount(2);
        expect(collect($data)->pluck('id')->all())->toEqualCanonicalizing([$clearanceA1->id, $clearanceA2->id]);
        expect($data[0])->toHaveKeys(['id', 'student_id', 'student_number', 'school_year', 'semester', 'status']);
    });

    it('filters clearances by status when status query parameter is provided', function () {
        $dept = Department::factory()->create();
        $staff = User::factory()->create([
            'role' => 'department_staff',
            'department_id' => $dept->id,
            'is_active' => true,
        ]);

        $pending = Clearance::factory()->create([
            'department_id' => $dept->id,
            'status' => 'pending',
        ]);
        Clearance::factory()->create([
            'department_id' => $dept->id,
            'status' => 'approved',
        ]);
        Clearance::factory()->create([
            'department_id' => $dept->id,
            'status' => 'denied',
        ]);

        $token = $staff->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/department/clearances?status=pending');

        $response->assertStatus(200);
        $data = $response->json();

        expect($data)->toHaveCount(1);
        expect($data[0]['id'])->toBe($pending->id);
        expect($data[0]['status'])->toBe('pending');
    });

    it('rejects unauthenticated requests with 401', function () {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/department/clearances');

        $response->assertStatus(401)
            ->assertExactJson(['error' => 'UNAUTHENTICATED']);
    });

    it('rejects non-department roles with 403', function () {
        $roles = ['student', 'cashier', 'registrar', 'admin'];

        foreach ($roles as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $token = $user->createToken('test_token')->plainTextToken;

            $response = $this->withHeaders([
                'Authorization' => 'Bearer '.$token,
                'Accept' => 'application/json',
            ])->getJson('/api/department/clearances');

            $response->assertStatus(403)
                ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);
        }
    });

    it('rejects department staff without an assigned department_id with 403', function () {
        $staffWithoutDept = User::factory()->create([
            'role' => 'department_staff',
            'department_id' => null,
            'is_active' => true,
        ]);
        $token = $staffWithoutDept->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/department/clearances');

        $response->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);
    });
});

describe('FR-11.2 & FR-11.3: Department Clearance Review', function () {
    it('allows department staff to approve a clearance belonging to their department', function () {
        $dept = Department::factory()->create(['name' => 'Library']);
        $staff = User::factory()->create([
            'role' => 'department_staff',
            'department_id' => $dept->id,
            'is_active' => true,
        ]);

        $clearance = Clearance::factory()->create([
            'department_id' => $dept->id,
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        $token = $staff->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/department/clearances/{$clearance->id}", [
            'status' => 'approved',
            'remarks' => 'No outstanding book loans or fines.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $clearance->id,
                'status' => 'approved',
            ])
            ->assertJsonStructure(['id', 'status', 'reviewed_at']);

        $clearance->refresh();
        expect($clearance->status)->toBe('approved');
        expect($clearance->remarks)->toBe('No outstanding book loans or fines.');
        expect($clearance->reviewed_by)->toBe($staff->id);
        expect($clearance->reviewed_at)->not->toBeNull();
    });

    it('allows department staff to deny a clearance with remarks', function () {
        $dept = Department::factory()->create(['name' => 'Accounting']);
        $staff = User::factory()->create([
            'role' => 'department_staff',
            'department_id' => $dept->id,
            'is_active' => true,
        ]);

        $clearance = Clearance::factory()->create([
            'department_id' => $dept->id,
            'status' => 'pending',
        ]);

        $token = $staff->createToken('test_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/department/clearances/{$clearance->id}", [
            'status' => 'denied',
            'remarks' => 'Unreturned laboratory equipment.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $clearance->id,
                'status' => 'denied',
            ]);

        $clearance->refresh();
        expect($clearance->status)->toBe('denied');
        expect($clearance->remarks)->toBe('Unreturned laboratory equipment.');
        expect($clearance->reviewed_by)->toBe($staff->id);
    });

    it('allows re-reviewing an already approved clearance and overwrites with no history (last-write-wins)', function () {
        $dept = Department::factory()->create();
        $staff1 = User::factory()->create([
            'role' => 'department_staff',
            'department_id' => $dept->id,
            'is_active' => true,
        ]);
        $staff2 = User::factory()->create([
            'role' => 'department_staff',
            'department_id' => $dept->id,
            'is_active' => true,
        ]);

        $clearance = Clearance::factory()->create([
            'department_id' => $dept->id,
            'status' => 'pending',
        ]);

        $token1 = $staff1->createToken('t1')->plainTextToken;
        $token2 = $staff2->createToken('t2')->plainTextToken;

        // Staff 1 approves
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token1,
            'Accept' => 'application/json',
        ])->patchJson("/api/department/clearances/{$clearance->id}", [
            'status' => 'approved',
            'remarks' => 'Approved initial check.',
        ])->assertStatus(200);

        $clearance->refresh();
        expect($clearance->status)->toBe('approved');
        expect($clearance->reviewed_by)->toBe($staff1->id);

        // Staff 2 later changes to denied (overwriting with no history per A-13)
        $this->withHeaders([
            'Authorization' => 'Bearer '.$token2,
            'Accept' => 'application/json',
        ])->patchJson("/api/department/clearances/{$clearance->id}", [
            'status' => 'denied',
            'remarks' => 'Overruled: Found unreturned equipment.',
        ])->assertStatus(200);

        $clearance->refresh();
        expect($clearance->status)->toBe('denied');
        expect($clearance->remarks)->toBe('Overruled: Found unreturned equipment.');
        expect($clearance->reviewed_by)->toBe($staff2->id);
    });

    it('rejects cross-department clearance review with 403 UNAUTHORIZED_ROLE (FR-11.3)', function () {
        $library = Department::factory()->create(['name' => 'Library']);
        $registrarDept = Department::factory()->create(['name' => 'Registrar Office']);

        $libraryStaff = User::factory()->create([
            'role' => 'department_staff',
            'department_id' => $library->id,
            'is_active' => true,
        ]);

        $registrarStaff = User::factory()->create([
            'role' => 'department_staff',
            'department_id' => $registrarDept->id,
            'is_active' => true,
        ]);

        $libraryClearance = Clearance::factory()->create([
            'department_id' => $library->id,
            'status' => 'pending',
        ]);

        $regToken = $registrarStaff->createToken('reg_token')->plainTextToken;

        // Registrar staff attempts to review Library clearance
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$regToken,
            'Accept' => 'application/json',
        ])->patchJson("/api/department/clearances/{$libraryClearance->id}", [
            'status' => 'approved',
            'remarks' => 'Unauthorized attempt',
        ]);

        $response->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);

        // Verify database remains untouched
        $libraryClearance->refresh();
        expect($libraryClearance->status)->toBe('pending');
        expect($libraryClearance->reviewed_by)->toBeNull();
    });

    it('returns 404 when updating non-existent clearance', function () {
        $dept = Department::factory()->create();
        $staff = User::factory()->create([
            'role' => 'department_staff',
            'department_id' => $dept->id,
            'is_active' => true,
        ]);
        $token = $staff->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson('/api/department/clearances/999999', [
            'status' => 'approved',
        ]);

        $response->assertStatus(404)
            ->assertExactJson(['error' => 'CLEARANCE_NOT_FOUND']);
    });

    it('returns 422 when status is missing or invalid', function () {
        $dept = Department::factory()->create();
        $staff = User::factory()->create([
            'role' => 'department_staff',
            'department_id' => $dept->id,
            'is_active' => true,
        ]);
        $clearance = Clearance::factory()->create(['department_id' => $dept->id]);
        $token = $staff->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/department/clearances/{$clearance->id}", [
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'VALIDATION_FAILED']);
    });
});
