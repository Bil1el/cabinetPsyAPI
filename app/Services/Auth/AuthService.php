<?php

namespace App\Services\Auth;

use App\Contracts\Auth\AuthServiceInterface;
use App\DTO\Auth\LoginDTO;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class AuthService implements AuthServiceInterface
{
    public function login(LoginDTO $dto): User
    {
        $authenticated = Auth::guard('web')->attempt(
            [
                'email' => $dto->email,
                'password' => $dto->password,
                'status' => UserStatus::ACTIVE->value,
            ],
            $dto->remember,
        );

        if (! $authenticated) {
            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        /** @var User $user */
        $user = Auth::guard('web')->user();

        if (! $user->canAccessPrivateWorkspace()) {
            $this->logout();

            throw ValidationException::withMessages([
                'email' => ['Les identifiants sont incorrects.'],
            ]);
        }

        return $user;
    }

    public function logout(): void
    {
        Auth::guard('web')->logout();
        Auth::forgetGuards();
    }
}
