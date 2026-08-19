<?php

namespace App\Services;

use App\Contracts\Repositories\AppointmentRepositoryInterface;
use App\Contracts\Repositories\PatientRepositoryInterface;
use App\DTOs\Appointment\StoreAppointmentDTO;
use App\DTOs\Appointment\UpdateAppointmentDTO;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Exceptions\InvalidAppointmentTransitionException;
use App\Models\Appointment;
use App\Models\Psychologist;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AppointmentService
{
    public function __construct(private AppointmentRepositoryInterface $appointments, private PatientRepositoryInterface $patients, private AvailabilityService $availability, private AppointmentNotificationService $notifications) {}

    public function paginate(int $psychologistId, array $filters, int $perPage)
    {
        return $this->appointments->paginateForPsychologist($psychologistId, $filters, $perPage);
    }

    public function create(Psychologist $psychologist, StoreAppointmentDTO $dto): Appointment
    {
        return DB::transaction(function () use ($psychologist, $dto) {
            $psychologist = Psychologist::query()->lockForUpdate()->findOrFail($psychologist->id);
            $startsAt = CarbonImmutable::parse($dto->attributes['starts_at'])->setTimezone(config('app.timezone'));
            $endsAt = $startsAt->addMinutes($psychologist->consultation_duration);
            $this->availability->assertAvailable($psychologist, $startsAt, $endsAt, AppointmentType::from($dto->attributes['type']));
            $patientId = $this->resolvePatientId($psychologist, $dto->attributes);

            return $this->appointments->create(['psychologist_id' => $psychologist->id, 'patient_id' => $patientId, 'starts_at' => $startsAt, 'ends_at' => $endsAt, 'type' => $dto->attributes['type'], 'patient_message' => $dto->attributes['patient_message'] ?? null, 'status' => AppointmentStatus::PENDING])->load(['patient', 'psychologist']);
        }, 3);
    }

    public function createPublicRequest(Psychologist $psychologist, StoreAppointmentDTO $dto): Appointment
    {
        $appointment = $this->create($psychologist, $dto);
        $this->notifications->requestCreated($appointment);

        return $appointment;
    }

    public function update(Appointment $appointment, UpdateAppointmentDTO $dto): Appointment
    {
        return DB::transaction(function () use ($appointment, $dto) {
            [$psychologist, $appointment] = $this->lockPsychologistAndAppointment($appointment);

            if (in_array($appointment->status, [AppointmentStatus::COMPLETED, AppointmentStatus::CANCELLED], true)) {
                throw new InvalidAppointmentTransitionException('Un rendez-vous terminé ou annulé ne peut plus être modifié.');
            }

            $attributes = $dto->attributes;

            if (array_key_exists('patient_id', $attributes)) {
                $attributes['patient_id'] = $this->resolvePatientId($psychologist, $attributes);
            }

            $startsAt = CarbonImmutable::parse($dto->attributes['starts_at'] ?? $appointment->starts_at)->setTimezone(config('app.timezone'));
            $endsAt = $startsAt->addMinutes($psychologist->consultation_duration);
            $this->availability->assertAvailable($psychologist, $startsAt, $endsAt, AppointmentType::from($attributes['type'] ?? $appointment->type->value), $appointment->id);

            return $this->appointments->update($appointment, [...$attributes, 'starts_at' => $startsAt, 'ends_at' => $endsAt])->load(['patient', 'psychologist']);
        }, 3);
    }

    public function confirm(Appointment $appointment): Appointment
    {
        $appointment = $this->transition($appointment, AppointmentStatus::CONFIRMED, ['confirmed_at' => now()]);
        $this->notifications->confirmed($appointment);

        return $appointment;
    }

    public function cancel(Appointment $appointment, ?string $reason): Appointment
    {
        $appointment = $this->transition($appointment, AppointmentStatus::CANCELLED, ['cancelled_at' => now(), 'cancellation_reason' => $reason]);
        $this->notifications->cancelled($appointment);

        return $appointment;
    }

    public function complete(Appointment $appointment): Appointment
    {
        return $this->transition($appointment, AppointmentStatus::COMPLETED, ['completed_at' => now()]);
    }

    private function transition(Appointment $appointment, AppointmentStatus $target, array $attributes): Appointment
    {
        return DB::transaction(function () use ($appointment, $target, $attributes) {
            [, $appointment] = $this->lockPsychologistAndAppointment($appointment);
            $allowed = [AppointmentStatus::PENDING->value => [AppointmentStatus::CONFIRMED, AppointmentStatus::CANCELLED], AppointmentStatus::CONFIRMED->value => [AppointmentStatus::COMPLETED, AppointmentStatus::CANCELLED]];

            if (! in_array($target, $allowed[$appointment->status->value] ?? [], true)) {
                throw new InvalidAppointmentTransitionException("Transition {$appointment->status->value} → {$target->value} interdite.");
            }

            return $this->appointments->update($appointment, ['status' => $target, ...$attributes])->load(['patient', 'psychologist']);
        }, 3);
    }

    private function lockPsychologistAndAppointment(Appointment $appointment): array
    {
        $psychologist = Psychologist::query()->lockForUpdate()->findOrFail($appointment->psychologist_id);
        $appointment = Appointment::query()->lockForUpdate()->findOrFail($appointment->id);

        return [$psychologist, $appointment];
    }

    private function resolvePatientId(Psychologist $psychologist, array $attributes): int
    {
        if (! array_key_exists('patient_id', $attributes)) {
            return $this->patients->firstOrCreate($psychologist->id, $attributes['patient'])->id;
        }

        $patient = $this->patients->findForPsychologist($psychologist->id, $attributes['patient_id']);

        if (! $patient) {
            throw new AuthorizationException;
        }

        return $patient->id;
    }
}
