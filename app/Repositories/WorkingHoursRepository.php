<?php

namespace App\Repositories;

use App\Contracts\Repositories\WorkingHoursRepositoryInterface;
use App\Models\PsychologistWorkingHour;
use Illuminate\Support\Collection;

class WorkingHoursRepository implements WorkingHoursRepositoryInterface
{
    public function forPsychologist(int $psychologistId): Collection
    {
        return PsychologistWorkingHour::query()->where('psychologist_id', $psychologistId)->orderBy('day_of_week')->orderBy('starts_at')->get();
    }

    public function replaceForPsychologist(int $psychologistId, array $ranges): Collection
    {
        PsychologistWorkingHour::query()->where('psychologist_id', $psychologistId)->delete();
        foreach ($ranges as $range) {
            PsychologistWorkingHour::query()->create(['psychologist_id' => $psychologistId, ...$range]);
        }

        return $this->forPsychologist($psychologistId);
    }
}
