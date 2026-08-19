<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'psychologistId' => $this->psychologist_id,
            'patientId' => $this->patient_id,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            'psychologist' => new AppointmentPsychologistResource($this->whenLoaded('psychologist')),
            'startsAt' => $this->starts_at->toISOString(),
            'endsAt' => $this->ends_at->toISOString(),
            'status' => $this->status->value,
            'type' => $this->type->value,
            'patientMessage' => $this->patient_message,
            'cancellationReason' => $this->cancellation_reason,
            'confirmedAt' => $this->confirmed_at?->toISOString(),
            'cancelledAt' => $this->cancelled_at?->toISOString(),
            'completedAt' => $this->completed_at?->toISOString(),
        ];
    }
}
