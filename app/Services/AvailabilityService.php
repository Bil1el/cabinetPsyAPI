<?php

namespace App\Services;

use App\Contracts\Repositories\AbsenceRepositoryInterface;
use App\Contracts\Repositories\AppointmentRepositoryInterface;
use App\Contracts\Repositories\WorkingHoursRepositoryInterface;
use App\Enums\AppointmentType;
use App\Exceptions\AppointmentConflictException;
use App\Exceptions\AppointmentNotAvailableException;
use App\Exceptions\PsychologistUnavailableException;
use App\Models\Psychologist;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class AvailabilityService
{
    public function __construct(private WorkingHoursRepositoryInterface $workingHours, private AbsenceRepositoryInterface $absences, private AppointmentRepositoryInterface $appointments) {}

    public function assertAvailable(Psychologist $psychologist, CarbonInterface $startsAt, CarbonInterface $endsAt, AppointmentType $type = AppointmentType::IN_PERSON, ?int $exceptAppointmentId = null): void
    {
        if (! $psychologist->is_active) {
            throw new PsychologistUnavailableException('Ce psychologue est indisponible.');
        }
        if ($startsAt->isPast()) {
            throw new AppointmentNotAvailableException('Un rendez-vous doit être planifié dans le futur.');
        }
        $day = strtolower($startsAt->englishDayOfWeek);
        $withinHours = $this->workingHours->forPsychologist($psychologist->id)->where('is_active', true)->where('day_of_week.value', $day)->contains(function ($range) use ($startsAt, $endsAt, $psychologist, $type) {
            $rangeStart = $startsAt->copy()->setTimeFromTimeString($range->starts_at);
            $rangeEnd = $startsAt->copy()->setTimeFromTimeString($range->ends_at);

            return $range->mode->supports($type) && $startsAt->greaterThanOrEqualTo($rangeStart)
                && $endsAt->lessThanOrEqualTo($rangeEnd)
                && $rangeStart->diffInMinutes($startsAt) % $psychologist->consultation_duration === 0;
        });
        if (! $withinHours) {
            throw new AppointmentNotAvailableException('Ce créneau est en dehors des horaires de travail.');
        }
        if ($this->absences->hasOverlap($psychologist->id, $startsAt, $endsAt)) {
            throw new PsychologistUnavailableException('Ce créneau chevauche une absence.');
        }
        if ($this->appointments->hasBlockingOverlap($psychologist->id, $startsAt, $endsAt, $exceptAppointmentId)) {
            throw new AppointmentConflictException('Ce créneau est déjà réservé.');
        }
    }

    public function slots(Psychologist $psychologist, CarbonImmutable $date, AppointmentType $type = AppointmentType::IN_PERSON): Collection
    {
        if (! $psychologist->is_active || $date->endOfDay()->isPast()) {
            return collect();
        }
        $day = strtolower($date->englishDayOfWeek);
        $slots = collect();
        $ranges = $this->workingHours->forPsychologist($psychologist->id)->where('is_active', true)->where('day_of_week.value', $day)->filter(fn ($range) => $range->mode->supports($type));
        $windowStart = $date->startOfDay();
        $windowEnd = $date->endOfDay();
        $absences = $this->absences->overlapping($psychologist->id, $windowStart, $windowEnd);
        $appointments = $this->appointments->blockingOverlapping($psychologist->id, $windowStart, $windowEnd);
        foreach ($ranges as $range) {
            $cursor = $date->setTimeFromTimeString($range->starts_at);
            $rangeEnd = $date->setTimeFromTimeString($range->ends_at);
            while ($cursor->addMinutes($psychologist->consultation_duration)->lessThanOrEqualTo($rangeEnd)) {
                $end = $cursor->addMinutes($psychologist->consultation_duration);
                if (! $cursor->isPast() && ! $this->hasOverlap($absences, $cursor, $end) && ! $this->hasOverlap($appointments, $cursor, $end)) {
                    $slots->push(['startsAt' => $cursor->toISOString(), 'endsAt' => $end->toISOString()]);
                }
                $cursor = $end;
            }
        }

        return $slots;
    }

    private function hasOverlap(Collection $intervals, CarbonImmutable $startsAt, CarbonImmutable $endsAt): bool
    {
        return $intervals->contains(fn ($interval) => $interval->starts_at < $endsAt && $interval->ends_at > $startsAt);
    }
}
