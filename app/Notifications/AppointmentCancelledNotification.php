<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class AppointmentCancelledNotification extends AppointmentNotification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment();

        return (new MailMessage)->subject('Votre rendez-vous est annulé')->greeting('Bonjour '.$appointment->patient->first_name.',')->line('Votre rendez-vous avec '.$appointment->psychologist->first_name.' '.$appointment->psychologist->last_name.' a été annulé.')->line('Date et heure : '.$this->dateAndTime($appointment))->line('Pour toute question, contactez le cabinet.');
    }
}
