<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Psychologist;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addDays(fake()->numberBetween(1, 30))->setTime(10, 0);

        return [
            'psychologist_id' => fn () => Psychologist::factory()->create()->id,
            'patient_id' => fn (array $attributes) => Patient::factory()->create(['psychologist_id' => $attributes['psychologist_id']])->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'status' => AppointmentStatus::PENDING,
            'type' => AppointmentType::IN_PERSON,
        ];
    }
}
