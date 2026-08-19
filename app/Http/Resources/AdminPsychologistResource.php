<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPsychologistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'firstName' => $this->psychologist?->first_name,
            'lastName' => $this->psychologist?->last_name,
            'email' => $this->email,
            'status' => $this->status?->value,
            'emailVerified' => $this->email_verified_at !== null,
            'publicProfileActive' => (bool) $this->psychologist?->is_active,
            'invitation' => $this->when($this->relationLoaded('psychologistInvitation') && $this->psychologistInvitation !== null, fn () => [
                'expiresAt' => $this->psychologistInvitation->expires_at?->toISOString(),
                'acceptedAt' => $this->psychologistInvitation->accepted_at?->toISOString(),
                'revokedAt' => $this->psychologistInvitation->revoked_at?->toISOString(),
            ]),
        ];
    }
}
