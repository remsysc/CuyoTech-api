<?php

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| Admin User Management Feature Tests (FR-15)
|--------------------------------------------------------------------------
|
| Covers:
| - FR-15.1: POST /api/admin/users
| - FR-15.2: 409 EMAIL_TAKEN on duplicate email
| - FR-15.3: PATCH /api/admin/users/{id} (updates role, department_id, is_active)
| - FR-15.4: 404 USER_NOT_FOUND on non-existent user
| - FR-15.5: AuditLog entries created on create, update, and deactivation
| - FR-15.6: Deactivated user's next request with valid token returns 403 ACCOUNT_DEACTIVATED
| - FR-15.7: Non-admin caller receives 403 UNAUTHORIZED_ROLE
|
*/

describe('FR-15.1 & FR-15.2: Admin User Creation', function () {
    it('creates a user with hashed password and writes an audit log row', function () {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $admin->createToken('admin_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/admin/users', [
            'name' => 'Maria Santos',
            'email' => 'maria.santos@cuyotech.edu.ph',
            'role' => 'cashier',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'role' => 'cashier',
            ])
            ->assertJsonStructure(['id', 'role']);

        $createdUserId = $response->json('id');
        $createdUser = User::find($createdUserId);

        expect($createdUser)->not->toBeNull();
        expect($createdUser->name)->toBe('Maria Santos');
        expect($createdUser->email)->toBe('maria.santos@cuyotech.edu.ph');
        expect($createdUser->role)->toBe('cashier');
        expect($createdUser->is_active)->toBeTrue();
        expect(Hash::check('SecurePass123!', $createdUser->password))->toBeTrue();

        // Verify Audit Log (FR-15.5)
        $audit = AuditLog::where('target_type', 'user')
            ->where('target_id', $createdUserId)
            ->where('action', 'user.created')
            ->first();

        expect($audit)->not->toBeNull();
        expect($audit->actor_id)->toBe($admin->id);
        expect($audit->changes['email'])->toBe('maria.santos@cuyotech.edu.ph');
        expect($audit->changes['role'])->toBe('cashier');
    });

    it('requires department_id when role is department_staff', function () {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $admin->createToken('admin_token')->plainTextToken;

        // Attempt without department_id
        $responseNoDept = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/admin/users', [
            'name' => 'Dept Staff One',
            'email' => 'staff1@cuyotech.edu.ph',
            'role' => 'department_staff',
            'password' => 'SecurePass123!',
        ]);

        $responseNoDept->assertStatus(422)
            ->assertJson(['error' => 'VALIDATION_FAILED']);

        // With valid department_id
        $dept = Department::factory()->create();
        $responseWithDept = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/admin/users', [
            'name' => 'Dept Staff One',
            'email' => 'staff1@cuyotech.edu.ph',
            'role' => 'department_staff',
            'department_id' => $dept->id,
            'password' => 'SecurePass123!',
        ]);

        $responseWithDept->assertStatus(201)
            ->assertJson(['role' => 'department_staff']);

        $createdUser = User::find($responseWithDept->json('id'));
        expect($createdUser->department_id)->toBe($dept->id);
    });

    it('returns 409 EMAIL_TAKEN when email already exists', function () {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $admin->createToken('admin_token')->plainTextToken;

        User::factory()->create(['email' => 'existing@cuyotech.edu.ph']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/admin/users', [
            'name' => 'Duplicate Attempt',
            'email' => 'existing@cuyotech.edu.ph',
            'role' => 'registrar',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(409)
            ->assertExactJson(['error' => 'EMAIL_TAKEN']);
    });

    it('returns 403 UNAUTHORIZED_ROLE when non-admin attempts to create a user', function () {
        $registrar = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $token = $registrar->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->postJson('/api/admin/users', [
            'name' => 'Hacker Attempt',
            'email' => 'hacker@cuyotech.edu.ph',
            'role' => 'admin',
            'password' => 'SecurePass123!',
        ]);

        $response->assertStatus(403)
            ->assertExactJson(['error' => 'UNAUTHORIZED_ROLE']);
    });
});

describe('FR-15.3 – FR-15.6: Admin User Update & Live Immediate Deactivation', function () {
    it('updates user role and department_id, generating audit log', function () {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $admin->createToken('admin_token')->plainTextToken;

        $user = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $dept = Department::factory()->create();

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson("/api/admin/users/{$user->id}", [
            'role' => 'department_staff',
            'department_id' => $dept->id,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'role' => 'department_staff',
                'is_active' => true,
            ]);

        $user->refresh();
        expect($user->role)->toBe('department_staff');
        expect($user->department_id)->toBe($dept->id);

        // Audit log check
        $audit = AuditLog::where('target_type', 'user')
            ->where('target_id', $user->id)
            ->where('action', 'user.updated')
            ->first();

        expect($audit)->not->toBeNull();
        expect($audit->actor_id)->toBe($admin->id);
        expect($audit->changes['before']['role'])->toBe('cashier');
        expect($audit->changes['after']['role'])->toBe('department_staff');
    });

    it('returns 404 USER_NOT_FOUND when updating non-existent user', function () {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $token = $admin->createToken('admin_token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->patchJson('/api/admin/users/999999', [
            'is_active' => false,
        ]);

        $response->assertStatus(404)
            ->assertExactJson(['error' => 'USER_NOT_FOUND']);
    });

    it('immediately blocks a deactivated user on their very next request with an existing valid token', function () {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $adminToken = $admin->createToken('admin_token')->plainTextToken;

        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $cashierToken = $cashier->createToken('cashier_token')->plainTextToken;

        // 1. Cashier can perform action initially (e.g. invalid amount test gives 400, proving token is accepted)
        $initialRes = $this->withHeaders([
            'Authorization' => 'Bearer '.$cashierToken,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => 1,
            'amount_centavos' => 0,
            'payment_type' => 'tuition',
        ]);
        expect($initialRes->status())->toBe(400); // Token authorized, business validation hit

        // 2. Admin deactivates cashier
        $deactivateRes = $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
            'Accept' => 'application/json',
        ])->patchJson("/api/admin/users/{$cashier->id}", [
            'is_active' => false,
        ]);
        $deactivateRes->assertStatus(200)
            ->assertJson([
                'id' => $cashier->id,
                'is_active' => false,
            ]);

        // Verify user still exists in database (never hard-deleted, FR-15)
        $cashier->refresh();
        expect($cashier->is_active)->toBeFalse();

        // 3. Cashier's very next request with the exact same still-valid token immediately returns 403 ACCOUNT_DEACTIVATED
        $nextRes = $this->withHeaders([
            'Authorization' => 'Bearer '.$cashierToken,
            'Accept' => 'application/json',
        ])->postJson('/api/cashier/payments', [
            'student_id' => 1,
            'amount_centavos' => 1000,
        ]);

        $nextRes->assertStatus(403)
            ->assertExactJson(['error' => 'ACCOUNT_DEACTIVATED']);
    });
});
