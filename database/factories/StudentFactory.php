<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => 'student']),
            'student_number' => 'SN-'.fake()->unique()->numerify('########'),
            'program' => fake()->randomElement(['BSCS', 'BSIT', 'BSCpE']),
            'year_level' => fake()->numberBetween(1, 4),
            'status' => 'active',
            'balance_centavos' => 0,
        ];
    }
}
