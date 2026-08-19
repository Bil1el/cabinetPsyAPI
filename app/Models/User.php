<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Notifications\AccountPasswordResetNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens,HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'status' => UserStatus::class,
        ];
    }

    public function psychologist(): HasOne
    {
        return $this->hasOne(Psychologist::class);
    }

    public function psychologistInvitation(): HasOne
    {
        return $this->hasOne(PsychologistInvitation::class);
    }

    public function canAccessPrivateWorkspace(): bool
    {
        return $this->status === UserStatus::ACTIVE
            && $this->email_verified_at !== null
            && ($this->role === UserRole::ADMIN || $this->psychologist()->exists());
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new AccountPasswordResetNotification($token));
    }
}
