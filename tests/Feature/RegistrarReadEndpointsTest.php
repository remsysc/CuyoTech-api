<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->department = Department::factory()->create();
    $this->registrar = User::factory()->create([
        'role' => 'registrar',
    ]);
    $this->studentUser = User::factory()->create([
        'role' => 'student',
    ]);
    $this->student = Student::factory()->create([
        'user_id' => $this->studentUser->id,
        'student_number' => '20269999',
        'balance_centavos' => 50000,
    ]);
    $this->course = Course::factory()->create([
        'department_id' => $this->department->id,
        'code' => 'CS 101',
        'title' => 'Introduction to Computing',
        'units' => 3,
    ]);
});

test('registrar can list courses', function () {
    Sanctum::actingAs($this->registrar);

    $response = $this->getJson('/api/registrar/courses');

    $response->assertOk()
        ->assertJsonFragment([
            'code' => 'CS 101',
            'title' => 'Introduction to Computing',
            'units' => 3,
        ]);
});

test('registrar can list students with balance', function () {
    Sanctum::actingAs($this->registrar);

    $response = $this->getJson('/api/registrar/students');

    $response->assertOk()
        ->assertJsonFragment([
            'student_number' => '20269999',
            'balance_centavos' => 50000,
        ]);
});

test('registrar can list enrollments with optional filters', function () {
    Sanctum::actingAs($this->registrar);

    $enrollment = Enrollment::create([
        'student_id' => $this->student->id,
        'course_id' => $this->course->id,
        'school_year' => '2026-2027',
        'semester' => 1,
        'status' => 'enrolled',
    ]);

    $response = $this->getJson('/api/registrar/enrollments?school_year=2026-2027&semester=1');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $enrollment->id,
            'student_id' => $this->student->id,
            'course_code' => 'CS 101',
            'status' => 'enrolled',
        ]);
});

test('registrar can list document requests', function () {
    Sanctum::actingAs($this->registrar);

    $docReq = DocumentRequest::create([
        'student_id' => $this->student->id,
        'type' => 'tor',
        'purpose' => 'Employment application',
        'status' => 'pending',
        'requested_at' => now(),
    ]);

    $response = $this->getJson('/api/registrar/document-requests');

    $response->assertOk()
        ->assertJsonFragment([
            'id' => $docReq->id,
            'type' => 'tor',
            'purpose' => 'Employment application',
            'status' => 'pending',
        ]);
});

test('student cannot access registrar read endpoints', function () {
    Sanctum::actingAs($this->studentUser);

    $this->getJson('/api/registrar/courses')->assertForbidden();
    $this->getJson('/api/registrar/students')->assertForbidden();
    $this->getJson('/api/registrar/enrollments')->assertForbidden();
    $this->getJson('/api/registrar/document-requests')->assertForbidden();
});
