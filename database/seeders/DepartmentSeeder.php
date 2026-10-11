<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $departments = [
            // Core Academic Colleges / Departments (Pamantasan ng Cabuyao curriculum)
            [
                'code' => 'CCS',
                'name' => 'College of Computing Studies',
            ],
            [
                'code' => 'COE',
                'name' => 'College of Engineering',
            ],
            [
                'code' => 'CBAA',
                'name' => 'College of Business, Accountancy & Administration',
            ],
            [
                'code' => 'COED',
                'name' => 'College of Education',
            ],
            [
                'code' => 'CAS',
                'name' => 'College of Arts and Sciences',
            ],
            [
                'code' => 'CHAS',
                'name' => 'College of Health and Allied Sciences',
            ],
            // Institutional & Clearance Sign-off Offices
            [
                'code' => 'LIB',
                'name' => 'University Library',
            ],
            [
                'code' => 'OSA',
                'name' => 'Office of Student Affairs',
            ],
            [
                'code' => 'RO',
                'name' => 'Office of the University Registrar',
            ],
            [
                'code' => 'CASH',
                'name' => 'University Treasury & Cashier',
            ],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['code' => $dept['code']],
                ['name' => $dept['name']]
            );
        }
    }
}
