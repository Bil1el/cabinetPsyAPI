<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicAppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'startsAt' => $this->starts_at->toISOString(), 'endsAt' => $this->ends_at->toISOString(), 'status' => $this->status->value, 'type' => $this->type->value];
    }
}
