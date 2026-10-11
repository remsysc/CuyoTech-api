<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            CourseSeeder::class,
            UserSeeder::class,
            AcademicRecordsSeeder::class,
        ]);
    }
}
