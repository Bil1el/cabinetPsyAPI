<?php

namespace App\Services;

use App\Contracts\Repositories\PatientRepositoryInterface;
use App\DTOs\Patient\StorePatientDTO;
use App\DTOs\Patient\UpdatePatientDTO;
use App\Exceptions\PatientIdentityConflictException;
use App\Models\Patient;
use App\Support\PatientIdentityNormalizer;

class PatientService
{
    public function __construct(private readonly PatientRepositoryInterface $patients) {}

    public function paginate(int $psychologistId, ?string $search, int $perPage)
    {
        return $this->patients->paginateForPsychologist($psychologistId, $search, $perPage);
    }

    public function create(int $psychologistId, StorePatientDTO $dto): Patient
    {
        return $this->patients->firstOrCreate($psychologistId, $dto->attributes);
    }

    public function update(Patient $patient, UpdatePatientDTO $dto): Patient
    {
        $attributes = PatientIdentityNormalizer::attributes($dto->attributes);
        $email = $attributes['email'] ?? $patient->email;
        $phone = $attributes['phone'] ?? $patient->phone;

        if ($this->patients->findByIdentity($patient->psychologist_id, $email, $phone, $patient->id)) {
            throw new PatientIdentityConflictException('Un patient avec ces coordonnées existe déjà.');
        }

        return $this->patients->update($patient, $attributes);
    }
}
