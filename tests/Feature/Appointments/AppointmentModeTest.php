<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentType;
use App\Enums\WorkingHoursMode;
use App\Models\Appointment;
use App\Models\Psychologist;
use App\Models\PsychologistAbsence;
use App\Models\PsychologistWorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_person_hours_accept_in_person_booking_and_reject_online_booking(): void
    {
        $psychologist = $this->psychologistWithHours(WorkingHoursMode::IN_PERSON);

        $this->book($psychologist, AppointmentType::IN_PERSON, 10)->assertCreated();
        $this->book($psychologist, AppointmentType::ONLINE, 11)->assertConflict()->assertJsonPath('code', 'SLOT_UNAVAILABLE');
    }

    public function test_online_hours_accept_online_booking_and_reject_in_person_booking(): void
    {
        $psychologist = $this->psychologistWithHours(WorkingHoursMode::ONLINE);

        $this->book($psychologist, AppointmentType::ONLINE, 10)->assertCreated();
        $this->book($psychologist, AppointmentType::IN_PERSON, 11)->assertConflict()->assertJsonPath('code', 'SLOT_UNAVAILABLE');
    }

    public function test_both_hours_accept_in_person_and_online_bookings(): void
    {
        $psychologist = $this->psychologistWithHours(WorkingHoursMode::BOTH);

        $this->book($psychologist, AppointmentType::IN_PERSON, 10)->assertCreated();
        $this->book($psychologist, AppointmentType::ONLINE, 11)->assertCreated();
    }

    public function test_availability_returns_only_ranges_compatible_with_the_requested_type(): void
    {
        $psychologist = Psychologist::factory()->create(['consultation_duration' => 60]);
        $date = CarbonImmutable::now()->next('Monday')->startOfDay();
        $this->hours($psychologist, '09:00', '10:00', WorkingHoursMode::IN_PERSON);
        $this->hours($psychologist, '10:00', '11:00', WorkingHoursMode::ONLINE);
        $this->hours($psychologist, '11:00', '12:00', WorkingHoursMode::BOTH);

        $inPerson = $this->getJson($this->availabilityUrl($psychologist, $date, AppointmentType::IN_PERSON))->assertOk();
        $online = $this->getJson($this->availabilityUrl($psychologist, $date, AppointmentType::ONLINE))->assertOk();

        $this->assertSame(['09:00', '11:00'], $this->slotTimes($inPerson->json('data')));
        $this->assertSame(['10:00', '11:00'], $this->slotTimes($online->json('data')));
    }

    public function test_absence_removes_overlapping_slots_for_both_types(): void
    {
        $psychologist = $this->psychologistWithHours(WorkingHoursMode::BOTH);
        $date = CarbonImmutable::now()->next('Monday')->startOfDay();
        PsychologistAbsence::factory()->create(['psychologist_id' => $psychologist->id, 'starts_at' => $date->setTime(10, 0), 'ends_at' => $date->setTime(11, 0)]);

        foreach ([AppointmentType::IN_PERSON, AppointmentType::ONLINE] as $type) {
            $response = $this->getJson($this->availabilityUrl($psychologist, $date, $type))->assertOk();
            $this->assertSame(['09:00', '11:00'], $this->slotTimes($response->json('data')));
        }
    }

    public function test_blocking_appointment_removes_the_slot_for_both_types(): void
    {
        $psychologist = $this->psychologistWithHours(WorkingHoursMode::BOTH);
        $date = CarbonImmutable::now()->next('Monday')->startOfDay();
        Appointment::factory()->create(['psychologist_id' => $psychologist->id, 'starts_at' => $date->setTime(10, 0), 'ends_at' => $date->setTime(11, 0)]);

        foreach ([AppointmentType::IN_PERSON, AppointmentType::ONLINE] as $type) {
            $response = $this->getJson($this->availabilityUrl($psychologist, $date, $type))->assertOk();
            $this->assertSame(['09:00', '11:00'], $this->slotTimes($response->json('data')));
        }
    }

    private function psychologistWithHours(WorkingHoursMode $mode): Psychologist
    {
        $psychologist = Psychologist::factory()->create(['consultation_duration' => 60]);
        $this->hours($psychologist, '09:00', '12:00', $mode);

        return $psychologist;
    }

    private function hours(Psychologist $psychologist, string $startsAt, string $endsAt, WorkingHoursMode $mode): void
    {
        PsychologistWorkingHour::factory()->create(['psychologist_id' => $psychologist->id, 'day_of_week' => 'monday', 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'mode' => $mode]);
    }

    private function book(Psychologist $psychologist, AppointmentType $type, int $hour)
    {
        $startsAt = CarbonImmutable::now()->next('Monday')->setTime($hour, 0);

        return $this->actingAs($psychologist->user)->postJson('/api/appointments', [
            'starts_at' => $startsAt->toISOString(),
            'type' => $type->value,
            'patient' => ['first_name' => 'Patient', 'last_name' => 'Test', 'email' => "patient-{$hour}-{$type->value}@example.test", 'phone' => '0600000000'],
        ]);
    }

    private function availabilityUrl(Psychologist $psychologist, CarbonImmutable $date, AppointmentType $type): string
    {
        return "/api/psychologists/{$psychologist->id}/availability?date={$date->toDateString()}&type={$type->value}";
    }

    private function slotTimes(array $slots): array
    {
        return collect($slots)->map(fn (array $slot) => CarbonImmutable::parse($slot['startsAt'])->setTimezone(config('app.timezone'))->format('H:i'))->all();
    }
}
