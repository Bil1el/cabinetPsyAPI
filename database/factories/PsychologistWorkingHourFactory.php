<?php

namespace Database\Factories;

use App\Enums\DayOfWeek;
use App\Enums\WorkingHoursMode;
use App\Models\Psychologist;
use App\Models\PsychologistWorkingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PsychologistWorkingHour> */
class PsychologistWorkingHourFactory extends Factory
{
    public function definition(): array
    {
        return [
            'psychologist_id' => Psychologist::factory(),
            'day_of_week' => DayOfWeek::MONDAY,
            'starts_at' => '09:00',
            'ends_at' => '17:00',
            'mode' => WorkingHoursMode::BOTH,
            'is_active' => true,
        ];
    }
}
