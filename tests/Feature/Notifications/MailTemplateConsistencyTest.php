<?php

namespace Tests\Feature\Notifications;

use App\Models\Appointment;
use App\Models\Psychologist;
use App\Models\User;
use App\Notifications\AccountPasswordResetNotification;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\AppointmentRequestReceivedNotification;
use App\Notifications\ConfirmEmailChangeNotification;
use App\Notifications\NewAppointmentRequestNotification;
use App\Notifications\PsychologistInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Tests\TestCase;

class MailTemplateConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_transactional_notification_uses_the_published_cabinet_mail_template(): void
    {
        config()->set('app.name', 'Cabinet de psychologie Talaouanou Bahia');
        config()->set('app.url', 'https://cabinet-psychologie.test');
        config()->set('app.frontend_url', 'https://cabinet-psychologie.test');

        $user = User::factory()->create();
        $psychologist = Psychologist::factory()->create(['user_id' => $user->id]);
        $appointment = Appointment::factory()->create(['psychologist_id' => $psychologist->id]);

        foreach ([
            new PsychologistInvitationNotification('invitation-token'),
            new AccountPasswordResetNotification('reset-token'),
            new ConfirmEmailChangeNotification('email-change-token'),
            new AppointmentRequestReceivedNotification($appointment),
            new NewAppointmentRequestNotification($appointment),
            new AppointmentConfirmedNotification($appointment),
            new AppointmentCancelledNotification($appointment),
        ] as $notification) {
            $this->assertCabinetBranding($notification, $user);
        }
    }

    private function assertCabinetBranding(Notification $notification, User $notifiable): void
    {
        /** @var MailMessage $message */
        $message = $notification->toMail($notifiable);

        $this->assertSame('notifications::email', $message->markdown);
        $this->assertNull($message->view);

        $rendered = $message->render();

        $this->assertStringContainsString('CABINET DE PSYCHOLOGIE', $rendered);
        $this->assertStringContainsString('Talaouanou Bahia', $rendered);
        $this->assertStringContainsString('#fbf7f9', $rendered);
        $this->assertDoesNotMatchRegularExpression('/laravel|all rights reserved|hello!|localhost/i', $rendered);

        $text = app(Markdown::class)->renderText($message->markdown, $message->data());

        $this->assertStringContainsString('Cabinet de psychologie Talaouanou Bahia', $text);
        $this->assertDoesNotMatchRegularExpression('/laravel|all rights reserved|hello!|localhost/i', $text);
    }
}
