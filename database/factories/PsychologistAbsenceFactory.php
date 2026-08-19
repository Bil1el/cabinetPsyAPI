<?php

namespace Database\Factories;

use App\Models\Psychologist;
use App\Models\PsychologistAbsence;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PsychologistAbsence> */
class PsychologistAbsenceFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(2, 30))->setTime(9, 0);

        return [
            'psychologist_id' => Psychologist::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'reason' => fake()->optional()->sentence(),
        ];
    }
}
