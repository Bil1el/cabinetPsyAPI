<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmEmailChangeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/email/confirm?token='.urlencode($this->token);

        return (new MailMessage)->subject('Confirmez votre nouvelle adresse email')->line('Confirmez cette adresse pour l’utiliser avec votre compte professionnel.')->action('Confirmer mon adresse', $url)->line('Ce lien expire dans 24 heures.');
    }
}
