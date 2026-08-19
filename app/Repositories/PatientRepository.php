<?php

namespace App\Repositories;

use App\Contracts\Repositories\PatientRepositoryInterface;
use App\Exceptions\PatientIdentityConflictException;
use App\Models\Patient;
use App\Support\PatientIdentityNormalizer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\QueryException;

class PatientRepository implements PatientRepositoryInterface
{
    public function paginateForPsychologist(int $psychologistId, ?string $search, int $perPage): LengthAwarePaginator
    {
        return Patient::query()
            ->where('psychologist_id', $psychologistId)
            ->when($search, function ($query, string $term) {
                $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
                $query->where(fn ($q) => $q->where('first_name', 'like', $term)->orWhere('last_name', 'like', $term)->orWhere('email', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->orderBy('last_name')->orderBy('first_name')->paginate($perPage);
    }

    public function create(array $attributes): Patient
    {
        return Patient::query()->create(PatientIdentityNormalizer::attributes($attributes));
    }

    public function firstOrCreate(int $psychologistId, array $attributes): Patient
    {
        $attributes = PatientIdentityNormalizer::attributes($attributes);
        $identity = [
            'psychologist_id' => $psychologistId,
            'email' => $attributes['email'],
            'phone' => $attributes['phone'],
        ];

        try {
            return Patient::query()->firstOrCreate($identity, $attributes);
        } catch (QueryException $exception) {
            if (! $this->isIdentityUniqueViolation($exception)) {
                throw $exception;
            }

            $patient = Patient::query()->where($identity)->first();

            if ($patient) {
                return $patient;
            }

            throw $exception;
        }
    }

    public function findForPsychologist(int $psychologistId, int $patientId): ?Patient
    {
        return Patient::query()
            ->whereKey($patientId)
            ->where('psychologist_id', $psychologistId)
            ->first();
    }

    public function findByIdentity(int $psychologistId, string $email, string $phone, ?int $exceptPatientId = null): ?Patient
    {
        return Patient::query()
            ->where('psychologist_id', $psychologistId)
            ->where('email', PatientIdentityNormalizer::email($email))
            ->where('phone', PatientIdentityNormalizer::phone($phone))
            ->when($exceptPatientId, fn ($query) => $query->whereKeyNot($exceptPatientId))
            ->first();
    }

    public function update(Patient $patient, array $attributes): Patient
    {
        try {
            $patient->update(PatientIdentityNormalizer::attributes($attributes));
        } catch (QueryException $exception) {
            if ($this->isIdentityUniqueViolation($exception)) {
                throw new PatientIdentityConflictException('Un patient avec ces coordonnées existe déjà.', previous: $exception);
            }

            throw $exception;
        }

        return $patient->refresh();
    }

    private function isIdentityUniqueViolation(QueryException $exception): bool
    {
        $driverCode = $exception->errorInfo[1] ?? null;
        $message = $exception->getMessage();

        return ($exception->getCode() === '23000' && $driverCode === 1062 && str_contains($message, 'patients_psychologist_email_phone_unique'))
            || str_contains($message, 'UNIQUE constraint failed: patients.psychologist_id, patients.email, patients.phone');
    }
}
