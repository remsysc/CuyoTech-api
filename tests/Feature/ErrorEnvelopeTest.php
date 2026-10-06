<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

describe('API Error Envelopes', function () {
    it('formats validation errors into standard { error: VALIDATION_FAILED, fields: { ... } } structure', function () {
        Route::post('/api/test-validation', function (Request $request) {
            $request->validate([
                'student_id' => 'required|integer',
                'amount' => 'required',
            ]);

            return response()->json(['ok' => true]);
        });

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->postJson('/api/test-validation', []);

        $response->assertStatus(422)
            ->assertJson([
                'error' => 'VALIDATION_FAILED',
            ])
            ->assertJsonStructure([
                'error',
                'fields' => ['student_id', 'amount'],
            ]);
    });

    it('formats unauthenticated requests into standard 401 error envelope', function () {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(401)
            ->assertExactJson([
                'error' => 'UNAUTHENTICATED',
            ]);
    });

    it('formats unauthorized requests into standard 403 error envelope', function () {
        $cashier = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $token = $cashier->createToken('token')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Accept' => 'application/json',
        ])->getJson('/api/student/profile');

        $response->assertStatus(403)
            ->assertExactJson([
                'error' => 'UNAUTHORIZED_ROLE',
            ]);
    });
});
