<?php

namespace App\Services;

use App\Models\Appointment;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\AppointmentRequestReceivedNotification;
use App\Notifications\NewAppointmentRequestNotification;
use Illuminate\Notifications\Notification as BaseNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

class AppointmentNotificationService
{
    public function requestCreated(Appointment $appointment): void
    {
        $this->safely(function () use ($appointment): void {
            $appointment->loadMissing(['patient', 'psychologist.user']);
            Notification::route('mail', $appointment->patient->email)->notify(new AppointmentRequestReceivedNotification($appointment));
            $appointment->psychologist->user?->notify(new NewAppointmentRequestNotification($appointment));
        });
    }

    public function confirmed(Appointment $appointment): void
    {
        $this->sendToPatient($appointment, new AppointmentConfirmedNotification($appointment));
    }

    public function cancelled(Appointment $appointment): void
    {
        $this->sendToPatient($appointment, new AppointmentCancelledNotification($appointment));
    }

    private function sendToPatient(Appointment $appointment, BaseNotification $notification): void
    {
        $this->safely(fn () => Notification::route('mail', $appointment->loadMissing('patient')->patient->email)->notify($notification));
    }

    private function safely(callable $dispatch): void
    {
        try {
            $dispatch();
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
