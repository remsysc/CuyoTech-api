<?php

namespace Database\Seeders;

use App\Models\CashierQueue;
use App\Models\Clearance;
use App\Models\Course;
use App\Models\Department;
use App\Models\DocumentRequest;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class AcademicRecordsSeeder extends Seeder
{
    public function run(): void
    {
        $cashier = User::where('role', 'cashier')->first();
        $registrar = User::where('role', 'registrar')->first();
        $csStaff = User::where('email', 'staff@cuyotech.edu')->first();
        $libStaff = User::where('email', 'library@cuyotech.edu')->first();

        $students = Student::all()->keyBy('student_number');
        $courses = Course::all()->keyBy('code');
        $departments = Department::all()->keyBy('code');

        // =========================================================================
        // 1. Course Enrollments (Completed Grades & Active Terms)
        // =========================================================================
        // Juan Miguel Dela Cruz (20260001) - 3rd Year BSCS
        $juan = $students->get('20260001');
        if ($juan) {
            // Previous Term (AY 2026-2027, 1st Semester) - Finalized grades
            $completedCourses = [
                'CS 311' => 1.50, // Design & Analysis of Algorithms
                'CS 312' => 1.75, // Object-Oriented Software Architecture
                'CS 313' => 1.25, // Automata Theory & Formal Languages
                'MATH 201' => 1.75, // Probability & Advanced Statistics
            ];

            foreach ($completedCourses as $code => $grade) {
                if ($course = $courses->get($code)) {
                    Enrollment::updateOrCreate(
                        [
                            'student_id' => $juan->id,
                            'course_id' => $course->id,
                            'school_year' => '2026-2027',
                            'semester' => 1,
                        ],
                        [
                            'grade' => $grade,
                            'status' => 'completed',
                        ]
                    );
                }
            }

            // Current Term (AY 2026-2027, 2nd Semester) - Enrolled courses
            $activeCourses = [
                'CS 321', // Software Engineering 1
                'CS 322', // Distributed Database Systems
                'CS 323', // Operating Systems
                'CS 324', // Computer Networks
                'CS 325', // Web & Mobile Systems
                'CS 326', // Research Methods in Computing
                'GE 109', // Philippine Popular Culture
            ];

            foreach ($activeCourses as $code) {
                if ($course = $courses->get($code)) {
                    Enrollment::updateOrCreate(
                        [
                            'student_id' => $juan->id,
                            'course_id' => $course->id,
                            'school_year' => '2026-2027',
                            'semester' => 2,
                        ],
                        [
                            'grade' => null,
                            'status' => 'enrolled',
                        ]
                    );
                }
            }
        }

        // Maria Clara Santos (20260002) - 3rd Year BSIT
        $maria = $students->get('20260002');
        if ($maria) {
            $itCourses = ['CS 321', 'CS 322', 'IT 211', 'IT 312', 'GE 109'];
            foreach ($itCourses as $code) {
                if ($course = $courses->get($code)) {
                    Enrollment::updateOrCreate(
                        [
                            'student_id' => $maria->id,
                            'course_id' => $course->id,
                            'school_year' => '2026-2027',
                            'semester' => 2,
                        ],
                        [
                            'grade' => null,
                            'status' => 'enrolled',
                        ]
                    );
                }
            }
        }

        // Crisostomo Ibarra (20260003) - 4th Year BSCE
        $crisostomo = $students->get('20260003');
        if ($crisostomo) {
            $engCourses = ['ENG 201', 'CE 321', 'MATH 201'];
            foreach ($engCourses as $code) {
                if ($course = $courses->get($code)) {
                    Enrollment::updateOrCreate(
                        [
                            'student_id' => $crisostomo->id,
                            'course_id' => $course->id,
                            'school_year' => '2026-2027',
                            'semester' => 2,
                        ],
                        [
                            'grade' => null,
                            'status' => 'enrolled',
                        ]
                    );
                }
            }
        }

        // Joshua Gabriel Mendoza (20260005) - BSCS Classmate
        $joshua = $students->get('20260005');
        if ($joshua) {
            $classmateCourses = ['CS 321', 'CS 322', 'CS 323', 'CS 324'];
            foreach ($classmateCourses as $code) {
                if ($course = $courses->get($code)) {
                    Enrollment::updateOrCreate(
                        [
                            'student_id' => $joshua->id,
                            'course_id' => $course->id,
                            'school_year' => '2026-2027',
                            'semester' => 2,
                        ],
                        [
                            'grade' => null,
                            'status' => 'enrolled',
                        ]
                    );
                }
            }
        }

        // =========================================================================
        // 2. Department Clearances (FR-11 / FR-12)
        // =========================================================================
        if ($juan && $departments->isNotEmpty()) {
            $deptStanding = [
                'CCS' => ['status' => 'approved', 'remarks' => 'Cleared: All computer science laboratory work and capstone milestones verified.', 'reviewer' => $csStaff?->id],
                'LIB' => ['status' => 'approved', 'remarks' => 'Cleared: Zero overdue book loans and zero library obligations.', 'reviewer' => $libStaff?->id],
                'OSA' => ['status' => 'approved', 'remarks' => 'Cleared: Student handbook compliance and good moral record verified.', 'reviewer' => $registrar?->id],
                'CASH' => ['status' => 'approved', 'remarks' => 'Cleared: Tuition assessment fully settled. Account balance is ₱0.00.', 'reviewer' => $cashier?->id],
                'RO' => ['status' => 'approved', 'remarks' => 'Cleared: Form 137, PSA birth certificate, and admission credentials on file.', 'reviewer' => $registrar?->id],
            ];

            foreach ($deptStanding as $deptCode => $data) {
                if ($dept = $departments->get($deptCode)) {
                    Clearance::updateOrCreate(
                        [
                            'student_id' => $juan->id,
                            'department_id' => $dept->id,
                            'school_year' => '2026-2027',
                            'semester' => 2,
                        ],
                        [
                            'status' => $data['status'],
                            'remarks' => $data['remarks'],
                            'reviewed_by' => $data['reviewer'],
                            'reviewed_at' => Carbon::parse('2026-10-10 10:15:00'),
                        ]
                    );
                }
            }
        }

        // Maria Clara (pending laboratory fee receipt)
        if ($maria && ($ccs = $departments->get('CCS')) && ($cash = $departments->get('CASH'))) {
            Clearance::updateOrCreate(
                [
                    'student_id' => $maria->id,
                    'department_id' => $ccs->id,
                    'school_year' => '2026-2027',
                    'semester' => 2,
                ],
                [
                    'status' => 'pending',
                    'remarks' => 'Pending review of Cisco networking hardware inventory sign-off.',
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                ]
            );

            Clearance::updateOrCreate(
                [
                    'student_id' => $maria->id,
                    'department_id' => $cash->id,
                    'school_year' => '2026-2027',
                    'semester' => 2,
                ],
                [
                    'status' => 'denied',
                    'remarks' => 'Hold: Outstanding tuition installment balance of ₱750.00.',
                    'reviewed_by' => $cashier?->id,
                    'reviewed_at' => Carbon::parse('2026-10-09 14:30:00'),
                ]
            );
        }

        // =========================================================================
        // 3. Official Receipts & Payments (FR-8, FR-9, FR-10)
        // =========================================================================
        if ($juan && $cashier) {
            Payment::updateOrCreate(
                ['or_number' => 'OR-2026-00481920'],
                [
                    'student_id' => $juan->id,
                    'cashier_id' => $cashier->id,
                    'amount_centavos' => 525000, // ₱5,250.00 (21 units assessment)
                    'payment_type' => 'tuition',
                    'paid_at' => Carbon::parse('2026-10-05 14:20:00'),
                ]
            );

            Payment::updateOrCreate(
                ['or_number' => 'OR-2026-00512844'],
                [
                    'student_id' => $juan->id,
                    'cashier_id' => $cashier->id,
                    'amount_centavos' => 35000, // ₱350.00 misc/lab fee
                    'payment_type' => 'misc_fee',
                    'paid_at' => Carbon::parse('2026-10-06 09:15:00'),
                ]
            );
        }

        if ($maria && $cashier) {
            Payment::updateOrCreate(
                ['or_number' => 'OR-2026-00392811'],
                [
                    'student_id' => $maria->id,
                    'cashier_id' => $cashier->id,
                    'amount_centavos' => 15000, // ₱150.00 document fee
                    'payment_type' => 'document_fee',
                    'paid_at' => Carbon::parse('2026-10-08 11:30:00'),
                ]
            );
        }

        // =========================================================================
        // 4. Credential Document Requests (FR-18, FR-19)
        // =========================================================================
        if ($juan && $registrar) {
            DocumentRequest::updateOrCreate(
                [
                    'student_id' => $juan->id,
                    'type' => 'tor',
                    'purpose' => 'DOST-SEI Merit Scholarship Evaluation & Endorsement',
                ],
                [
                    'status' => 'ready',
                    'requested_at' => Carbon::parse('2026-10-08 08:30:00'),
                    'processed_by' => $registrar->id,
                ]
            );

            DocumentRequest::updateOrCreate(
                [
                    'student_id' => $juan->id,
                    'type' => 'certification',
                    'purpose' => 'Dean\'s Honor Roll Certificate for AY 2026-2027 1st Sem',
                ],
                [
                    'status' => 'released',
                    'requested_at' => Carbon::parse('2026-10-02 10:00:00'),
                    'released_at' => Carbon::parse('2026-10-04 15:45:00'),
                    'processed_by' => $registrar->id,
                ]
            );
        }

        if ($maria) {
            DocumentRequest::updateOrCreate(
                [
                    'student_id' => $maria->id,
                    'type' => 'cor',
                    'purpose' => 'Discount Concession Card / Student ID Re-validation',
                ],
                [
                    'status' => 'processing',
                    'requested_at' => Carbon::parse('2026-10-09 14:15:00'),
                    'processed_by' => null,
                ]
            );
        }

        // =========================================================================
        // 5. Cashier Window Queue & Student Presence (FR-21, FR-22)
        // =========================================================================
        $today = now()->toDateString();

        if ($juan && $cashier) {
            CashierQueue::updateOrCreate(
                ['queue_date' => $today, 'sequence_number' => 1],
                [
                    'queue_number' => 'C-001',
                    'student_id' => $juan->id,
                    'amount_centavos' => 35000,
                    'payment_type' => 'misc_fee',
                    'status' => 'completed',
                    'is_present' => true,
                    'checked_in_at' => now()->subMinutes(45),
                    'called_at' => now()->subMinutes(40),
                    'completed_at' => now()->subMinutes(35),
                    'called_by' => $cashier->id,
                    'or_number' => 'OR-2026-00512844',
                ]
            );
        }

        if ($maria && $cashier) {
            CashierQueue::updateOrCreate(
                ['queue_date' => $today, 'sequence_number' => 2],
                [
                    'queue_number' => 'C-002',
                    'student_id' => $maria->id,
                    'amount_centavos' => 75000,
                    'payment_type' => 'tuition',
                    'status' => 'serving',
                    'is_present' => true,
                    'checked_in_at' => now()->subMinutes(20),
                    'called_at' => now()->subMinutes(5),
                    'completed_at' => null,
                    'called_by' => $cashier->id,
                    'or_number' => null,
                ]
            );
        }

        if ($crisostomo) {
            CashierQueue::updateOrCreate(
                ['queue_date' => $today, 'sequence_number' => 3],
                [
                    'queue_number' => 'C-003',
                    'student_id' => $crisostomo->id,
                    'amount_centavos' => 250000,
                    'payment_type' => 'tuition',
                    'status' => 'waiting',
                    'is_present' => true,
                    'checked_in_at' => now()->subMinutes(10),
                    'called_at' => null,
                    'completed_at' => null,
                    'called_by' => null,
                    'or_number' => null,
                ]
            );
        }

        if ($joshua) {
            CashierQueue::updateOrCreate(
                ['queue_date' => $today, 'sequence_number' => 4],
                [
                    'queue_number' => 'C-004',
                    'student_id' => $joshua->id,
                    'amount_centavos' => 15000,
                    'payment_type' => 'document_fee',
                    'status' => 'waiting',
                    'is_present' => false,
                    'checked_in_at' => null,
                    'called_at' => null,
                    'completed_at' => null,
                    'called_by' => null,
                    'or_number' => null,
                ]
            );
        }
    }
}
