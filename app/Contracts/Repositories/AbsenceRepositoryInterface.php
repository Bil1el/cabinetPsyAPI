<?php

namespace App\Contracts\Repositories;

use App\Models\PsychologistAbsence;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AbsenceRepositoryInterface
{
    public function paginateForPsychologist(int $psychologistId, int $perPage): LengthAwarePaginator;

    public function create(array $attributes): PsychologistAbsence;

    public function update(PsychologistAbsence $absence, array $attributes): PsychologistAbsence;

    public function delete(PsychologistAbsence $absence): void;

    public function hasOverlap(int $psychologistId, mixed $startsAt, mixed $endsAt, ?int $exceptId = null): bool;

    public function overlapping(int $psychologistId, mixed $startsAt, mixed $endsAt): Collection;
}
