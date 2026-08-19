<?php

namespace App\Repositories;

use App\Contracts\Repositories\AppointmentRepositoryInterface;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class AppointmentRepository implements AppointmentRepositoryInterface
{
    public function paginateForPsychologist(int $psychologistId, array $filters, int $perPage): LengthAwarePaginator
    {
        return Appointment::query()->with(['patient', 'psychologist'])->where('psychologist_id', $psychologistId)
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->where('starts_at', '>=', CarbonImmutable::parse($v)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->where('starts_at', '<', CarbonImmutable::parse($v)->addDay()->startOfDay()))
            ->when($filters['date'] ?? null, fn ($q, $v) => $q->whereDate('starts_at', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['patient_id'] ?? null, fn ($q, $v) => $q->where('patient_id', $v))
            ->orderBy('starts_at')->paginate($perPage);
    }

    public function hasBlockingOverlap(int $psychologistId, mixed $startsAt, mixed $endsAt, ?int $exceptId = null): bool
    {
        return Appointment::query()->where('psychologist_id', $psychologistId)->whereIn('status', $this->blockingStatuses())->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->exists();
    }

    public function hasCurrentOrFutureBlockingOverlap(int $psychologistId, mixed $startsAt, mixed $endsAt): bool
    {
        return Appointment::query()
            ->where('psychologist_id', $psychologistId)
            ->whereIn('status', $this->blockingStatuses())
            ->where('ends_at', '>', now())
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->exists();
    }

    public function blockingOverlapping(int $psychologistId, mixed $startsAt, mixed $endsAt): Collection
    {
        return Appointment::query()->where('psychologist_id', $psychologistId)->whereIn('status', $this->blockingStatuses())->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt)->get(['starts_at', 'ends_at']);
    }

    public function futureBlockingForPsychologist(int $psychologistId): Collection
    {
        return Appointment::query()
            ->select(['id', 'psychologist_id', 'starts_at', 'ends_at', 'status', 'type'])
            ->where('psychologist_id', $psychologistId)
            ->whereIn('status', $this->blockingStatuses())
            ->where('ends_at', '>', now())
            ->orderBy('starts_at')
            ->get();
    }

    public function create(array $attributes): Appointment
    {
        return Appointment::query()->create($attributes);
    }

    public function update(Appointment $appointment, array $attributes): Appointment
    {
        $appointment->update($attributes);

        return $appointment->refresh();
    }

    private function blockingStatuses(): array
    {
        return [AppointmentStatus::PENDING, AppointmentStatus::CONFIRMED];
    }
}
