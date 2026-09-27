<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $dept = Department::first();

        // Admin
        User::factory()->create([
            'name' => 'System Admin',
            'email' => 'admin@cuyotech.edu',
            'role' => 'admin',
        ]);

        // Registrar
        User::factory()->create([
            'name' => 'University Registrar',
            'email' => 'registrar@cuyotech.edu',
            'role' => 'registrar',
        ]);

        // Cashier
        User::factory()->create([
            'name' => 'Main Cashier',
            'email' => 'cashier@cuyotech.edu',
            'role' => 'cashier',
        ]);

        // Department Staff
        User::factory()->create([
            'name' => 'CS Staff',
            'email' => 'staff@cuyotech.edu',
            'role' => 'department_staff',
            'department_id' => $dept->id,
        ]);

        // 3 Students
        for ($i = 1; $i <= 3; $i++) {
            $user = User::factory()->create([
                'name' => "Student {$i}",
                'email' => "student{$i}@cuyotech.edu",
                'role' => 'student',
            ]);
            Student::factory()->create([
                'user_id' => $user->id,
                'student_number' => "2026000{$i}",
            ]);
        }
    }
}
