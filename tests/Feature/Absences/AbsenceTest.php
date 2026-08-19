<?php

namespace Tests\Feature\Absences;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Psychologist;
use App\Models\PsychologistAbsence;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_crud_validation_and_overlap_protection(): void
    {
        $psychologist = Psychologist::factory()->create();
        $start = CarbonImmutable::now()->addWeek()->setTime(9, 0);
        $response = $this->actingAs($psychologist->user)->postJson('/api/absences', ['starts_at' => $start->toISOString(), 'ends_at' => $start->addHours(2)->toISOString(), 'reason' => 'Formation'])->assertCreated();
        $id = $response->json('data.id');
        $this->actingAs($psychologist->user)->postJson('/api/absences', ['starts_at' => $start->addHour()->toISOString(), 'ends_at' => $start->addHours(3)->toISOString()])->assertConflict();
        $this->actingAs($psychologist->user)->patchJson("/api/absences/{$id}", ['reason' => 'Congé'])->assertOk()->assertJsonPath('data.reason', 'Congé');
        $this->actingAs($psychologist->user)->deleteJson("/api/absences/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('psychologist_absences', ['id' => $id]);
    }

    public function test_end_before_start_is_invalid(): void
    {
        $psychologist = Psychologist::factory()->create();
        $start = CarbonImmutable::now()->addWeek();
        $this->actingAs($psychologist->user)->postJson('/api/absences', ['starts_at' => $start->toISOString(), 'ends_at' => $start->subHour()->toISOString()])->assertUnprocessable();
        $this->actingAs($psychologist->user)->getJson('/api/absences?per_page=0')->assertUnprocessable();
    }

    public function test_blocking_appointments_reject_overlapping_absences_without_changes(): void
    {
        foreach ([AppointmentStatus::PENDING, AppointmentStatus::CONFIRMED] as $status) {
            $psychologist = Psychologist::factory()->create();
            $start = CarbonImmutable::now()->addWeek()->setTime(10, 0);
            $appointment = Appointment::factory()->create(['psychologist_id' => $psychologist->id, 'starts_at' => $start, 'ends_at' => $start->addHour(), 'status' => $status]);

            $this->actingAs($psychologist->user)->postJson('/api/absences', ['starts_at' => $start->addMinutes(30)->toISOString(), 'ends_at' => $start->addHours(2)->toISOString()])
                ->assertConflict()->assertJsonPath('code', 'ABSENCE_CONFLICT');

            $this->assertDatabaseCount('psychologist_absences', 0);
            $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => $status->value]);
        }
    }

    public function test_boundary_cancelled_past_and_other_psychologist_appointments_do_not_block_absence(): void
    {
        $a = Psychologist::factory()->create();
        $b = Psychologist::factory()->create();
        $start = CarbonImmutable::now()->addWeek()->setTime(10, 0);
        Appointment::factory()->create(['psychologist_id' => $a->id, 'starts_at' => $start, 'ends_at' => $start->addHour(), 'status' => AppointmentStatus::CANCELLED]);
        Appointment::factory()->create(['psychologist_id' => $a->id, 'starts_at' => $start->subWeek(), 'ends_at' => $start->subWeek()->addHour(), 'status' => AppointmentStatus::PENDING]);
        Appointment::factory()->create(['psychologist_id' => $a->id, 'starts_at' => $start, 'ends_at' => $start->addHour(), 'status' => AppointmentStatus::PENDING]);

        $this->actingAs($a->user)->postJson('/api/absences', ['starts_at' => $start->addHour()->toISOString(), 'ends_at' => $start->addHours(2)->toISOString()])->assertCreated();
        $this->actingAs($b->user)->postJson('/api/absences', ['starts_at' => $start->toISOString(), 'ends_at' => $start->addHour()->toISOString()])->assertCreated();
    }

    public function test_historical_blocking_appointments_do_not_block_historical_absence_creation_or_update(): void
    {
        foreach ([AppointmentStatus::PENDING, AppointmentStatus::CONFIRMED] as $status) {
            $psychologist = Psychologist::factory()->create();
            $start = CarbonImmutable::now()->subWeek()->setTime(10, 0);
            Appointment::factory()->create([
                'psychologist_id' => $psychologist->id,
                'starts_at' => $start,
                'ends_at' => $start->addHour(),
                'status' => $status,
            ]);

            $created = $this->actingAs($psychologist->user)->postJson('/api/absences', [
                'starts_at' => $start->toISOString(),
                'ends_at' => $start->addHour()->toISOString(),
            ])->assertCreated();

            $updatePsychologist = Psychologist::factory()->create();
            Appointment::factory()->create([
                'psychologist_id' => $updatePsychologist->id,
                'starts_at' => $start,
                'ends_at' => $start->addHour(),
                'status' => $status,
            ]);
            $absence = PsychologistAbsence::factory()->create([
                'psychologist_id' => $updatePsychologist->id,
                'starts_at' => $start->addHours(2),
                'ends_at' => $start->addHours(3),
            ]);

            $this->actingAs($updatePsychologist->user)->patchJson("/api/absences/{$absence->id}", [
                'starts_at' => $start->toISOString(),
                'ends_at' => $start->addHour()->toISOString(),
            ])->assertOk();

            $this->assertDatabaseHas('psychologist_absences', ['id' => $created->json('data.id')]);
            $this->assertDatabaseHas('psychologist_absences', ['id' => $absence->id]);
        }
    }

    public function test_conflicting_absence_update_preserves_original_absence(): void
    {
        $psychologist = Psychologist::factory()->create();
        $start = CarbonImmutable::now()->addWeek()->setTime(10, 0);
        $absence = PsychologistAbsence::factory()->create(['psychologist_id' => $psychologist->id, 'starts_at' => $start->addHours(3), 'ends_at' => $start->addHours(4), 'reason' => 'Original']);
        $appointment = Appointment::factory()->create(['psychologist_id' => $psychologist->id, 'starts_at' => $start, 'ends_at' => $start->addHour(), 'status' => AppointmentStatus::PENDING]);

        $this->actingAs($psychologist->user)->patchJson("/api/absences/{$absence->id}", ['starts_at' => $start->addMinutes(30)->toISOString(), 'ends_at' => $start->addHours(2)->toISOString()])
            ->assertConflict()->assertJsonPath('code', 'ABSENCE_CONFLICT');

        $this->assertDatabaseHas('psychologist_absences', ['id' => $absence->id, 'reason' => 'Original']);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => AppointmentStatus::PENDING->value]);
    }

    public function test_multi_day_absence_is_rejected_when_it_overlaps_a_blocking_appointment(): void
    {
        $psychologist = Psychologist::factory()->create();
        $start = CarbonImmutable::now()->addWeek()->startOfDay()->addHours(9);
        Appointment::factory()->create([
            'psychologist_id' => $psychologist->id,
            'starts_at' => $start->addDay(),
            'ends_at' => $start->addDay()->addHour(),
            'status' => AppointmentStatus::CONFIRMED,
        ]);

        $this->actingAs($psychologist->user)->postJson('/api/absences', [
            'starts_at' => $start->toISOString(),
            'ends_at' => $start->addDays(2)->toISOString(),
        ])->assertConflict()->assertJsonPath('code', 'ABSENCE_CONFLICT');

        $this->assertDatabaseCount('psychologist_absences', 0);
    }

    public function test_another_psychologist_cannot_update_an_absence(): void
    {
        $owner = Psychologist::factory()->create();
        $other = Psychologist::factory()->create();
        $absence = PsychologistAbsence::factory()->create(['psychologist_id' => $owner->id, 'reason' => 'Privé']);

        $this->actingAs($other->user)->patchJson("/api/absences/{$absence->id}", ['reason' => 'Tentative'])
            ->assertForbidden();

        $this->assertDatabaseHas('psychologist_absences', ['id' => $absence->id, 'reason' => 'Privé']);
    }
}
