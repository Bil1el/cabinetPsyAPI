<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\DayOfWeek;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Psychologist;
use App\Models\PsychologistAbsence;
use App\Models\PsychologistWorkingHour;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $psychologists = collect([
            ['name' => 'Claire Martin', 'email' => 'claire@example.test', 'first_name' => 'Claire', 'last_name' => 'Martin'],
            ['name' => 'Julien Bernard', 'email' => 'julien@example.test', 'first_name' => 'Julien', 'last_name' => 'Bernard'],
        ])->map(function (array $data): Psychologist {
            $user = User::factory()->create(['name' => $data['name'], 'email' => $data['email']]);

            return Psychologist::factory()->create([
                'user_id' => $user->id,
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
            ]);
        });

        foreach ($psychologists as $psychologist) {
            $patients = Patient::factory()->count(6)->create(['psychologist_id' => $psychologist->id]);
            foreach ([DayOfWeek::MONDAY, DayOfWeek::TUESDAY, DayOfWeek::WEDNESDAY, DayOfWeek::THURSDAY, DayOfWeek::FRIDAY] as $day) {
                PsychologistWorkingHour::factory()->create(['psychologist_id' => $psychologist->id, 'day_of_week' => $day, 'starts_at' => '09:00', 'ends_at' => '12:00']);
                PsychologistWorkingHour::factory()->create(['psychologist_id' => $psychologist->id, 'day_of_week' => $day, 'starts_at' => '14:00', 'ends_at' => '18:00']);
            }

            PsychologistAbsence::factory()->create(['psychologist_id' => $psychologist->id]);

            foreach (AppointmentStatus::cases() as $offset => $status) {
                Appointment::factory()->create([
                    'psychologist_id' => $psychologist->id,
                    'patient_id' => $patients->random()->id,
                    'starts_at' => now()->addDays($offset + 1)->setTime(10, 0),
                    'ends_at' => now()->addDays($offset + 1)->setTime(11, 0),
                    'status' => $status,
                    'confirmed_at' => $status === AppointmentStatus::CONFIRMED ? now() : null,
                    'cancelled_at' => $status === AppointmentStatus::CANCELLED ? now() : null,
                    'completed_at' => $status === AppointmentStatus::COMPLETED ? now() : null,
                ]);
            }
        }
    }
}
