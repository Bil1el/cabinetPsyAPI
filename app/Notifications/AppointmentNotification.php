<?php

namespace App\Notifications;

use App\Enums\AppointmentType;
use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

abstract class AppointmentNotification extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(protected readonly Appointment $appointment)
    {
        $this->afterCommit();
    }

    protected function appointment(): Appointment
    {
        return $this->appointment->loadMissing(['patient', 'psychologist.user']);
    }

    protected function dateAndTime(Appointment $appointment): string
    {
        return $appointment->starts_at->setTimezone(config('app.timezone'))->format('d/m/Y à H:i');
    }

    protected function type(Appointment $appointment): string
    {
        return $appointment->type === AppointmentType::ONLINE ? 'en ligne' : 'au cabinet';
    }
}
