<?php

namespace Tests\Feature\Authorization;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Psychologist;
use App\Models\PsychologistAbsence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_psychologist_cannot_access_another_psychologists_resources(): void
    {
        $a = Psychologist::factory()->create();
        $b = Psychologist::factory()->create();
        $patient = Patient::factory()->create(['psychologist_id' => $b->id]);
        $appointment = Appointment::factory()->create(['psychologist_id' => $b->id, 'patient_id' => $patient->id]);
        $absence = PsychologistAbsence::factory()->create(['psychologist_id' => $b->id]);

        $this->actingAs($a->user)->getJson("/api/patients/{$patient->id}")->assertForbidden();
        $this->actingAs($a->user)->getJson("/api/absences/{$absence->id}")->assertForbidden();
        $this->actingAs($a->user)->getJson("/api/appointments/{$appointment->id}")->assertForbidden();
        $this->actingAs($b->user)->getJson("/api/patients/{$patient->id}")->assertOk();
    }

    public function test_an_admin_role_does_not_bypass_psychologist_ownership(): void
    {
        $a = Psychologist::factory()->create();
        $a->user->update(['role' => UserRole::ADMIN]);
        $b = Psychologist::factory()->create();
        $patient = Patient::factory()->create(['psychologist_id' => $b->id]);
        $appointment = Appointment::factory()->create(['psychologist_id' => $b->id, 'patient_id' => $patient->id]);
        $absence = PsychologistAbsence::factory()->create(['psychologist_id' => $b->id]);

        $this->actingAs($a->user)->getJson("/api/patients/{$patient->id}")->assertForbidden();
        $this->actingAs($a->user)->getJson("/api/appointments/{$appointment->id}")->assertForbidden();
        $this->actingAs($a->user)->getJson("/api/absences/{$absence->id}")->assertForbidden();
    }
}
