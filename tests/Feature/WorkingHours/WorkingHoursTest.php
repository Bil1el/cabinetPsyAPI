<?php

namespace Tests\Feature\WorkingHours;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Psychologist;
use App\Models\PsychologistWorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkingHoursTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_multiple_ranges_are_saved_and_overlaps_are_rejected(): void
    {
        $psychologist = Psychologist::factory()->create();
        $this->actingAs($psychologist->user)->putJson('/api/working-hours', ['ranges' => [
            ['day_of_week' => 'monday', 'starts_at' => '09:00', 'ends_at' => '12:00', 'mode' => 'both'],
            ['day_of_week' => 'monday', 'starts_at' => '14:00', 'ends_at' => '18:00', 'mode' => 'both'],
        ]])->assertOk()->assertJsonCount(2, 'data');

        $this->actingAs($psychologist->user)->putJson('/api/working-hours', ['ranges' => [
            ['day_of_week' => 'monday', 'starts_at' => '09:00', 'ends_at' => '12:00', 'mode' => 'both'],
            ['day_of_week' => 'monday', 'starts_at' => '11:00', 'ends_at' => '18:00', 'mode' => 'both'],
        ]])->assertConflict();
    }

    public function test_end_before_start_is_invalid(): void
    {
        $psychologist = Psychologist::factory()->create();
        $this->actingAs($psychologist->user)->putJson('/api/working-hours', ['ranges' => [['day_of_week' => 'monday', 'starts_at' => '12:00', 'ends_at' => '09:00', 'mode' => 'both']]])->assertUnprocessable();
    }

    public function test_update_without_future_appointments_succeeds(): void
    {
        $psychologist = Psychologist::factory()->create();

        $this->replace($psychologist, $this->ranges('14:00', '18:00'))
            ->assertOk()
            ->assertJsonPath('data.0.startsAt', '14:00');
    }

    public function test_update_preserving_pending_and_confirmed_future_appointments_succeeds(): void
    {
        $psychologist = Psychologist::factory()->create();
        $slot = $this->nextMonday();
        $this->appointment($psychologist, AppointmentStatus::PENDING, $slot, $slot->addHour());
        $this->appointment($psychologist, AppointmentStatus::CONFIRMED, $slot->addHour(), $slot->addHours(2));

        $this->replace($psychologist, $this->ranges('09:00', '12:00'))->assertOk();
    }

    public function test_update_excluding_a_pending_future_appointment_is_rejected_without_changes(): void
    {
        $psychologist = Psychologist::factory()->create();
        $this->seedExistingHours($psychologist);
        $slot = $this->nextMonday();
        $appointment = $this->appointment($psychologist, AppointmentStatus::PENDING, $slot, $slot->addHour());

        $this->replace($psychologist, $this->ranges('14:00', '18:00'))
            ->assertConflict()
            ->assertJsonPath('code', 'SCHEDULE_CONFLICT');

        $this->assertHours($psychologist, '09:00', '17:00');
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'psychologist_id' => $psychologist->id, 'status' => AppointmentStatus::PENDING->value]);
    }

    public function test_update_excluding_a_confirmed_future_appointment_is_rejected(): void
    {
        $psychologist = Psychologist::factory()->create();
        $this->seedExistingHours($psychologist);
        $slot = $this->nextMonday();
        $appointment = $this->appointment($psychologist, AppointmentStatus::CONFIRMED, $slot, $slot->addHour());

        $this->replace($psychologist, $this->ranges('14:00', '18:00'))->assertConflict()->assertJsonPath('code', 'SCHEDULE_CONFLICT');

        $this->assertHours($psychologist, '09:00', '17:00');
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => AppointmentStatus::CONFIRMED->value]);
    }

    public function test_cancelled_and_past_appointments_do_not_block_schedule_changes(): void
    {
        $psychologist = Psychologist::factory()->create();
        $future = $this->nextMonday();
        $past = CarbonImmutable::now()->previous('Monday')->setTime(10, 0);
        $this->appointment($psychologist, AppointmentStatus::CANCELLED, $future, $future->addHour());
        $this->appointment($psychologist, AppointmentStatus::PENDING, $past, $past->addHour());
        $this->appointment($psychologist, AppointmentStatus::COMPLETED, $past->addHour(), $past->addHours(2));

        $this->replace($psychologist, $this->ranges('14:00', '18:00'))->assertOk();
    }

    public function test_appointment_must_fit_inside_one_of_multiple_working_intervals(): void
    {
        $psychologist = Psychologist::factory()->create();
        $this->seedExistingHours($psychologist);
        $slot = $this->nextMonday()->setTime(11, 30);
        $this->appointment($psychologist, AppointmentStatus::PENDING, $slot, $slot->addHours(3));

        $this->replace($psychologist, [
            ['day_of_week' => 'monday', 'starts_at' => '09:00', 'ends_at' => '12:00', 'mode' => 'both'],
            ['day_of_week' => 'monday', 'starts_at' => '14:00', 'ends_at' => '18:00', 'mode' => 'both'],
        ])->assertConflict()->assertJsonPath('code', 'SCHEDULE_CONFLICT');

        $this->assertHours($psychologist, '09:00', '17:00');
    }

    public function test_another_psychologists_appointments_do_not_block_schedule_update(): void
    {
        $a = Psychologist::factory()->create();
        $b = Psychologist::factory()->create();
        $slot = $this->nextMonday();
        $this->appointment($a, AppointmentStatus::PENDING, $slot, $slot->addHour());

        $this->replace($b, $this->ranges('14:00', '18:00'))->assertOk();
    }

    public function test_schedule_mode_cannot_become_incompatible_with_a_future_blocking_appointment(): void
    {
        $psychologist = Psychologist::factory()->create();
        $slot = $this->nextMonday();
        $this->appointment($psychologist, AppointmentStatus::PENDING, $slot, $slot->addHour());

        $this->replace($psychologist, [[
            'day_of_week' => 'monday',
            'starts_at' => '09:00',
            'ends_at' => '12:00',
            'mode' => 'online',
        ]])->assertConflict()->assertJsonPath('code', 'SCHEDULE_CONFLICT');
    }

    private function replace(Psychologist $psychologist, array $ranges)
    {
        return $this->actingAs($psychologist->user)->putJson('/api/working-hours', ['ranges' => $ranges]);
    }

    private function ranges(string $startsAt, string $endsAt): array
    {
        return [['day_of_week' => 'monday', 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'mode' => 'both']];
    }

    private function seedExistingHours(Psychologist $psychologist): void
    {
        PsychologistWorkingHour::factory()->create([
            'psychologist_id' => $psychologist->id,
            'day_of_week' => 'monday',
            'starts_at' => '09:00',
            'ends_at' => '17:00',
        ]);
    }

    private function appointment(Psychologist $psychologist, AppointmentStatus $status, CarbonImmutable $startsAt, CarbonImmutable $endsAt): Appointment
    {
        return Appointment::factory()->create([
            'psychologist_id' => $psychologist->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status,
        ]);
    }

    private function assertHours(Psychologist $psychologist, string $startsAt, string $endsAt): void
    {
        $this->assertDatabaseHas('psychologist_working_hours', [
            'psychologist_id' => $psychologist->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $this->assertSame(1, PsychologistWorkingHour::query()->where('psychologist_id', $psychologist->id)->count());
    }

    private function nextMonday(): CarbonImmutable
    {
        return CarbonImmutable::now()->next('Monday')->setTime(10, 0);
    }
}
