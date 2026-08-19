<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\EmailChangeRequest;
use App\Models\Psychologist;
use App\Models\PsychologistInvitation;
use App\Models\User;
use App\Notifications\AccountPasswordResetNotification;
use App\Notifications\PsychologistInvitationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class AccountLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_an_admin_can_invite_a_psychologist_and_the_token_is_not_stored_in_clear(): void
    {
        Notification::fake();
        $admin = Psychologist::factory()->create();
        $admin->user->update(['role' => UserRole::ADMIN]);
        $payload = ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.test', 'speciality' => 'Clinique'];
        $this->actingAs($admin->user, 'sanctum')->postJson('/api/admin/psychologists/invitations', $payload)->assertCreated();
        $user = User::query()->where('email', 'ada@example.test')->firstOrFail();
        $this->assertSame(UserStatus::INVITED, $user->status);
        $this->assertNull($user->email_verified_at);
        $this->assertFalse($user->psychologist->is_active);
        $invitation = PsychologistInvitation::query()->firstOrFail();
        $this->assertNotSame('ada@example.test', $invitation->token_hash);
        $this->assertSame(64, strlen($invitation->token_hash));
        Notification::assertSentTo($user, PsychologistInvitationNotification::class);

        $psychologist = Psychologist::factory()->create();
        $this->actingAs($psychologist->user, 'sanctum')->postJson('/api/admin/psychologists/invitations', $payload)->assertForbidden();
    }

    public function test_an_invitation_is_single_use_and_activates_a_verified_account(): void
    {
        $token = 'invitation-token';
        $user = User::factory()->unverified()->create(['status' => UserStatus::INVITED]);
        Psychologist::factory()->create(['user_id' => $user->id, 'is_active' => false]);
        PsychologistInvitation::create(['user_id' => $user->id, 'email' => $user->email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHour(), 'created_by' => User::factory()->create()->id]);
        $payload = ['token' => $token, 'password' => 'a-long-new-password', 'password_confirmation' => 'a-long-new-password'];
        $this->postJson('/api/account/invitations/accept', $payload)->assertOk();
        $user->refresh();
        $this->assertSame(UserStatus::ACTIVE, $user->status);
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Hash::check('a-long-new-password', $user->password));
        $this->assertNotNull(PsychologistInvitation::firstOrFail()->accepted_at);
        $this->postJson('/api/account/invitations/accept', $payload)->assertUnprocessable();
    }

    public function test_expired_or_invalid_invitation_is_refused(): void
    {
        $user = User::factory()->unverified()->create(['status' => UserStatus::INVITED]);
        Psychologist::factory()->create(['user_id' => $user->id]);
        PsychologistInvitation::create(['user_id' => $user->id, 'email' => $user->email, 'token_hash' => hash('sha256', 'expired'), 'expires_at' => now()->subSecond(), 'created_by' => User::factory()->create()->id]);
        $this->postJson('/api/account/invitations/accept', ['token' => 'expired', 'password' => 'a-long-new-password', 'password_confirmation' => 'a-long-new-password'])->assertUnprocessable();
        $this->postJson('/api/account/invitations/accept', ['token' => 'unknown', 'password' => 'a-long-new-password', 'password_confirmation' => 'a-long-new-password'])->assertUnprocessable();
    }

    public function test_login_refuses_unavailable_or_unverified_accounts_with_the_same_generic_error(): void
    {
        foreach ([[UserStatus::INVITED, now()], [UserStatus::SUSPENDED, now()], [UserStatus::ACTIVE, null]] as [$status, $verified]) {
            $user = User::factory()->create(['status' => $status, 'email_verified_at' => $verified, 'password' => 'secret-password']);
            Psychologist::factory()->create(['user_id' => $user->id]);
            $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-password'])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'Les identifiants sont incorrects.']);
        }
        $user = User::factory()->create(['password' => 'secret-password']);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'secret-password'])->assertUnprocessable()->assertJsonValidationErrors(['email' => 'Les identifiants sont incorrects.']);
    }

    public function test_password_reset_is_generic_and_resets_only_an_eligible_professional(): void
    {
        Notification::fake();
        $this->postJson('/api/account/password/forgot', ['email' => 'nobody@example.test'])->assertOk()->assertJsonPath('message', 'Si cette adresse correspond à un compte autorisé, un lien de réinitialisation a été envoyé.');
        $psychologist = Psychologist::factory()->create();
        $this->postJson('/api/account/password/forgot', ['email' => $psychologist->user->email])->assertOk();
        Notification::assertSentTo($psychologist->user, AccountPasswordResetNotification::class);
        $token = Password::broker()->createToken($psychologist->user);
        $this->postJson('/api/account/password/reset', ['email' => $psychologist->user->email, 'token' => $token, 'password' => 'different-new-password', 'password_confirmation' => 'different-new-password'])->assertOk();
        $this->assertTrue(Hash::check('different-new-password', $psychologist->user->refresh()->password));
        $this->postJson('/api/account/password/reset', ['email' => $psychologist->user->email, 'token' => $token, 'password' => 'another-new-password', 'password_confirmation' => 'another-new-password'])->assertUnprocessable();
    }

    public function test_email_change_keeps_the_old_address_until_the_confirmation_token_is_used(): void
    {
        Notification::fake();
        $psychologist = Psychologist::factory()->create();
        $this->actingAs($psychologist->user, 'sanctum')->postJson('/api/account/email-change', ['email' => 'new@example.test'])->assertOk();
        $this->assertSame($psychologist->user->email, $psychologist->user->fresh()->email);
        $change = EmailChangeRequest::firstOrFail();
        $token = 'email-change-token';
        $change->update(['token_hash' => hash('sha256', $token)]);
        $this->postJson('/api/account/email/confirm', ['token' => $token])->assertOk();
        $this->assertSame('new@example.test', $psychologist->user->fresh()->email);
        $this->postJson('/api/account/email/confirm', ['token' => $token])->assertUnprocessable();
    }

    public function test_signed_in_password_change_requires_the_current_password_and_invalidates_other_sessions(): void
    {
        $psychologist = Psychologist::factory()->create();
        $psychologist->user->update(['password' => 'current-password']);
        $this->actingAs($psychologist->user, 'sanctum')->putJson('/api/account/password', ['current_password' => 'wrong-password', 'password' => 'different-new-password', 'password_confirmation' => 'different-new-password'])->assertUnprocessable();
        $this->actingAs($psychologist->user, 'sanctum')->putJson('/api/account/password', ['current_password' => 'current-password', 'password' => 'different-new-password', 'password_confirmation' => 'different-new-password'])->assertOk();
        $this->assertTrue(Hash::check('different-new-password', $psychologist->user->refresh()->password));
    }

    public function test_password_reset_endpoint_is_rate_limited(): void
    {
        foreach (range(1, 5) as $_) {
            $this->postJson('/api/account/password/forgot', ['email' => 'rate-limit@example.test'])->assertOk();
        }
        $this->postJson('/api/account/password/forgot', ['email' => 'rate-limit@example.test'])->assertTooManyRequests();
    }

    public function test_suspension_invalidates_access_and_only_admin_can_apply_it(): void
    {
        $admin = Psychologist::factory()->create();
        $admin->user->update(['role' => UserRole::ADMIN]);
        $target = Psychologist::factory()->create();
        $this->actingAs($target->user, 'sanctum')->patchJson('/api/admin/users/'.$admin->user->id.'/suspend')->assertForbidden();
        $this->actingAs($admin->user, 'sanctum')->patchJson('/api/admin/users/'.$target->user->id.'/suspend')->assertOk();
        $this->assertSame(UserStatus::SUSPENDED, $target->user->fresh()->status);
        $this->actingAs($target->user, 'sanctum')->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_an_admin_can_reactivate_a_suspended_psychologist_who_can_then_login_again(): void
    {
        $admin = Psychologist::factory()->create();
        $admin->user->update(['role' => UserRole::ADMIN]);
        $target = Psychologist::factory()->create();
        $target->user->update(['status' => UserStatus::SUSPENDED, 'password' => 'reactivation-password']);

        $this->postJson('/api/login', ['email' => $target->user->email, 'password' => 'reactivation-password'])->assertUnprocessable();
        $this->actingAs($admin->user, 'sanctum')->patchJson('/api/admin/users/'.$target->user->id.'/reactivate')->assertOk();
        $this->postJson('/api/login', ['email' => $target->user->email, 'password' => 'reactivation-password'])->assertOk();
    }
}
