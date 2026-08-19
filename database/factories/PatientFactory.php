<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Psychologist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Patient> */
class PatientFactory extends Factory
{
    public function definition(): array
    {
        return [
            'psychologist_id' => Psychologist::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'birth_date' => fake()->optional()->dateTimeBetween('-80 years', '-18 years'),
        ];
    }
}
