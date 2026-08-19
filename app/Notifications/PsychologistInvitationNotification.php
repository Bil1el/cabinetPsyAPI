<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PsychologistInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = rtrim((string) config('app.frontend_url'), '/').'/invitation/accept?token='.urlencode($this->token);

        return (new MailMessage)->subject('Invitation au cabinet')->greeting('Bienvenue au cabinet')->line('Votre invitation est valable 48 heures.')->action('Activer mon compte', $url)->line('Si vous n’attendiez pas cette invitation, vous pouvez ignorer cet email.');
    }
}
