<?php

namespace App\Services;

use App\Contracts\Repositories\AppointmentRepositoryInterface;
use App\Contracts\Repositories\WorkingHoursRepositoryInterface;
use App\DTOs\WorkingHours\UpdateWorkingHoursDTO;
use App\Enums\AppointmentType;
use App\Enums\WorkingHoursMode;
use App\Exceptions\WorkingHoursConflictException;
use App\Models\Psychologist;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class WorkingHoursService
{
    public function __construct(private WorkingHoursRepositoryInterface $workingHours, private AppointmentRepositoryInterface $appointments) {}

    public function all(int $psychologistId)
    {
        return $this->workingHours->forPsychologist($psychologistId);
    }

    public function replace(int $psychologistId, UpdateWorkingHoursDTO $dto)
    {
        $byDay = collect($dto->ranges)->groupBy('day_of_week');
        foreach ($byDay as $ranges) {
            $sorted = $ranges->sortBy('starts_at')->values();
            for ($i = 1; $i < $sorted->count(); $i++) {
                if ($sorted[$i]['starts_at'] < $sorted[$i - 1]['ends_at']) {
                    throw new WorkingHoursConflictException('Les plages horaires ne peuvent pas se chevaucher.');
                }
            }
        }

        return DB::transaction(function () use ($psychologistId, $dto, $byDay) {
            $psychologist = Psychologist::query()->lockForUpdate()->findOrFail($psychologistId);

            foreach ($this->appointments->futureBlockingForPsychologist($psychologist->id) as $appointment) {
                if (! $this->fitsWithinProposedWorkingHours($appointment->starts_at, $appointment->ends_at, $appointment->type, $byDay)) {
                    throw new WorkingHoursConflictException('Les nouveaux horaires excluraient un rendez-vous à venir.');
                }
            }

            return $this->workingHours->replaceForPsychologist($psychologist->id, $dto->ranges);
        });
    }

    private function fitsWithinProposedWorkingHours(CarbonInterface $startsAt, CarbonInterface $endsAt, AppointmentType $type, $rangesByDay): bool
    {
        $day = strtolower($startsAt->englishDayOfWeek);

        return collect($rangesByDay->get($day, []))->contains(function (array $range) use ($startsAt, $endsAt, $type): bool {
            if (! ($range['is_active'] ?? true)) {
                return false;
            }

            $rangeStart = $startsAt->copy()->setTimeFromTimeString($range['starts_at']);
            $rangeEnd = $startsAt->copy()->setTimeFromTimeString($range['ends_at']);

            return WorkingHoursMode::from($range['mode'])->supports($type)
                && $startsAt->greaterThanOrEqualTo($rangeStart)
                && $endsAt->lessThanOrEqualTo($rangeEnd);
        });
    }
}
