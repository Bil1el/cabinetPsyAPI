<?php

namespace App\Contracts\Auth;

use App\DTO\Auth\LoginDTO;
use App\Models\User;

interface AuthServiceInterface
{
    public function login(LoginDTO $dto): User;

    public function logout(): void;
}
