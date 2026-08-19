<?php

namespace Tests\Feature;

use App\Models\Psychologist;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PlatformEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_professional_public_booking_and_cross_psychologist_isolation_flow(): void
    {
        Storage::fake('public');
        $monday = CarbonImmutable::now()->next('Monday')->startOfDay();
        $inPersonSlot = $monday->setTime(10, 0);
        $onlineSlot = $monday->setTime(14, 0);
        $psychologistA = Psychologist::factory()->create(['consultation_duration' => 60]);
        $psychologistB = Psychologist::factory()->create();

        $this->actingAs($psychologistA->user, 'sanctum')
            ->patchJson('/api/psychologist/profile', [
                'first_name' => 'Claire',
                'last_name' => 'Martin',
                'speciality' => 'Psychologie clinique',
                'bio' => 'Accompagnement des adultes.',
                'is_active' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.firstName', 'Claire');

        $photoResponse = $this->actingAs($psychologistA->user, 'sanctum')
            ->post('/api/psychologist/profile/photo', [
                'photo' => UploadedFile::fake()->createWithContent(
                    'portrait.jpg',
                    base64_decode('/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQL/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/AL//xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/AL//2Q=='),
                ),
            ], ['Accept' => 'application/json'])
            ->assertOk();
        $photoUrl = $photoResponse->json('data.photo');

        $this->actingAs($psychologistA->user, 'sanctum')
            ->putJson('/api/working-hours', ['ranges' => [
                ['day_of_week' => 'monday', 'starts_at' => '09:00', 'ends_at' => '12:00', 'mode' => 'in_person', 'is_active' => true],
                ['day_of_week' => 'monday', 'starts_at' => '14:00', 'ends_at' => '17:00', 'mode' => 'online', 'is_active' => true],
            ]])
            ->assertOk();

        $this->actingAs($psychologistA->user, 'sanctum')
            ->postJson('/api/absences', [
                'starts_at' => $monday->setTime(11, 0)->toISOString(),
                'ends_at' => $monday->setTime(12, 0)->toISOString(),
                'reason' => 'Indisponibilité',
            ])
            ->assertCreated();

        $this->getJson('/api/public/psychologists')
            ->assertOk()
            ->assertJsonFragment(['id' => $psychologistA->id, 'photo' => $photoUrl]);
        $this->getJson("/api/psychologists/{$psychologistA->id}/availability?date={$monday->toDateString()}&type=in_person")
            ->assertOk()
            ->assertJsonFragment(['startsAt' => $inPersonSlot->toISOString()])
            ->assertJsonMissing(['startsAt' => $monday->setTime(11, 0)->toISOString()])
            ->assertJsonMissing(['startsAt' => $onlineSlot->toISOString()]);
        $this->getJson("/api/psychologists/{$psychologistA->id}/availability?date={$monday->toDateString()}&type=online")
            ->assertOk()
            ->assertJsonFragment(['startsAt' => $onlineSlot->toISOString()])
            ->assertJsonMissing(['startsAt' => $inPersonSlot->toISOString()]);

        $inPersonBooking = $this->postJson('/api/public/appointments', $this->bookingPayload(
            $psychologistA,
            $inPersonSlot,
            'in_person',
            'patient@example.test',
        ))->assertCreated();
        $appointmentId = $inPersonBooking->json('data.id');

        $this->getJson("/api/psychologists/{$psychologistA->id}/availability?date={$monday->toDateString()}&type=in_person")
            ->assertOk()
            ->assertJsonMissing(['startsAt' => $inPersonSlot->toISOString()]);
        $this->actingAs($psychologistA->user, 'sanctum')->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.appointmentsPending', 1);
        $patientId = $this->actingAs($psychologistA->user, 'sanctum')->getJson('/api/patients')
            ->assertOk()
            ->assertJsonPath('data.0.email', 'patient@example.test')
            ->json('data.0.id');
        $this->actingAs($psychologistA->user, 'sanctum')
            ->patchJson("/api/appointments/{$appointmentId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->postJson('/api/public/appointments', $this->bookingPayload(
            $psychologistA,
            $inPersonSlot,
            'in_person',
            'another@example.test',
        ))->assertConflict()->assertJsonPath('code', 'SLOT_UNAVAILABLE');

        $this->postJson('/api/public/appointments', $this->bookingPayload(
            $psychologistA,
            $onlineSlot,
            'online',
            'online@example.test',
        ))->assertCreated()->assertJsonPath('data.type', 'online');
        $this->getJson("/api/psychologists/{$psychologistA->id}/availability?date={$monday->toDateString()}&type=online")
            ->assertOk()
            ->assertJsonMissing(['startsAt' => $onlineSlot->toISOString()]);

        $this->actingAs($psychologistB->user, 'sanctum')->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('data.appointmentsPending', 0)
            ->assertJsonPath('data.patientsCount', 0);
        $this->actingAs($psychologistB->user, 'sanctum')->getJson('/api/appointments')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->actingAs($psychologistB->user, 'sanctum')->getJson("/api/appointments/{$appointmentId}")
            ->assertForbidden();
        $this->actingAs($psychologistB->user, 'sanctum')->getJson("/api/patients/{$patientId}")
            ->assertForbidden();
    }

    private function bookingPayload(Psychologist $psychologist, CarbonImmutable $slot, string $type, string $email): array
    {
        return [
            'psychologist_id' => $psychologist->id,
            'starts_at' => $slot->toISOString(),
            'type' => $type,
            'patient' => [
                'first_name' => 'Alice',
                'last_name' => 'Durand',
                'email' => $email,
                'phone' => '0600000000',
            ],
        ];
    }
}
