<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Department;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $ccs = Department::where('code', 'CCS')->first();
        $coe = Department::where('code', 'COE')->first();
        $cbaa = Department::where('code', 'CBAA')->first();
        $coed = Department::where('code', 'COED')->first();
        $cas = Department::where('code', 'CAS')->first();
        $chas = Department::where('code', 'CHAS')->first();

        $courses = [
            // ==========================================
            // College of Computing Studies (BSCS & BSIT)
            // ==========================================
            [
                'department_id' => $ccs?->id,
                'code' => 'CS 321',
                'title' => 'Software Engineering 1',
                'units' => 3,
            ],
            [
                'department_id' => $ccs?->id,
                'code' => 'CS 322',
                'title' => 'Distributed Database Systems',
                'units' => 3,
            ],
            [
                'department_id' => $ccs?->id,
                'code' => 'CS 323',
                'title' => 'Operating Systems',
                'units' => 3,
            ],
            [
                'department_id' => $ccs?->id,
                'code' => 'CS 324',
                'title' => 'Computer Networks & Data Communications',
                'units' => 3,
            ],
            [
                'department_id' => $ccs?->id,
                'code' => 'CS 325',
                'title' => 'Web & Mobile Systems Development',
                'units' => 3,
            ],
            [
                'department_id' => $ccs?->id,
                'code' => 'CS 326',
                'title' => 'Research Methods in Computing',
                'units' => 3,
            ],
            [
                'department_id' => $ccs?->id,
                'code' => 'CS 311',
                'title' => 'Design & Analysis of Algorithms',
                'units' => 3,
            ],
            [
                'department_id' => $ccs?->id,
                'code' => 'CS 312',
                'title' => 'Object-Oriented Software Architecture',
                'units' => 3,
            ],
            [
                'department_id' => $ccs?->id,
                'code' => 'CS 313',
                'title' => 'Automata Theory & Formal Languages',
                'units' => 3,
            ],
            [
                'department_id' => $ccs?->id,
                'code' => 'IT 211',
                'title' => 'Information Assurance and Security 1',
                'units' => 3,
            ],
            [
                'department_id' => $ccs?->id,
                'code' => 'IT 312',
                'title' => 'System Integration and Architecture',
                'units' => 3,
            ],

            // ==========================================
            // College of Engineering (BSCE, BSEE, BSECE, BSME)
            // ==========================================
            [
                'department_id' => $coe?->id,
                'code' => 'ENG 201',
                'title' => 'Engineering Mechanics: Statics of Rigid Bodies',
                'units' => 3,
            ],
            [
                'department_id' => $coe?->id,
                'code' => 'ECE 311',
                'title' => 'Microprocessor Systems and Embedded Design',
                'units' => 4,
            ],
            [
                'department_id' => $coe?->id,
                'code' => 'CE 321',
                'title' => 'Structural Analysis and Design',
                'units' => 4,
            ],

            // ==========================================
            // College of Business, Accountancy & Admin (BSA, BSBA)
            // ==========================================
            [
                'department_id' => $cbaa?->id,
                'code' => 'ACT 201',
                'title' => 'Intermediate Financial Accounting 1',
                'units' => 3,
            ],
            [
                'department_id' => $cbaa?->id,
                'code' => 'BA 301',
                'title' => 'Strategic Management and Corporate Governance',
                'units' => 3,
            ],

            // ==========================================
            // College of Education (BEEd, BSEd)
            // ==========================================
            [
                'department_id' => $coed?->id,
                'code' => 'ED 201',
                'title' => 'Assessment of Learning and Educational Measurement',
                'units' => 3,
            ],

            // ==========================================
            // College of Arts and Sciences & General Education
            // ==========================================
            [
                'department_id' => $cas?->id,
                'code' => 'MATH 201',
                'title' => 'Probability and Advanced Statistical Inference',
                'units' => 3,
            ],
            [
                'department_id' => $cas?->id,
                'code' => 'GE 109',
                'title' => 'Philippine Popular Culture and Social Issues',
                'units' => 3,
            ],
            [
                'department_id' => $cas?->id,
                'code' => 'PSY 101',
                'title' => 'Introduction to Psychological Statistics',
                'units' => 3,
            ],

            // ==========================================
            // College of Health and Allied Sciences
            // ==========================================
            [
                'department_id' => $chas?->id,
                'code' => 'NUR 201',
                'title' => 'Human Anatomy and Physiology with Pathophysiology',
                'units' => 4,
            ],
        ];

        foreach ($courses as $c) {
            if ($c['department_id']) {
                Course::updateOrCreate(
                    ['code' => $c['code']],
                    [
                        'department_id' => $c['department_id'],
                        'title' => $c['title'],
                        'units' => $c['units'],
                    ]
                );
            }
        }
    }
}
