<?php

namespace Database\Factories;

use App\Models\DocumentRequest;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentRequest>
 */
class DocumentRequestFactory extends Factory
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
            'type' => fake()->randomElement(['TOR', 'Diploma', 'Good Moral']),
            'purpose' => fake()->sentence(),
            'status' => 'pending',
            'processed_by' => null,
        ];
    }
}
