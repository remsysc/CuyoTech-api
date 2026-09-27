<?php

namespace Database\Factories;

use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
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
            'cashier_id' => User::factory()->state(['role' => 'cashier']),
            'amount_centavos' => fake()->numberBetween(100000, 500000),
            'or_number' => 'OR-'.fake()->unique()->numerify('########'),
        ];
    }
}
