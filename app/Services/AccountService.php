<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\EmailChangeRequest;
use App\Models\Psychologist;
use App\Models\PsychologistInvitation;
use App\Models\User;
use App\Notifications\ConfirmEmailChangeNotification;
use App\Notifications\PsychologistInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AccountService
{
    public function invite(User $admin, array $data): void
    {
        $token = Str::random(64);
        $user = DB::transaction(function () use ($admin, $data, $token): User {
            $user = User::query()->where('email', $data['email'])->lockForUpdate()->first();
            if ($user !== null && $user->status !== UserStatus::INVITED) {
                throw ValidationException::withMessages(['email' => ['Cette adresse ne peut pas être invitée.']]);
            }
            $user ??= User::create(['name' => $data['first_name'].' '.$data['last_name'], 'email' => $data['email'], 'password' => Str::random(64), 'role' => UserRole::PSYCHOLOGIST, 'status' => UserStatus::INVITED]);
            $user->forceFill(['name' => $data['first_name'].' '.$data['last_name'], 'status' => UserStatus::INVITED, 'email_verified_at' => null])->save();
            Psychologist::query()->updateOrCreate(['user_id' => $user->id], ['first_name' => $data['first_name'], 'last_name' => $data['last_name'], 'speciality' => $data['speciality'] ?? '', 'is_active' => false]);
            $this->createInvitation($admin, $user, $token);

            return $user;
        });
        $user->notify(new PsychologistInvitationNotification($token));
    }

    public function adminPsychologists(int $perPage)
    {
        return User::query()
            ->whereHas('psychologist')
            ->with(['psychologist', 'psychologistInvitation'])
            ->orderBy('name')
            ->paginate($perPage);
    }

    public function resendInvitation(User $admin, User $user): void
    {
        $token = Str::random(64);
        $user = DB::transaction(function () use ($admin, $token, $user): User {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $invitation = $user->psychologistInvitation()->lockForUpdate()->first();
            if ($user->status !== UserStatus::INVITED || ! $user->psychologist()->exists() || $invitation === null || $invitation->accepted_at !== null) {
                throw ValidationException::withMessages(['invitation' => ['Cette invitation ne peut pas être renvoyée.']]);
            }
            $this->createInvitation($admin, $user, $token);

            return $user;
        });
        $user->notify(new PsychologistInvitationNotification($token));
    }

    public function acceptInvitation(string $token, string $password): void
    {
        DB::transaction(function () use ($token, $password): void {
            $invitation = PsychologistInvitation::query()->where('token_hash', $this->tokenHash($token))->lockForUpdate()->first();
            if ($invitation === null || ! $invitation->isUsable()) {
                $this->invalidInvitation();
            }
            $user = $invitation->user()->lockForUpdate()->first();
            if ($user === null || $user->status !== UserStatus::INVITED) {
                $this->invalidInvitation();
            }
            $user->forceFill(['password' => $password, 'status' => UserStatus::ACTIVE, 'email_verified_at' => now()])->save();
            $invitation->update(['accepted_at' => now()]);
        });
    }

    public function requestPasswordReset(string $email): void
    {
        $user = User::query()->where('email', $email)->where('status', UserStatus::ACTIVE)->whereNotNull('email_verified_at')->whereHas('psychologist')->first();
        if ($user !== null) {
            Password::broker()->sendResetLink(['email' => $email]);
        }
    }

    public function resetPassword(array $data): bool
    {
        return Password::broker()->reset($data, function (User $user, string $password): void {
            if ($user->status !== UserStatus::ACTIVE || $user->email_verified_at === null || ! $user->psychologist()->exists()) {
                throw ValidationException::withMessages(['email' => ['Lien de réinitialisation invalide ou expiré.']]);
            }
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $this->invalidateSessions($user);
        }) === Password::PASSWORD_RESET;
    }

    public function changePassword(User $user, string $currentPassword, string $password): void
    {
        if (! Hash::check($currentPassword, $user->password)) {
            throw ValidationException::withMessages(['current_password' => ['Le mot de passe actuel est incorrect.']]);
        }
        $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
        $this->invalidateSessions($user);
    }

    public function requestEmailChange(User $user, string $email): void
    {
        $token = Str::random(64);
        DB::transaction(function () use ($user, $email, $token): void {
            if (User::query()->where('email', $email)->whereKeyNot($user->id)->exists()) {
                throw ValidationException::withMessages(['email' => ['Cette adresse est déjà utilisée.']]);
            }
            EmailChangeRequest::query()->where('user_id', $user->id)->delete();
            EmailChangeRequest::create(['user_id' => $user->id, 'new_email' => $email, 'token_hash' => $this->tokenHash($token), 'expires_at' => now()->addDay()]);
        });
        Notification::route('mail', $email)->notify(new ConfirmEmailChangeNotification($token));
    }

    public function confirmEmailChange(string $token): void
    {
        DB::transaction(function () use ($token): void {
            $request = EmailChangeRequest::query()->where('token_hash', $this->tokenHash($token))->lockForUpdate()->first();
            if ($request === null || ! $request->isUsable()) {
                throw ValidationException::withMessages(['token' => ['Lien de confirmation invalide ou expiré.']]);
            }
            if (User::query()->where('email', $request->new_email)->whereKeyNot($request->user_id)->exists()) {
                throw ValidationException::withMessages(['token' => ['Lien de confirmation invalide ou expiré.']]);
            }
            $user = User::query()->lockForUpdate()->findOrFail($request->user_id);
            $user->forceFill(['email' => $request->new_email, 'email_verified_at' => now()])->save();
            $request->update(['accepted_at' => now()]);
        });
    }

    public function suspend(User $user): void
    {
        $user->update(['status' => UserStatus::SUSPENDED]);
        $this->invalidateSessions($user);
    }

    public function reactivate(User $user): void
    {
        $user->update(['status' => UserStatus::ACTIVE]);
    }

    public function invalidateSessions(User $user): void
    {
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->tokens()->delete();
    }

    private function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    private function createInvitation(User $admin, User $user, string $token): void
    {
        PsychologistInvitation::query()->where('user_id', $user->id)->delete();
        PsychologistInvitation::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'token_hash' => $this->tokenHash($token),
            'expires_at' => now()->addHours(48),
            'created_by' => $admin->id,
        ]);
    }

    private function invalidInvitation(): never
    {
        throw ValidationException::withMessages(['token' => ['Cette invitation n’est plus valide. Veuillez demander une nouvelle invitation.']]);
    }
}
