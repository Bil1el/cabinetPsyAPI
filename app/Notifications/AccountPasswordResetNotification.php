<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountPasswordResetNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/mot-de-passe/reinitialiser?token='.urlencode($this->token).'&email='.urlencode($notifiable->email);

        return (new MailMessage)->subject('Réinitialisation de mot de passe')->line('Un lien de réinitialisation a été demandé pour votre compte.')->action('Réinitialiser mon mot de passe', $url)->line('Ce lien expire dans 60 minutes.');
    }
}
