<?php

namespace Tests\Feature\Database;

use App\Enums\DayOfWeek;
use App\Models\Psychologist;
use App\Models\PsychologistWorkingHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DomainSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_tables_and_critical_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('appointments', [
            'psychologist_id', 'patient_id', 'starts_at', 'ends_at', 'status', 'type',
            'patient_message', 'cancellation_reason', 'confirmed_at', 'cancelled_at', 'completed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('patients', ['psychologist_id', 'first_name', 'last_name', 'email', 'phone', 'birth_date']));
        $this->assertTrue(Schema::hasTable('psychologist_working_hours'));
        $this->assertTrue(Schema::hasTable('psychologist_absences'));
    }

    public function test_a_psychologist_can_have_multiple_ranges_on_the_same_day(): void
    {
        $psychologist = Psychologist::factory()->create();

        PsychologistWorkingHour::factory()->create([
            'psychologist_id' => $psychologist->id,
            'day_of_week' => DayOfWeek::MONDAY,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);
        PsychologistWorkingHour::factory()->create([
            'psychologist_id' => $psychologist->id,
            'day_of_week' => DayOfWeek::MONDAY,
            'starts_at' => '14:00',
            'ends_at' => '18:00',
        ]);

        $this->assertCount(2, $psychologist->workingHours);
    }
}
