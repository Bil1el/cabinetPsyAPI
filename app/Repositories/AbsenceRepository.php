<?php

namespace App\Repositories;

use App\Contracts\Repositories\AbsenceRepositoryInterface;
use App\Models\PsychologistAbsence;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AbsenceRepository implements AbsenceRepositoryInterface
{
    public function paginateForPsychologist(int $psychologistId, int $perPage): LengthAwarePaginator
    {
        return PsychologistAbsence::query()->where('psychologist_id', $psychologistId)->latest('starts_at')->paginate($perPage);
    }

    public function create(array $attributes): PsychologistAbsence
    {
        return PsychologistAbsence::query()->create($attributes);
    }

    public function update(PsychologistAbsence $absence, array $attributes): PsychologistAbsence
    {
        $absence->update($attributes);

        return $absence->refresh();
    }

    public function delete(PsychologistAbsence $absence): void
    {
        $absence->delete();
    }

    public function hasOverlap(int $psychologistId, mixed $startsAt, mixed $endsAt, ?int $exceptId = null): bool
    {
        return PsychologistAbsence::query()->where('psychologist_id', $psychologistId)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists();
    }

    public function overlapping(int $psychologistId, mixed $startsAt, mixed $endsAt): Collection
    {
        return PsychologistAbsence::query()->where('psychologist_id', $psychologistId)->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->get(['starts_at', 'ends_at']);
    }
}
