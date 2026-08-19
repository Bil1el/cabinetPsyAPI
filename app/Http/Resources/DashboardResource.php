<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['appointmentsToday' => $this['appointmentsToday'], 'appointmentsThisWeek' => $this['appointmentsThisWeek'], 'appointmentsPending' => $this['appointmentsPending'], 'appointmentsConfirmed' => $this['appointmentsConfirmed'], 'patientsCount' => $this['patientsCount'], 'nextAppointments' => AppointmentResource::collection($this['nextAppointments'])];
    }
}
