<?php

namespace Tests\Feature\Appointments;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Psychologist;
use App\Models\PsychologistWorkingHour;
use App\Notifications\AppointmentCancelledNotification;
use App\Notifications\AppointmentConfirmedNotification;
use App\Notifications\AppointmentRequestReceivedNotification;
use App\Notifications\NewAppointmentRequestNotification;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AppointmentNotificationTest extends TestCase
{
    use RefreshDatabase;

    private Psychologist $psychologist;

    private CarbonImmutable $slot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->slot = CarbonImmutable::now()->next('Monday')->setTime(10, 0);
        $this->psychologist = Psychologist::factory()->create(['consultation_duration' => 60]);
        PsychologistWorkingHour::factory()->create(['psychologist_id' => $this->psychologist->id, 'day_of_week' => 'monday', 'starts_at' => '09:00', 'ends_at' => '12:00']);
    }

    public function test_public_request_notifies_the_correct_patient_and_psychologist(): void
    {
        Notification::fake();
        $this->postJson('/api/public/appointments', $this->payload())->assertCreated();
        $appointment = Appointment::firstOrFail();

        Notification::assertSentTo($this->psychologist->user, NewAppointmentRequestNotification::class);
        Notification::assertSentOnDemand(AppointmentRequestReceivedNotification::class, function ($notification, array $channels, object $notifiable) use ($appointment): bool {
            return $notifiable->routes['mail'] === $appointment->patient->email && $channels === ['mail'];
        });
    }

    public function test_confirm_and_cancel_notify_only_after_a_valid_transition(): void
    {
        $appointment = Appointment::factory()->create(['psychologist_id' => $this->psychologist->id, 'status' => AppointmentStatus::PENDING, 'starts_at' => $this->slot, 'ends_at' => $this->slot->addHour()]);
        Notification::fake();

        $this->actingAs($this->psychologist->user)->patchJson("/api/appointments/{$appointment->id}/confirm")->assertOk();
        Notification::assertSentOnDemand(AppointmentConfirmedNotification::class);
        Notification::assertNothingSentTo($this->psychologist->user);

        Notification::fake();
        $this->actingAs($this->psychologist->user)->patchJson("/api/appointments/{$appointment->id}/confirm")->assertUnprocessable();
        Notification::assertNothingSent();

        Notification::fake();
        $this->actingAs($this->psychologist->user)->patchJson("/api/appointments/{$appointment->id}/cancel", ['cancellation_reason' => 'Indisponible'])->assertOk();
        Notification::assertSentOnDemand(AppointmentCancelledNotification::class);

        Notification::fake();
        $this->actingAs($this->psychologist->user)->patchJson("/api/appointments/{$appointment->id}/confirm")->assertUnprocessable();
        Notification::assertNothingSent();
    }

    public function test_conflicted_public_booking_does_not_send_notifications(): void
    {
        Notification::fake();
        $this->postJson('/api/public/appointments', $this->payload())->assertCreated();
        Notification::fake();

        $this->postJson('/api/public/appointments', $this->payload('other@example.test'))->assertConflict();
        Notification::assertNothingSent();
    }

    private function payload(string $email = 'patient@example.test'): array
    {
        return ['psychologist_id' => $this->psychologist->id, 'starts_at' => $this->slot->toISOString(), 'type' => 'in_person', 'patient' => ['first_name' => 'Patient', 'last_name' => 'Public', 'email' => $email, 'phone' => '0600000000']];
    }
}
