<?php

use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;

/*
|--------------------------------------------------------------------------
| Tier 4: Real-World Application Scenarios E2E Tests
|--------------------------------------------------------------------------
|
| Realistic end-to-end user workflows mirroring authentic university
| operations over time:
| - Scenario 1: Complete student semester lifecycle (Enrollment -> Subjects
|               -> Installment 1 -> Partial Grading -> Installment 2
|               -> Balance Settled -> Final Grading -> Grade Slip)
| - Scenario 2: Grade revision lifecycle (Failing grade -> Removal exam -> Grade update)
| - Scenario 3: Multi-fee payment settlement & advance credit balance handling
| - Scenario 4: Multi-student ledger accounting and privacy segregation
| - Scenario 5: Multi-term progression lifecycle (Sem 1 -> Sem 2)
|
*/

describe('Tier 4: Real-World Application Scenarios', function () {
    it('scenario 1: complete student semester academic and financial lifecycle', function () {
        // -------------------------------------------------------------
        // Setup Actors
        // -------------------------------------------------------------
        $studentUser = User::factory()->create([
            'name' => 'Juan Dela Cruz',
            'role' => 'student',
            'is_active' => true,
        ]);
        $student = Student::factory()->create([
            'user_id' => $studentUser->id,
            'student_number' => '2026-0001',
            'balance_centavos' => 0,
        ]);
        $studentToken = $studentUser->createToken('student_token')->plainTextToken;

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('registrar_token')->plainTextToken;

        $cashierUser = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $cashierToken = $cashierUser->createToken('cashier_token')->plainTextToken;

        $dept = Department::factory()->create(['code' => 'CCS', 'name' => 'College of Computer Studies']);

        $prog1 = Course::factory()->create(['department_id' => $dept->id, 'code' => 'CS101', 'title' => 'Programming 1', 'units' => 3]);
        $discrete = Course::factory()->create(['department_id' => $dept->id, 'code' => 'CS102', 'title' => 'Discrete Structures', 'units' => 3]);
        $pe1 = Course::factory()->create(['department_id' => $dept->id, 'code' => 'PE101', 'title' => 'Physical Education 1', 'units' => 2]);

        $term = ['school_year' => '2026-2027', 'semester' => 1];

        // -------------------------------------------------------------
        // Step 1: Student checks initial profile
        // -------------------------------------------------------------
        $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/profile')
            ->assertStatus(200)
            ->assertJson([
                'student_number' => '2026-0001',
                'name' => 'Juan Dela Cruz',
                'status' => 'active',
            ]);

        expect($student->fresh()->balance_centavos)->toBe(0);

        // -------------------------------------------------------------
        // Step 2: Registrar enrolls student in 3 subjects (3 + 3 + 2 = 8 units)
        // Rate = 50,000 centavos/unit -> Total Tuition = 400,000 centavos
        // -------------------------------------------------------------
        $res1 = $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', array_merge($term, ['student_id' => $student->id, 'course_id' => $prog1->id]))
            ->assertStatus(201);
        $prog1EnrollmentId = $res1->json('id');

        $res2 = $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', array_merge($term, ['student_id' => $student->id, 'course_id' => $discrete->id]))
            ->assertStatus(201);
        $discreteEnrollmentId = $res2->json('id');

        $res3 = $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', array_merge($term, ['student_id' => $student->id, 'course_id' => $pe1->id]))
            ->assertStatus(201);
        $peEnrollmentId = $res3->json('id');

        expect($student->fresh()->balance_centavos)->toBe(400000);

        // -------------------------------------------------------------
        // Step 3: Student views subjects: 3 subjects enrolled
        // -------------------------------------------------------------
        $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/subjects?school_year=2026-2027&semester=1')
            ->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJson([
                ['course_code' => 'CS101', 'status' => 'enrolled'],
                ['course_code' => 'CS102', 'status' => 'enrolled'],
                ['course_code' => 'PE101', 'status' => 'enrolled'],
            ]);

        // -------------------------------------------------------------
        // Step 4: Student views grades: 0 completed
        // -------------------------------------------------------------
        $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/grades?school_year=2026-2027&semester=1')
            ->assertStatus(200)
            ->assertExactJson([]);

        // -------------------------------------------------------------
        // Step 5: Cashier accepts 1st Installment payment: 200,000 centavos
        // -------------------------------------------------------------
        $payRes1 = $this->withHeaders(['Authorization' => 'Bearer '.$cashierToken, 'Accept' => 'application/json'])
            ->postJson('/api/cashier/payments', [
                'student_id' => $student->id,
                'amount_centavos' => 200000,
                'payment_type' => 'tuition',
            ])->assertStatus(201);

        $orNumber1 = $payRes1->json('or_number');
        expect($student->fresh()->balance_centavos)->toBe(200000);

        // Verify receipt 1 is viewable
        $this->withHeaders(['Authorization' => 'Bearer '.$studentToken])
            ->get("/receipts/{$orNumber1}")
            ->assertStatus(200);

        // -------------------------------------------------------------
        // Step 6: Midterm grading: Registrar grades CS101 (1.50) & CS102 (2.00)
        // -------------------------------------------------------------
        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->patchJson("/api/registrar/enrollments/{$prog1EnrollmentId}/grade", ['grade' => 1.50])
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->patchJson("/api/registrar/enrollments/{$discreteEnrollmentId}/grade", ['grade' => 2.00])
            ->assertStatus(200);

        // -------------------------------------------------------------
        // Step 7: Student views grades: 2 subjects appear, PE excluded
        // -------------------------------------------------------------
        $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/grades?school_year=2026-2027&semester=1')
            ->assertStatus(200)
            ->assertJsonCount(2)
            ->assertJson([
                ['course_code' => 'CS101'],
                ['course_code' => 'CS102'],
            ])
            ->assertJsonMissing(['course_code' => 'PE101']);

        // -------------------------------------------------------------
        // Step 8: Cashier accepts 2nd Installment payment: 200,000 centavos
        // Balance becomes 0
        // -------------------------------------------------------------
        $payRes2 = $this->withHeaders(['Authorization' => 'Bearer '.$cashierToken, 'Accept' => 'application/json'])
            ->postJson('/api/cashier/payments', [
                'student_id' => $student->id,
                'amount_centavos' => 200000,
                'payment_type' => 'tuition',
            ])->assertStatus(201);

        $orNumber2 = $payRes2->json('or_number');
        expect($student->fresh()->balance_centavos)->toBe(0);

        // Verify receipt 2 is viewable
        $this->withHeaders(['Authorization' => 'Bearer '.$studentToken])
            ->get("/receipts/{$orNumber2}")
            ->assertStatus(200);

        // -------------------------------------------------------------
        // Step 9: Finals grading: Registrar grades PE101 (1.25)
        // -------------------------------------------------------------
        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->patchJson("/api/registrar/enrollments/{$peEnrollmentId}/grade", ['grade' => 1.25])
            ->assertStatus(200);

        // -------------------------------------------------------------
        // Step 10: Final grade slip: all 3 subjects completed
        // -------------------------------------------------------------
        $finalGrades = $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $finalGrades->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJson([
                ['course_code' => 'CS101'],
                ['course_code' => 'CS102'],
                ['course_code' => 'PE101'],
            ]);

        $finalSubjects = $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/subjects?school_year=2026-2027&semester=1');

        $finalSubjects->assertStatus(200)
            ->assertJsonCount(3)
            ->assertJson([
                ['course_code' => 'CS101', 'status' => 'completed'],
                ['course_code' => 'CS102', 'status' => 'completed'],
                ['course_code' => 'PE101', 'status' => 'completed'],
            ]);
    });

    it('scenario 2: grade revision and correction lifecycle (failing to removal exam passing)', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id]);
        $studentToken = $studentUser->createToken('student_token')->plainTextToken;

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('registrar_token')->plainTextToken;

        $course = Course::factory()->create(['code' => 'MATH101', 'title' => 'Calculus 1', 'units' => 4]);

        // 1. Enrollment
        $enrollResponse = $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'school_year' => '2026-2027',
                'semester' => 1,
            ])->assertStatus(201);

        $enrollmentId = $enrollResponse->json('id');

        // 2. Registrar records initial failing grade: 5.00
        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->patchJson("/api/registrar/enrollments/{$enrollmentId}/grade", [
                'grade' => 5.00,
            ])->assertStatus(200);

        // 3. Student views grade: 5.00
        $gradesBefore = $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $gradesBefore->assertStatus(200)->assertJsonCount(1);
        expect((float) $gradesBefore->json('0.grade'))->toBe(5.0);

        // 4. Student passes removal exam -> Registrar updates grade to 3.00 (last-write-wins)
        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->patchJson("/api/registrar/enrollments/{$enrollmentId}/grade", [
                'grade' => 3.00,
            ])->assertStatus(200);

        // 5. Student views updated grade: 3.00
        $gradesAfter = $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/grades?school_year=2026-2027&semester=1');

        $gradesAfter->assertStatus(200)->assertJsonCount(1);
        expect((float) $gradesAfter->json('0.grade'))->toBe(3.0);
    });

    it('scenario 3: multi-fee payment settlement and advance credit balance handling', function () {
        $studentUser = User::factory()->create(['role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id, 'balance_centavos' => 0]);

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('registrar_token')->plainTextToken;

        $cashierUser = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $cashierToken = $cashierUser->createToken('cashier_token')->plainTextToken;

        $course1 = Course::factory()->create(['units' => 3]);
        $course2 = Course::factory()->create(['units' => 3]);

        // 1. Enroll Course 1 (3 units * 50,000 = 150,000 centavos)
        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', [
                'student_id' => $student->id,
                'course_id' => $course1->id,
                'school_year' => '2026-2027',
                'semester' => 1,
            ])->assertStatus(201);

        expect($student->fresh()->balance_centavos)->toBe(150000);

        // 2. Pay 100,000 tuition -> balance = 50,000
        $this->withHeaders(['Authorization' => 'Bearer '.$cashierToken, 'Accept' => 'application/json'])
            ->postJson('/api/cashier/payments', [
                'student_id' => $student->id,
                'amount_centavos' => 100000,
                'payment_type' => 'tuition',
            ])->assertStatus(201);

        expect($student->fresh()->balance_centavos)->toBe(50000);

        // 3. Overpay with 100,000 tuition -> balance becomes -50,000 (advance credit)
        $this->withHeaders(['Authorization' => 'Bearer '.$cashierToken, 'Accept' => 'application/json'])
            ->postJson('/api/cashier/payments', [
                'student_id' => $student->id,
                'amount_centavos' => 100000,
                'payment_type' => 'tuition',
            ])->assertStatus(201);

        expect($student->fresh()->balance_centavos)->toBe(-50000);

        // 4. Enroll Course 2 (3 units = 150,000) -> balance becomes 100,000
        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', [
                'student_id' => $student->id,
                'course_id' => $course2->id,
                'school_year' => '2026-2027',
                'semester' => 1,
            ])->assertStatus(201);

        expect($student->fresh()->balance_centavos)->toBe(100000);

        // 5. Pay 75,000 misc_fee -> balance becomes 25,000
        $this->withHeaders(['Authorization' => 'Bearer '.$cashierToken, 'Accept' => 'application/json'])
            ->postJson('/api/cashier/payments', [
                'student_id' => $student->id,
                'amount_centavos' => 75000,
                'payment_type' => 'misc_fee',
            ])->assertStatus(201);

        expect($student->fresh()->balance_centavos)->toBe(25000);

        // 6. Pay 25,000 document_fee -> balance becomes 0
        $this->withHeaders(['Authorization' => 'Bearer '.$cashierToken, 'Accept' => 'application/json'])
            ->postJson('/api/cashier/payments', [
                'student_id' => $student->id,
                'amount_centavos' => 25000,
                'payment_type' => 'document_fee',
            ])->assertStatus(201);

        expect($student->fresh()->balance_centavos)->toBe(0);

        // Total payments recorded: 4
        expect(Payment::where('student_id', $student->id)->count())->toBe(4);
    });

    it('scenario 4: multi-student ledger accounting and data segregation', function () {
        $studentUserA = User::factory()->create(['name' => 'Alice Santos', 'role' => 'student', 'is_active' => true]);
        $studentA = Student::factory()->create(['user_id' => $studentUserA->id, 'balance_centavos' => 0]);
        $tokenA = $studentUserA->createToken('token_a')->plainTextToken;

        $studentUserB = User::factory()->create(['name' => 'Bob Reyes', 'role' => 'student', 'is_active' => true]);
        $studentB = Student::factory()->create(['user_id' => $studentUserB->id, 'balance_centavos' => 0]);
        $tokenB = $studentUserB->createToken('token_b')->plainTextToken;

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('registrar_token')->plainTextToken;

        $cashierUser = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $cashierToken = $cashierUser->createToken('cashier_token')->plainTextToken;

        $course3Units = Course::factory()->create(['code' => 'CS101', 'units' => 3]);
        $course6Units = Course::factory()->create(['code' => 'CS102', 'units' => 6]);

        // Registrar enrolls Student A in 3 units (150,000)
        $resA = $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', [
                'student_id' => $studentA->id,
                'course_id' => $course3Units->id,
                'school_year' => '2026-2027',
                'semester' => 1,
            ])->assertStatus(201);

        // Registrar enrolls Student B in 6 units (300,000)
        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', [
                'student_id' => $studentB->id,
                'course_id' => $course6Units->id,
                'school_year' => '2026-2027',
                'semester' => 1,
            ])->assertStatus(201);

        expect($studentA->fresh()->balance_centavos)->toBe(150000);
        expect($studentB->fresh()->balance_centavos)->toBe(300000);

        // Cashier collects 150,000 from Student A and 100,000 from Student B
        $this->withHeaders(['Authorization' => 'Bearer '.$cashierToken, 'Accept' => 'application/json'])
            ->postJson('/api/cashier/payments', [
                'student_id' => $studentA->id,
                'amount_centavos' => 150000,
                'payment_type' => 'tuition',
            ])->assertStatus(201);

        $this->withHeaders(['Authorization' => 'Bearer '.$cashierToken, 'Accept' => 'application/json'])
            ->postJson('/api/cashier/payments', [
                'student_id' => $studentB->id,
                'amount_centavos' => 100000,
                'payment_type' => 'tuition',
            ])->assertStatus(201);

        expect($studentA->fresh()->balance_centavos)->toBe(0);
        expect($studentB->fresh()->balance_centavos)->toBe(200000);

        // Grade Student A's enrollment
        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->patchJson("/api/registrar/enrollments/{$resA->json('id')}/grade", ['grade' => 1.25])
            ->assertStatus(200);

        // Verify Student A sees their grade, Student B sees no grades
        $this->withHeaders(['Authorization' => 'Bearer '.$tokenA, 'Accept' => 'application/json'])
            ->getJson('/api/student/grades?school_year=2026-2027&semester=1')
            ->assertStatus(200)->assertJsonCount(1);

        $this->withHeaders(['Authorization' => 'Bearer '.$tokenB, 'Accept' => 'application/json'])
            ->getJson('/api/student/grades?school_year=2026-2027&semester=1')
            ->assertStatus(200)->assertExactJson([]);
    });

    it('scenario 5: multi-term progression lifecycle from first year semester 1 to semester 2', function () {
        $studentUser = User::factory()->create(['name' => 'Danilo Ramos', 'role' => 'student', 'is_active' => true]);
        $student = Student::factory()->create(['user_id' => $studentUser->id, 'balance_centavos' => 0]);
        $studentToken = $studentUser->createToken('student_token')->plainTextToken;

        $registrarUser = User::factory()->create(['role' => 'registrar', 'is_active' => true]);
        $registrarToken = $registrarUser->createToken('registrar_token')->plainTextToken;

        $cashierUser = User::factory()->create(['role' => 'cashier', 'is_active' => true]);
        $cashierToken = $cashierUser->createToken('cashier_token')->plainTextToken;

        $course1 = Course::factory()->create(['code' => 'CS111', 'units' => 3]);
        $course2 = Course::factory()->create(['code' => 'CS112', 'units' => 3]);
        $course3 = Course::factory()->create(['code' => 'CS121', 'units' => 3]);

        // === Semester 1 ===
        // Enroll in CS111 and CS112 (6 units = 300,000)
        $resSem1A = $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', ['student_id' => $student->id, 'course_id' => $course1->id, 'school_year' => '2026-2027', 'semester' => 1])
            ->assertStatus(201);

        $resSem1B = $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', ['student_id' => $student->id, 'course_id' => $course2->id, 'school_year' => '2026-2027', 'semester' => 1])
            ->assertStatus(201);

        // Settle Sem 1 fees (300,000)
        $this->withHeaders(['Authorization' => 'Bearer '.$cashierToken, 'Accept' => 'application/json'])
            ->postJson('/api/cashier/payments', ['student_id' => $student->id, 'amount_centavos' => 300000, 'payment_type' => 'tuition'])
            ->assertStatus(201);

        // Grade Sem 1 courses
        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->patchJson("/api/registrar/enrollments/{$resSem1A->json('id')}/grade", ['grade' => 1.50])
            ->assertStatus(200);

        $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->patchJson("/api/registrar/enrollments/{$resSem1B->json('id')}/grade", ['grade' => 1.75])
            ->assertStatus(200);

        // === Semester 2 ===
        // Enroll in CS121 (3 units = 150,000)
        $resSem2 = $this->withHeaders(['Authorization' => 'Bearer '.$registrarToken, 'Accept' => 'application/json'])
            ->postJson('/api/registrar/enrollments', ['student_id' => $student->id, 'course_id' => $course3->id, 'school_year' => '2026-2027', 'semester' => 2])
            ->assertStatus(201);

        // Pay 100,000 towards Sem 2 tuition -> balance = 50,000
        $this->withHeaders(['Authorization' => 'Bearer '.$cashierToken, 'Accept' => 'application/json'])
            ->postJson('/api/cashier/payments', ['student_id' => $student->id, 'amount_centavos' => 100000, 'payment_type' => 'tuition'])
            ->assertStatus(201);

        expect($student->fresh()->balance_centavos)->toBe(50000);

        // === Term Verification ===
        // Sem 1 queries
        $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/subjects?school_year=2026-2027&semester=1')
            ->assertStatus(200)->assertJsonCount(2);

        $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/grades?school_year=2026-2027&semester=1')
            ->assertStatus(200)->assertJsonCount(2);

        // Sem 2 queries
        $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/subjects?school_year=2026-2027&semester=2')
            ->assertStatus(200)->assertJsonCount(1);

        $this->withHeaders(['Authorization' => 'Bearer '.$studentToken, 'Accept' => 'application/json'])
            ->getJson('/api/student/grades?school_year=2026-2027&semester=2')
            ->assertStatus(200)->assertExactJson([]);
    });
});
