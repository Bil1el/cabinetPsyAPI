<?php

namespace App\Http\Controllers\Api;

use App\Contracts\Auth\AuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Hydrators\Auth\LoginHydrator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly LoginHydrator $loginHydrator,
        private readonly AuthServiceInterface $authService,
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $dto = $this->loginHydrator->hydrate($request);

        $user = $this->authService->login($dto);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Connexion réussie.',
            'user' => new UserResource($user),
        ]);
    }

    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json([
            'message' => 'Déconnexion réussie.',
        ]);
    }
}
