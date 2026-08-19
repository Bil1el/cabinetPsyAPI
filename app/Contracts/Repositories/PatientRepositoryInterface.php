<?php

namespace App\Contracts\Repositories;

use App\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface PatientRepositoryInterface
{
    public function paginateForPsychologist(int $psychologistId, ?string $search, int $perPage): LengthAwarePaginator;

    public function create(array $attributes): Patient;

    public function firstOrCreate(int $psychologistId, array $attributes): Patient;

    public function findForPsychologist(int $psychologistId, int $patientId): ?Patient;

    public function findByIdentity(int $psychologistId, string $email, string $phone, ?int $exceptPatientId = null): ?Patient;

    public function update(Patient $patient, array $attributes): Patient;
}
