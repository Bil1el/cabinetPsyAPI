<?php

namespace Tests\Feature\Public;

use App\Enums\DayOfWeek;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Psychologist;
use App\Models\PsychologistAbsence;
use App\Models\PsychologistWorkingHour;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPsychologistTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_public_list_contains_only_active_psychologists_in_deterministic_order_and_exposes_no_private_data(): void
    {
        $z = Psychologist::factory()->create(['first_name' => 'Zoé', 'last_name' => 'Bernard', 'is_active' => true]);
        $a = Psychologist::factory()->create(['first_name' => 'Alice', 'last_name' => 'Bernard', 'is_active' => true]);
        $inactive = Psychologist::factory()->create(['is_active' => false]);
        $invited = Psychologist::factory()->create();
        $invited->user->update(['status' => UserStatus::INVITED]);
        $suspended = Psychologist::factory()->create();
        $suspended->user->update(['status' => UserStatus::SUSPENDED]);
        $patient = Patient::factory()->create(['psychologist_id' => $a->id]);
        Appointment::factory()->create(['psychologist_id' => $a->id, 'patient_id' => $patient->id]);
        PsychologistAbsence::factory()->create(['psychologist_id' => $a->id]);

        $response = $this->getJson('/api/public/psychologists')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $a->id)
            ->assertJsonPath('data.1.id', $z->id)
            ->assertJsonStructure(['data' => [['id', 'firstName', 'lastName', 'speciality', 'bio', 'photo']]]);

        $response->assertJsonMissing([
            'user_id' => $a->user_id,
            'email' => $a->user->email,
            'phone' => $a->phone,
            'isActive' => true,
            'appointments' => [],
            'absences' => [],
        ]);
        $response->assertJsonMissingPath('data.0.userId');
        $response->assertJsonMissingPath('data.0.patient');
        $response->assertJsonMissingPath('data.0.appointments');
        $response->assertJsonMissingPath('data.0.absences');
        $response->assertJsonMissingPath('data.0.workingHours');
        $response->assertJsonMissingPath('data.0.role');
        $response->assertJsonMissingPath('data.0.isActive');
        $response->assertJsonMissingPath('data.0.consultationDuration');
        $response->assertJsonMissing(['id' => $inactive->id]);
        $response->assertJsonMissing(['id' => $invited->id]);
        $response->assertJsonMissing(['id' => $suspended->id]);
    }

    public function test_suspending_a_psychologist_removes_only_the_public_listing_and_preserves_historical_data(): void
    {
        $admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $psychologist = Psychologist::factory()->create(['is_active' => true]);
        $patient = Patient::factory()->create(['psychologist_id' => $psychologist->id]);
        $appointment = Appointment::factory()->create([
            'psychologist_id' => $psychologist->id,
            'patient_id' => $patient->id,
        ]);

        $this->getJson('/api/public/psychologists')
            ->assertOk()
            ->assertJsonFragment(['id' => $psychologist->id]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/admin/users/{$psychologist->user_id}/suspend")
            ->assertOk();

        $this->getJson('/api/public/psychologists')
            ->assertOk()
            ->assertJsonMissing(['id' => $psychologist->id]);

        $this->assertDatabaseHas('users', ['id' => $psychologist->user_id, 'status' => UserStatus::SUSPENDED->value]);
        $this->assertDatabaseHas('psychologists', ['id' => $psychologist->id, 'is_active' => true]);
        $this->assertDatabaseHas('patients', ['id' => $patient->id]);
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id]);
    }

    public function test_returned_public_psychologist_id_works_with_availability(): void
    {
        $psychologist = Psychologist::factory()->create(['is_active' => true]);
        PsychologistWorkingHour::factory()->create([
            'psychologist_id' => $psychologist->id,
            'day_of_week' => DayOfWeek::MONDAY,
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);
        $date = now()->next('Monday')->toDateString();

        $id = $this->getJson('/api/public/psychologists')->assertOk()->json('data.0.id');

        $this->getJson("/api/psychologists/{$id}/availability?date={$date}&type=in_person")
            ->assertOk()
            ->assertJsonPath('data.0.startsAt', now()->next('Monday')->setTime(9, 0)->toISOString());
    }

    public function test_public_availability_for_today_never_returns_a_past_slot(): void
    {
        $now = CarbonImmutable::parse('2026-08-17 10:15:00', config('app.timezone'));
        Carbon::setTestNow($now);
        CarbonImmutable::setTestNow($now);
        $psychologist = Psychologist::factory()->create(['consultation_duration' => 30]);
        PsychologistWorkingHour::factory()->create([
            'psychologist_id' => $psychologist->id,
            'day_of_week' => 'monday',
            'starts_at' => '09:00',
            'ends_at' => '12:00',
        ]);

        $response = $this->getJson("/api/psychologists/{$psychologist->id}/availability?date=2026-08-17&type=in_person")
            ->assertOk()
            ->assertJsonCount(3, 'data');

        foreach ($response->json('data') as $slot) {
            $this->assertFalse(CarbonImmutable::parse($slot['startsAt'])->isPast());
        }
    }
}
