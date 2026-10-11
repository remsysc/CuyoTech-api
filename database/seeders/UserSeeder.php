<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $ccs = Department::where('code', 'CCS')->first();
        $lib = Department::where('code', 'LIB')->first();

        // 1. Super Administrator
        User::updateOrCreate(
            ['email' => 'admin@cuyotech.edu'],
            [
                'name' => 'Engr. Ferdinand Dela Cruz',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // 2. University Registrar
        User::updateOrCreate(
            ['email' => 'registrar@cuyotech.edu'],
            [
                'name' => 'Dr. Victoria Alcantara',
                'password' => Hash::make('password'),
                'role' => 'registrar',
                'is_active' => true,
            ]
        );

        // 3. University Cashier
        User::updateOrCreate(
            ['email' => 'cashier@cuyotech.edu'],
            [
                'name' => 'Atty. Manuel Soriano',
                'password' => Hash::make('password'),
                'role' => 'cashier',
                'is_active' => true,
            ]
        );

        // 4. Department Staff (College of Computing Studies)
        User::updateOrCreate(
            ['email' => 'staff@cuyotech.edu'],
            [
                'name' => 'Prof. Ernesto Bautista',
                'password' => Hash::make('password'),
                'role' => 'department_staff',
                'department_id' => $ccs?->id,
                'is_active' => true,
            ]
        );

        // 5. Additional Department Staff (University Library)
        User::updateOrCreate(
            ['email' => 'library@cuyotech.edu'],
            [
                'name' => 'Ms. Carmela Dizon',
                'password' => Hash::make('password'),
                'role' => 'department_staff',
                'department_id' => $lib?->id,
                'is_active' => true,
            ]
        );

        // 6. Complete Roster of Real-named Filipino Students across PnC Degree Programs
        $studentsData = [
            [
                'name' => 'Juan Miguel Dela Cruz',
                'email' => 'juan.delacruz@cuyotech.edu',
                'student_number' => '20260001',
                'program' => 'BS Computer Science',
                'year_level' => 3,
                'status' => 'active',
                'balance_centavos' => 0,
            ],
            [
                'name' => 'Maria Clara Santos',
                'email' => 'maria.santos@cuyotech.edu',
                'student_number' => '20260002',
                'program' => 'BS Information Technology',
                'year_level' => 3,
                'status' => 'active',
                'balance_centavos' => 75000, // ₱750.00
            ],
            [
                'name' => 'Crisostomo Ibarra',
                'email' => 'crisostomo.ibarra@cuyotech.edu',
                'student_number' => '20260003',
                'program' => 'BS Civil Engineering',
                'year_level' => 4,
                'status' => 'active',
                'balance_centavos' => 150000, // ₱1,500.00
            ],
            [
                'name' => 'Alyssa Nicole Reyes',
                'email' => 'alyssa.reyes@cuyotech.edu',
                'student_number' => '20260004',
                'program' => 'BS Accountancy',
                'year_level' => 2,
                'status' => 'active',
                'balance_centavos' => 0,
            ],
            [
                'name' => 'Joshua Gabriel Mendoza',
                'email' => 'joshua.mendoza@cuyotech.edu',
                'student_number' => '20260005',
                'program' => 'BS Computer Science',
                'year_level' => 3,
                'status' => 'active',
                'balance_centavos' => 0,
            ],
            [
                'name' => 'Bea Patricia Villanueva',
                'email' => 'bea.villanueva@cuyotech.edu',
                'student_number' => '20260006',
                'program' => 'BS Secondary Education (Major in Science)',
                'year_level' => 2,
                'status' => 'active',
                'balance_centavos' => 0,
            ],
            [
                'name' => 'Christian Dave Tan',
                'email' => 'christian.tan@cuyotech.edu',
                'student_number' => '20260007',
                'program' => 'BS Electronics Engineering',
                'year_level' => 4,
                'status' => 'active',
                'balance_centavos' => 225000, // ₱2,250.00
            ],
            [
                'name' => 'Danielle Sophia Ramos',
                'email' => 'danielle.ramos@cuyotech.edu',
                'student_number' => '20260008',
                'program' => 'BS Psychology',
                'year_level' => 1,
                'status' => 'active',
                'balance_centavos' => 0,
            ],
        ];

        foreach ($studentsData as $data) {
            $student = Student::where('student_number', $data['student_number'])->first();

            if ($student) {
                // Update existing student and their user
                $student->user->update([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make('password'),
                    'role' => 'student',
                    'is_active' => true,
                ]);

                $student->update([
                    'program' => $data['program'],
                    'year_level' => $data['year_level'],
                    'status' => $data['status'],
                    'balance_centavos' => $data['balance_centavos'],
                ]);
            } else {
                // Create new user and student
                $user = User::updateOrCreate(
                    ['email' => $data['email']],
                    [
                        'name' => $data['name'],
                        'password' => Hash::make('password'),
                        'role' => 'student',
                        'is_active' => true,
                    ]
                );

                Student::updateOrCreate(
                    ['student_number' => $data['student_number']],
                    [
                        'user_id' => $user->id,
                        'program' => $data['program'],
                        'year_level' => $data['year_level'],
                        'status' => $data['status'],
                        'balance_centavos' => $data['balance_centavos'],
                    ]
                );
            }
        }
    }
}
