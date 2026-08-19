<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Psychologist;
use App\Models\PsychologistInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminPsychologistTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_list_psychologist_accounts_and_a_psychologist_cannot(): void
    {
        $admin = $this->admin();
        $psychologist = Psychologist::factory()->create(['is_active' => false]);
        $psychologist->user->forceFill(['status' => UserStatus::SUSPENDED, 'email_verified_at' => null])->save();

        $this->actingAs($admin, 'sanctum')->getJson('/api/admin/psychologists')
            ->assertOk()
            ->assertJsonFragment(['id' => $psychologist->user->id, 'status' => 'suspended', 'emailVerified' => false, 'publicProfileActive' => false]);
        $regularPsychologist = Psychologist::factory()->create();
        $this->actingAs($regularPsychologist->user, 'sanctum')->getJson('/api/admin/psychologists')->assertForbidden();
    }

    public function test_an_admin_can_invite_resend_suspend_and_reactivate_a_psychologist(): void
    {
        Notification::fake();
        $admin = $this->admin();
        $payload = ['first_name' => 'Nora', 'last_name' => 'Martin', 'email' => 'nora@example.test', 'speciality' => 'Clinique'];
        $this->actingAs($admin, 'sanctum')->postJson('/api/admin/psychologists/invitations', $payload)->assertCreated();
        $user = PsychologistInvitation::firstOrFail()->user;
        $this->actingAs($admin, 'sanctum')->postJson("/api/admin/psychologists/{$user->id}/invitation/resend")->assertOk();
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/users/{$user->id}/suspend")->assertOk();
        $this->assertSame(UserStatus::SUSPENDED, $user->fresh()->status);
        $this->actingAs($admin, 'sanctum')->patchJson("/api/admin/users/{$user->id}/reactivate")->assertOk();
        $this->assertSame(UserStatus::ACTIVE, $user->fresh()->status);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::ADMIN]);
    }
}
