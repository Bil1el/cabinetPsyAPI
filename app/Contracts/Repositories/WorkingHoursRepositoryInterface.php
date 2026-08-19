<?php

namespace App\Contracts\Repositories;

use Illuminate\Support\Collection;

interface WorkingHoursRepositoryInterface
{
    public function forPsychologist(int $psychologistId): Collection;

    public function replaceForPsychologist(int $psychologistId, array $ranges): Collection;
}
