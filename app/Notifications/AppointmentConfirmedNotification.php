<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class AppointmentConfirmedNotification extends AppointmentNotification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment();

        return (new MailMessage)->subject('Votre rendez-vous est confirmé')->greeting('Bonjour '.$appointment->patient->first_name.',')->line('Votre rendez-vous avec '.$appointment->psychologist->first_name.' '.$appointment->psychologist->last_name.' est confirmé.')->line('Date et heure : '.$this->dateAndTime($appointment))->line('Type : '.$this->type($appointment))->line('Le cabinet vous accueillera à l’horaire indiqué.');
    }
}
