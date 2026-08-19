<?php

namespace App\Hydrators\Auth;

use App\DTO\Auth\LoginDTO;
use App\Http\Requests\Auth\LoginRequest;

final class LoginHydrator
{
    public function hydrate(LoginRequest $request): LoginDTO
    {
        $validated = $request->validated();

        return new LoginDTO(
            email: $validated['email'],
            password: $validated['password'],
            remember: $validated['remember'] ?? false,
        );
    }
}
