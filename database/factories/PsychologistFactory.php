<?php

namespace Database\Factories;

use App\Models\Psychologist;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Psychologist> */
class PsychologistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->phoneNumber(),
            'speciality' => 'Psychologie clinique',
            'bio' => fake()->paragraph(),
            'consultation_duration' => 60,
            'is_active' => true,
        ];
    }
}
