<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PsychologistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'firstName' => $this->first_name, 'lastName' => $this->last_name, 'email' => $this->whenLoaded('user', fn () => $this->user->email), 'phone' => $this->phone, 'speciality' => $this->speciality, 'bio' => $this->bio, 'photo' => $this->photoUrl(), 'consultationDuration' => $this->consultation_duration, 'isActive' => $this->is_active];
    }
}
