<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveProfessional
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user()?->fresh();
        if ($user === null || ! $user->canAccessPrivateWorkspace()) {
            return response()->json(['message' => 'Compte indisponible.'], 403);
        }

        return $next($request);
    }
}
