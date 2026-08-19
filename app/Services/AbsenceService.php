<?php

namespace App\Services;

use App\Contracts\Repositories\AbsenceRepositoryInterface;
use App\Contracts\Repositories\AppointmentRepositoryInterface;
use App\DTOs\Absence\StoreAbsenceDTO;
use App\DTOs\Absence\UpdateAbsenceDTO;
use App\Exceptions\AbsenceConflictException;
use App\Models\Psychologist;
use App\Models\PsychologistAbsence;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AbsenceService
{
    public function __construct(private AbsenceRepositoryInterface $absences, private AppointmentRepositoryInterface $appointments) {}

    public function paginate(int $psychologistId, int $perPage)
    {
        return $this->absences->paginateForPsychologist($psychologistId, $perPage);
    }

    public function create(int $psychologistId, StoreAbsenceDTO $dto): PsychologistAbsence
    {
        $startsAt = CarbonImmutable::parse($dto->attributes['starts_at'])->setTimezone(config('app.timezone'));
        $endsAt = CarbonImmutable::parse($dto->attributes['ends_at'])->setTimezone(config('app.timezone'));
        if ($startsAt >= $endsAt) {
            throw new AbsenceConflictException('La fin doit être postérieure au début.');
        }

        return DB::transaction(function () use ($psychologistId, $dto, $startsAt, $endsAt) {
            $psychologist = Psychologist::query()->lockForUpdate()->findOrFail($psychologistId);
            $this->ensureAvailableForAbsence($psychologist->id, $startsAt, $endsAt);

            return $this->absences->create(['psychologist_id' => $psychologist->id, ...$dto->attributes, 'starts_at' => $startsAt, 'ends_at' => $endsAt]);
        });
    }

    public function update(PsychologistAbsence $absence, UpdateAbsenceDTO $dto): PsychologistAbsence
    {
        $startsAt = CarbonImmutable::parse($dto->attributes['starts_at'] ?? $absence->starts_at)->setTimezone(config('app.timezone'));
        $endsAt = CarbonImmutable::parse($dto->attributes['ends_at'] ?? $absence->ends_at)->setTimezone(config('app.timezone'));
        if ($startsAt >= $endsAt) {
            throw new AbsenceConflictException('La fin doit être postérieure au début.');
        }

        return DB::transaction(function () use ($absence, $dto, $startsAt, $endsAt) {
            $psychologist = Psychologist::query()->lockForUpdate()->findOrFail($absence->psychologist_id);
            $absence = PsychologistAbsence::query()->lockForUpdate()->findOrFail($absence->id);
            $this->ensureAvailableForAbsence($psychologist->id, $startsAt, $endsAt, $absence->id);

            return $this->absences->update($absence, [...$dto->attributes, 'starts_at' => $startsAt, 'ends_at' => $endsAt]);
        });
    }

    public function delete(PsychologistAbsence $absence): void
    {
        $this->absences->delete($absence);
    }

    private function ensureAvailableForAbsence(int $psychologistId, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?int $exceptAbsenceId = null): void
    {
        if ($this->absences->hasOverlap($psychologistId, $startsAt, $endsAt, $exceptAbsenceId)) {
            throw new AbsenceConflictException('Cette absence chevauche une absence existante.');
        }

        if ($this->appointments->hasCurrentOrFutureBlockingOverlap($psychologistId, $startsAt, $endsAt)) {
            throw new AbsenceConflictException('Cette absence chevauche un rendez-vous existant.');
        }
    }
}
