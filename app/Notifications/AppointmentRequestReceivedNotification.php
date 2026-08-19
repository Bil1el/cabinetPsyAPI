<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;

class AppointmentRequestReceivedNotification extends AppointmentNotification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appointment = $this->appointment();

        return (new MailMessage)->subject('Votre demande de rendez-vous a été reçue')->greeting('Bonjour '.$appointment->patient->first_name.',')->line('Votre demande avec '.$appointment->psychologist->first_name.' '.$appointment->psychologist->last_name.' a bien été reçue.')->line('Date et heure : '.$this->dateAndTime($appointment))->line('Type : '.$this->type($appointment))->line('Elle est en attente de validation par le cabinet.');
    }
}
