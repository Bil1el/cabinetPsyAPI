<?php

namespace App\Contracts\Repositories;

use App\Models\Appointment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface AppointmentRepositoryInterface
{
    public function paginateForPsychologist(int $psychologistId, array $filters, int $perPage): LengthAwarePaginator;

    public function hasBlockingOverlap(int $psychologistId, mixed $startsAt, mixed $endsAt, ?int $exceptId = null): bool;

    public function hasCurrentOrFutureBlockingOverlap(int $psychologistId, mixed $startsAt, mixed $endsAt): bool;

    public function blockingOverlapping(int $psychologistId, mixed $startsAt, mixed $endsAt): Collection;

    public function futureBlockingForPsychologist(int $psychologistId): Collection;

    public function create(array $attributes): Appointment;

    public function update(Appointment $appointment, array $attributes): Appointment;
}
