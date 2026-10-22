<?php

namespace Database\Factories;

use App\Models\CashierQueue;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashierQueue>
 */
class CashierQueueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $seq = fake()->unique()->numberBetween(1, 999);

        return [
            'queue_date' => now()->toDateString(),
            'queue_number' => 'C-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            'sequence_number' => $seq,
            'student_id' => Student::factory(),
            'amount_centavos' => 250000,
            'payment_type' => 'tuition',
            'status' => 'waiting',
            'is_present' => false,
            'checked_in_at' => null,
            'called_at' => null,
            'completed_at' => null,
            'called_by' => null,
            'or_number' => null,
        ];
    }

    public function present(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_present' => true,
            'checked_in_at' => now(),
        ]);
    }

    public function serving(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'serving',
            'is_present' => true,
            'checked_in_at' => now()->subMinutes(5),
            'called_at' => now(),
        ]);
    }
}
