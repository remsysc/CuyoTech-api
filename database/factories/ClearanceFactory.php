<?php

namespace Database\Factories;

use App\Models\Clearance;
use App\Models\Department;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clearance>
 */
class ClearanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'student_id' => Student::factory(),
            'department_id' => Department::factory(),
            'school_year' => '2026-2027',
            'semester' => fake()->numberBetween(1, 3),
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'remarks' => null,
        ];
    }
}
