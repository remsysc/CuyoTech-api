<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        Department::create(['name' => 'Computer Science', 'code' => 'CS']);
        Department::create(['name' => 'Information Technology', 'code' => 'IT']);
        Department::create(['name' => 'Engineering', 'code' => 'ENG']);
        Department::create(['name' => 'Library', 'code' => 'LIB']);
    }
}
