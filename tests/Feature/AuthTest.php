<?php

use App\Models\Student;
use App\Models\User;

it('allows a user to login with email', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
        'role' => 'admin',
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/login', [
        'student_number_or_email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'role', 'redirect']);
});

it('allows a student to login with student number', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
        'role' => 'student',
        'is_active' => true,
    ]);

    $student = Student::factory()->create([
        'user_id' => $user->id,
        'student_number' => '12345678',
    ]);

    $response = $this->postJson('/api/login', [
        'student_number_or_email' => '12345678',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'role', 'redirect']);
});

it('returns 401 on invalid credentials', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/login', [
        'student_number_or_email' => 'test@example.com',
        'password' => 'wrongpassword',
    ]);

    $response->assertStatus(401)
        ->assertJson(['error' => 'INVALID_CREDENTIALS']);
});

it('returns 403 when account is deactivated', function () {
    $user = User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password123'),
        'is_active' => false,
    ]);

    $response = $this->postJson('/api/login', [
        'student_number_or_email' => 'test@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(403)
        ->assertJson(['error' => 'ACCOUNT_DEACTIVATED']);
});

it('allows a user to logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('auth_token')->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/logout');

    $response->assertStatus(204);
    expect($user->tokens()->count())->toBe(0);
});
