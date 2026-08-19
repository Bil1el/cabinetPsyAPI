<?php

namespace Tests\Feature\Dashboard;

use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Psychologist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_are_scoped_to_current_psychologist(): void
    {
        $a = Psychologist::factory()->create();
        $b = Psychologist::factory()->create();
        $patient = Patient::factory()->create(['psychologist_id' => $a->id]);
        Appointment::factory()->create(['psychologist_id' => $a->id, 'patient_id' => $patient->id, 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2)]);
        Appointment::factory()->create(['psychologist_id' => $b->id]);
        $this->actingAs($a->user)->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.patientsCount', 1)->assertJsonCount(1, 'data.nextAppointments');
    }
}
