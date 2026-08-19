<?php

namespace Tests\Feature\Public;

use App\Models\Psychologist;
use App\Models\PsychologistAbsence;
use App\Models\PsychologistWorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBookingPrivacyTest extends TestCase
{
    use RefreshDatabase;

    private Psychologist $psychologist;

    private CarbonImmutable $slot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->slot = CarbonImmutable::now()->next('Monday')->setTime(10, 0);
        $this->psychologist = Psychologist::factory()->create(['consultation_duration' => 60]);
        PsychologistWorkingHour::factory()->create([
            'psychologist_id' => $this->psychologist->id,
            'day_of_week' => 'monday',
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);
    }

    public function test_public_booking_success_exposes_only_the_public_appointment_contract(): void
    {
        $this->postJson('/api/public/appointments', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'startsAt', 'endsAt', 'status', 'type']])
            ->assertJsonMissingPath('data.patient')
            ->assertJsonMissingPath('data.patientId')
            ->assertJsonMissingPath('data.patientMessage')
            ->assertJsonMissingPath('data.cancellationReason')
            ->assertJsonMissingPath('data.psychologist');
    }

    public function test_public_booking_returns_one_generic_conflict_for_an_occupied_slot(): void
    {
        $this->postJson('/api/public/appointments', $this->payload())->assertCreated();

        $this->postJson('/api/public/appointments', $this->payload('other@example.test'))
            ->assertConflict()
            ->assertExactJson($this->slotUnavailableResponse());
    }

    public function test_public_booking_does_not_disclose_an_absence(): void
    {
        PsychologistAbsence::factory()->create([
            'psychologist_id' => $this->psychologist->id,
            'starts_at' => $this->slot,
            'ends_at' => $this->slot->addHour(),
        ]);

        $this->postJson('/api/public/appointments', $this->payload())
            ->assertConflict()
            ->assertExactJson($this->slotUnavailableResponse());

    }

    public function test_unknown_or_inactive_public_psychologists_are_not_bookable_or_available(): void
    {
        $inactive = Psychologist::factory()->create(['is_active' => false]);
        $date = $this->slot->toDateString();

        foreach ([999999, $inactive->id] as $psychologistId) {
            $this->getJson("/api/psychologists/{$psychologistId}/availability?date={$date}&type=in_person")
                ->assertNotFound();
            $this->postJson('/api/public/appointments', $this->payload('inactive@example.test', $psychologistId))
                ->assertNotFound();
        }
    }

    private function payload(string $email = 'patient@example.test', Psychologist|int|null $psychologist = null): array
    {
        return [
            'psychologist_id' => $psychologist instanceof Psychologist ? $psychologist->id : ($psychologist ?? $this->psychologist->id),
            'starts_at' => $this->slot->toISOString(),
            'type' => 'in_person',
            'patient' => [
                'first_name' => 'Patient',
                'last_name' => 'Public',
                'email' => $email,
                'phone' => '0600000000',
            ],
        ];
    }

    private function slotUnavailableResponse(): array
    {
        return [
            'message' => 'Le créneau sélectionné n’est plus disponible.',
            'code' => 'SLOT_UNAVAILABLE',
        ];
    }
}
