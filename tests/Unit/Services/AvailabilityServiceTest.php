<?php

namespace Tests\Unit\Services;

use App\Models\Appointment;
use App\Models\Psychologist;
use App\Models\PsychologistAbsence;
use App\Models\PsychologistWorkingHour;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_slots_support_multiple_ranges_and_remove_absences_and_appointments(): void
    {
        $date = CarbonImmutable::now()->next('Monday')->startOfDay();
        $psychologist = Psychologist::factory()->create(['consultation_duration' => 60]);
        PsychologistWorkingHour::factory()->create(['psychologist_id' => $psychologist->id, 'day_of_week' => 'monday', 'starts_at' => '09:00', 'ends_at' => '12:00']);
        PsychologistWorkingHour::factory()->create(['psychologist_id' => $psychologist->id, 'day_of_week' => 'monday', 'starts_at' => '14:00', 'ends_at' => '16:00']);
        PsychologistAbsence::factory()->create(['psychologist_id' => $psychologist->id, 'starts_at' => $date->setTime(10, 0), 'ends_at' => $date->setTime(11, 0)]);
        Appointment::factory()->create(['psychologist_id' => $psychologist->id, 'starts_at' => $date->setTime(14, 0), 'ends_at' => $date->setTime(15, 0)]);
        $slots = app(AvailabilityService::class)->slots($psychologist, $date);
        $this->assertCount(3, $slots);
        $this->assertSame([9, 11, 15], $slots->map(fn ($slot) => CarbonImmutable::parse($slot['startsAt'])->setTimezone(config('app.timezone'))->hour)->all());
    }

    public function test_closed_day_has_no_slots(): void
    {
        $psychologist = Psychologist::factory()->create();
        $this->assertEmpty(app(AvailabilityService::class)->slots($psychologist, CarbonImmutable::now()->next('Sunday')->startOfDay()));
    }

    public function test_current_day_excludes_past_slots_and_retains_future_bookable_slots(): void
    {
        $now = CarbonImmutable::parse('2026-08-17 10:15:00', config('app.timezone'));
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
        $psychologist = Psychologist::factory()->create(['consultation_duration' => 30]);
        PsychologistWorkingHour::factory()->create([
            'psychologist_id' => $psychologist->id,
            'day_of_week' => 'monday',
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);

        $slots = app(AvailabilityService::class)->slots($psychologist, $now->startOfDay());

        $this->assertSame(['10:30', '11:00', '11:30'], $slots->map(fn (array $slot) => CarbonImmutable::parse($slot['startsAt'])->setTimezone(config('app.timezone'))->format('H:i'))->all());
        $slots->each(function (array $slot) use ($psychologist): void {
            $startsAt = CarbonImmutable::parse($slot['startsAt'])->setTimezone(config('app.timezone'));
            $endsAt = CarbonImmutable::parse($slot['endsAt'])->setTimezone(config('app.timezone'));

            $this->assertFalse($startsAt->isPast());
            app(AvailabilityService::class)->assertAvailable($psychologist, $startsAt, $endsAt);
        });
    }

    public function test_fully_past_day_returns_no_slots_while_future_day_remains_available(): void
    {
        $now = CarbonImmutable::parse('2026-08-17 10:15:00', config('app.timezone'));
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
        $psychologist = Psychologist::factory()->create(['consultation_duration' => 60]);
        PsychologistWorkingHour::factory()->create([
            'psychologist_id' => $psychologist->id,
            'day_of_week' => 'sunday',
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);
        PsychologistWorkingHour::factory()->create([
            'psychologist_id' => $psychologist->id,
            'day_of_week' => 'tuesday',
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);

        $this->assertEmpty(app(AvailabilityService::class)->slots($psychologist, $now->subDay()->startOfDay()));
        $this->assertCount(3, app(AvailabilityService::class)->slots($psychologist, $now->addDay()->startOfDay()));
    }

    public function test_slot_starting_exactly_now_remains_available(): void
    {
        $now = CarbonImmutable::parse('2026-08-17 10:30:00', config('app.timezone'));
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
        $psychologist = Psychologist::factory()->create(['consultation_duration' => 30]);
        PsychologistWorkingHour::factory()->create([
            'psychologist_id' => $psychologist->id,
            'day_of_week' => 'monday',
            'starts_at' => '10:30',
            'ends_at' => '11:30',
        ]);

        $slots = app(AvailabilityService::class)->slots($psychologist, $now->startOfDay());

        $this->assertSame(['10:30', '11:00'], $slots->map(fn (array $slot) => CarbonImmutable::parse($slot['startsAt'])->setTimezone(config('app.timezone'))->format('H:i'))->all());
    }
}
