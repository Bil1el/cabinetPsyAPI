<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class NewAppointmentRequestNotification extends AppointmentNotification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment();
        $patient = $appointment->patient;

        return (new MailMessage)->subject('Nouvelle demande de rendez-vous')->greeting('Bonjour,')->line('Une nouvelle demande de rendez-vous vous est adressée.')->line('Date et heure : '.$this->dateAndTime($appointment))->line('Type : '.$this->type($appointment))->line('Patient : '.$patient->first_name.' '.$patient->last_name)->line('Contact : '.$patient->email.' · '.$patient->phone);
    }
}
