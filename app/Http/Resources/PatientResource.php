<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'firstName' => $this->first_name, 'lastName' => $this->last_name, 'email' => $this->email, 'phone' => $this->phone, 'birthDate' => $this->birth_date?->toDateString(), 'appointments' => $this->whenLoaded('appointments', fn () => AppointmentResource::collection($this->appointments)), 'createdAt' => $this->created_at?->toISOString()];
    }
}
