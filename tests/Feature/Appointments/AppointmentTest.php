<?php

namespace Tests\Feature\Appointments;

use App\DTOs\Appointment\StoreAppointmentDTO;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Psychologist;
use App\Models\PsychologistAbsence;
use App\Models\PsychologistWorkingHour;
use App\Services\AppointmentService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    use RefreshDatabase;

    private Psychologist $psychologist;

    private CarbonImmutable $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slot = CarbonImmutable::now()->next('Monday')->setTime(10, 0);
        $this->psychologist = Psychologist::factory()->create(['consultation_duration' => 60]);
        PsychologistWorkingHour::factory()->create(['psychologist_id' => $this->psychologist->id, 'day_of_week' => 'monday', 'starts_at' => '09:00', 'ends_at' => '12:00']);
    }

    private function payload(): array
    {
        return ['starts_at' => $this->slot->toISOString(), 'type' => 'in_person', 'patient' => ['first_name' => 'Alice', 'last_name' => 'Durand', 'email' => 'alice@example.test', 'phone' => '0600000000']];
    }

    public function test_valid_creation_calculates_end_on_server_and_prevents_overlap(): void
    {
        $this->actingAs($this->psychologist->user)->postJson('/api/appointments', $this->payload())->assertCreated()->assertJsonPath('data.endsAt', $this->slot->addHour()->toISOString());
        $this->actingAs($this->psychologist->user)->postJson('/api/appointments', $this->payload())->assertConflict()->assertJsonPath('code', 'SLOT_UNAVAILABLE');
    }

    public function test_private_booking_prohibits_client_supplied_psychologist_id(): void
    {
        $other = Psychologist::factory()->create();

        $this->actingAs($this->psychologist->user)
            ->postJson('/api/appointments', [...$this->payload(), 'psychologist_id' => $other->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('psychologist_id');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_public_and_private_booking_reject_off_grid_slots(): void
    {
        $offGrid = [...$this->payload(), 'starts_at' => $this->slot->addMinutes(17)->toISOString()];

        $this->postJson('/api/public/appointments', [...$offGrid, 'psychologist_id' => $this->psychologist->id])
            ->assertConflict()->assertJsonPath('code', 'SLOT_UNAVAILABLE');
        $this->actingAs($this->psychologist->user)->postJson('/api/appointments', $offGrid)
            ->assertConflict()->assertJsonPath('code', 'SLOT_UNAVAILABLE');
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_appointment_range_cannot_exceed_366_days(): void
    {
        $this->actingAs($this->psychologist->user)->getJson('/api/appointments?from=2026-01-01&to=2027-01-03')->assertUnprocessable();
    }

    public function test_appointment_range_includes_the_entire_end_date(): void
    {
        $included = Appointment::factory()->create([
            'psychologist_id' => $this->psychologist->id,
            'starts_at' => $this->slot->setTime(18, 30),
            'ends_at' => $this->slot->setTime(19, 30),
        ]);
        $excluded = Appointment::factory()->create([
            'psychologist_id' => $this->psychologist->id,
            'starts_at' => $this->slot->addDay()->setTime(9, 0),
            'ends_at' => $this->slot->addDay()->setTime(10, 0),
        ]);
        $date = $this->slot->toDateString();

        $this->actingAs($this->psychologist->user)
            ->getJson("/api/appointments?from={$date}&to={$date}")
            ->assertOk()
            ->assertJsonFragment(['id' => $included->id])
            ->assertJsonMissing(['id' => $excluded->id]);
    }

    public function test_outside_hours_absence_and_inactive_psychologist_are_rejected(): void
    {
        $outside = [...$this->payload(), 'starts_at' => $this->slot->setTime(13, 0)->toISOString()];
        $this->actingAs($this->psychologist->user)->postJson('/api/appointments', $outside)->assertConflict()->assertJsonPath('code', 'SLOT_UNAVAILABLE');
        PsychologistAbsence::factory()->create(['psychologist_id' => $this->psychologist->id, 'starts_at' => $this->slot->subMinutes(30), 'ends_at' => $this->slot->addMinutes(30)]);
        $this->actingAs($this->psychologist->user)->postJson('/api/appointments', $this->payload())->assertConflict()->assertJsonPath('code', 'SLOT_UNAVAILABLE');
        PsychologistAbsence::query()->delete();
        $this->psychologist->update(['is_active' => false]);
        $this->actingAs($this->psychologist->user)->postJson('/api/appointments', $this->payload())->assertConflict()->assertJsonPath('code', 'SLOT_UNAVAILABLE');
    }

    public function test_status_transitions_are_enforced(): void
    {
        $appointment = Appointment::factory()->create(['psychologist_id' => $this->psychologist->id, 'starts_at' => $this->slot, 'ends_at' => $this->slot->addHour()]);
        $this->actingAs($this->psychologist->user)->patchJson("/api/appointments/{$appointment->id}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->actingAs($this->psychologist->user)->patchJson("/api/appointments/{$appointment->id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');
        $this->actingAs($this->psychologist->user)->patchJson("/api/appointments/{$appointment->id}/confirm")
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_APPOINTMENT_TRANSITION');
    }

    public function test_pending_can_be_cancelled(): void
    {
        $appointment = Appointment::factory()->create(['psychologist_id' => $this->psychologist->id, 'status' => AppointmentStatus::PENDING]);
        $this->actingAs($this->psychologist->user)->patchJson("/api/appointments/{$appointment->id}/cancel", ['cancellation_reason' => 'Indisponible'])->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_public_booking_requires_patient_details_and_does_not_expose_them(): void
    {
        $payload = [...$this->payload(), 'psychologist_id' => $this->psychologist->id];
        $this->postJson('/api/public/appointments', $payload)->assertCreated()->assertJsonMissingPath('data.patient')->assertJsonPath('data.status', 'pending');

        $this->postJson('/api/public/appointments', ['psychologist_id' => $this->psychologist->id, 'patient_id' => 1, 'starts_at' => $this->slot->addHour()->toISOString(), 'type' => 'in_person'])->assertUnprocessable();
    }

    public function test_terminal_appointment_cannot_be_edited(): void
    {
        $appointment = Appointment::factory()->create(['psychologist_id' => $this->psychologist->id, 'status' => AppointmentStatus::CANCELLED]);
        $this->actingAs($this->psychologist->user)->patchJson("/api/appointments/{$appointment->id}", ['starts_at' => $this->slot->toISOString()])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'INVALID_APPOINTMENT_TRANSITION');
    }

    public function test_psychologist_can_reassign_an_appointment_to_their_own_patient(): void
    {
        $originalPatient = Patient::factory()->create(['psychologist_id' => $this->psychologist->id]);
        $replacementPatient = Patient::factory()->create(['psychologist_id' => $this->psychologist->id]);
        $appointment = Appointment::factory()->create([
            'psychologist_id' => $this->psychologist->id,
            'patient_id' => $originalPatient->id,
            'starts_at' => $this->slot,
            'ends_at' => $this->slot->addHour(),
        ]);

        $this->actingAs($this->psychologist->user)
            ->patchJson("/api/appointments/{$appointment->id}", [
                'patient_id' => $replacementPatient->id,
                'starts_at' => $this->slot->addHour()->toISOString(),
            ])
            ->assertOk()
            ->assertJsonPath('data.patientId', $replacementPatient->id)
            ->assertJsonPath('data.startsAt', $this->slot->addHour()->toISOString());

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'psychologist_id' => $this->psychologist->id,
            'patient_id' => $replacementPatient->id,
        ]);
    }

    public function test_psychologist_cannot_reassign_an_appointment_to_another_psychologists_patient(): void
    {
        $originalPatient = Patient::factory()->create(['psychologist_id' => $this->psychologist->id]);
        $otherPsychologist = Psychologist::factory()->create();
        $privatePatient = Patient::factory()->create([
            'psychologist_id' => $otherPsychologist->id,
            'first_name' => 'Private',
            'last_name' => 'Patient',
            'email' => 'private.patient@example.test',
        ]);
        $appointment = Appointment::factory()->create([
            'psychologist_id' => $this->psychologist->id,
            'patient_id' => $originalPatient->id,
            'starts_at' => $this->slot,
            'ends_at' => $this->slot->addHour(),
        ]);

        $this->actingAs($this->psychologist->user)
            ->patchJson("/api/appointments/{$appointment->id}", ['patient_id' => $privatePatient->id])
            ->assertForbidden()
            ->assertJsonMissingPath('data.patient')
            ->assertJsonMissing(['email' => $privatePatient->email, 'phone' => $privatePatient->phone]);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'psychologist_id' => $this->psychologist->id,
            'patient_id' => $originalPatient->id,
        ]);
    }

    public function test_appointment_factory_assigns_a_patient_of_the_same_psychologist_by_default(): void
    {
        $appointment = Appointment::factory()->create();

        $this->assertSame($appointment->psychologist_id, $appointment->patient->psychologist_id);
    }

    public function test_service_cannot_create_an_appointment_with_another_psychologists_patient(): void
    {
        $otherPsychologist = Psychologist::factory()->create();
        $otherPatient = Patient::factory()->create(['psychologist_id' => $otherPsychologist->id]);

        try {
            app(AppointmentService::class)->create($this->psychologist, StoreAppointmentDTO::fromArray([
                'patient_id' => $otherPatient->id,
                'starts_at' => $this->slot->toISOString(),
                'type' => 'in_person',
            ]));
            $this->fail('Le service doit refuser un patient d’un autre psychologue.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('appointments', 0);
        }
    }

    public function test_psychologist_can_create_an_appointment_with_their_own_patient(): void
    {
        $patient = Patient::factory()->create(['psychologist_id' => $this->psychologist->id]);

        $this->actingAs($this->psychologist->user)
            ->postJson('/api/appointments', [
                'patient_id' => $patient->id,
                'starts_at' => $this->slot->toISOString(),
                'type' => 'in_person',
            ])
            ->assertCreated()
            ->assertJsonPath('data.patientId', $patient->id);
    }

    public function test_private_appointment_resource_exposes_no_psychologist_account_contact_data(): void
    {
        $appointment = Appointment::factory()->create([
            'psychologist_id' => $this->psychologist->id,
            'starts_at' => $this->slot,
            'ends_at' => $this->slot->addHour(),
        ]);

        $this->actingAs($this->psychologist->user)
            ->getJson("/api/appointments/{$appointment->id}")
            ->assertOk()
            ->assertJsonPath('data.psychologist.id', $this->psychologist->id)
            ->assertJsonMissingPath('data.psychologist.email')
            ->assertJsonMissingPath('data.psychologist.phone')
            ->assertJsonMissingPath('data.psychologist.userId');
    }

    public function test_public_booking_reuses_only_the_same_normalized_patient_identity_without_leaking_it(): void
    {
        $first = [...$this->payload(), 'psychologist_id' => $this->psychologist->id];
        $first['patient']['email'] = '  ALICE@EXAMPLE.TEST  ';
        $first['patient']['phone'] = '(06) 00-00.00 00';

        $this->postJson('/api/public/appointments', $first)
            ->assertCreated()
            ->assertJsonMissingPath('data.patient')
            ->assertJsonMissingPath('data.patientId')
            ->assertJsonMissingPath('data.patientReused');

        $second = [...$this->payload(), 'psychologist_id' => $this->psychologist->id, 'starts_at' => $this->slot->addHour()->toISOString()];
        $this->postJson('/api/public/appointments', $second)
            ->assertCreated()
            ->assertJsonMissingPath('data.patient')
            ->assertJsonMissingPath('data.patientId')
            ->assertJsonMissingPath('data.patientReused');

        $this->assertDatabaseCount('patients', 1);
        $this->assertDatabaseHas('patients', ['psychologist_id' => $this->psychologist->id, 'email' => 'alice@example.test', 'phone' => '0600000000']);
    }

    public function test_same_name_or_another_psychologist_does_not_merge_patients(): void
    {
        $a = [...$this->payload(), 'psychologist_id' => $this->psychologist->id];
        $this->postJson('/api/public/appointments', $a)->assertCreated();

        $sameNameDifferentIdentity = [...$a, 'starts_at' => $this->slot->addHour()->toISOString()];
        $sameNameDifferentIdentity['patient']['email'] = 'alice.other@example.test';
        $sameNameDifferentIdentity['patient']['phone'] = '0611111111';
        $this->postJson('/api/public/appointments', $sameNameDifferentIdentity)->assertCreated();

        $otherPsychologist = Psychologist::factory()->create(['consultation_duration' => 60]);
        PsychologistWorkingHour::factory()->create(['psychologist_id' => $otherPsychologist->id, 'day_of_week' => 'monday', 'starts_at' => '09:00', 'ends_at' => '12:00']);
        $other = [...$a, 'psychologist_id' => $otherPsychologist->id];
        $this->postJson('/api/public/appointments', $other)->assertCreated();

        $this->assertDatabaseCount('patients', 3);
        $this->assertSame(2, Patient::query()->where('psychologist_id', $this->psychologist->id)->count());
        $this->assertSame(1, Patient::query()->where('psychologist_id', $otherPsychologist->id)->count());
    }
}
