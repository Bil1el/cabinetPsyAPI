<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Patient;

class DashboardService
{
    public function forPsychologist(int $psychologistId): array
    {
        $now = now();
        $counts = Appointment::query()->where('psychologist_id', $psychologistId)->selectRaw('SUM(CASE WHEN starts_at >= ? AND starts_at < ? THEN 1 ELSE 0 END) appointments_today', [$now->copy()->startOfDay(), $now->copy()->addDay()->startOfDay()])->selectRaw('SUM(CASE WHEN starts_at >= ? AND starts_at <= ? THEN 1 ELSE 0 END) appointments_this_week', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()])->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) appointments_pending', [AppointmentStatus::PENDING->value])->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) appointments_confirmed', [AppointmentStatus::CONFIRMED->value])->first();
        $patientsCount = Patient::query()->where('psychologist_id', $psychologistId)->count();
        $next = Appointment::query()->with('patient')->where('psychologist_id', $psychologistId)->where('starts_at', '>=', $now)->whereIn('status', [AppointmentStatus::PENDING, AppointmentStatus::CONFIRMED])->orderBy('starts_at')->limit(5)->get();

        return ['appointmentsToday' => (int) $counts->appointments_today, 'appointmentsThisWeek' => (int) $counts->appointments_this_week, 'appointmentsPending' => (int) $counts->appointments_pending, 'appointmentsConfirmed' => (int) $counts->appointments_confirmed, 'patientsCount' => $patientsCount, 'nextAppointments' => $next];
    }
}
