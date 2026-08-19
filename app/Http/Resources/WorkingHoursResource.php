<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkingHoursResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'dayOfWeek' => $this->day_of_week->value, 'startsAt' => substr($this->starts_at, 0, 5), 'endsAt' => substr($this->ends_at, 0, 5), 'mode' => $this->mode->value, 'isActive' => $this->is_active];
    }
}
