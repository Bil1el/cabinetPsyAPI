<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Http\Requests\Auth\ChangeEmailRequest;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\InvitePsychologistRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Http\Requests\Auth\TokenRequest;
use App\Models\PsychologistInvitation;
use App\Models\User;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function invite(InvitePsychologistRequest $request): JsonResponse
    {
        $this->authorize('manageAccounts');
        $this->accounts->invite($request->user(), $request->validated());

        return response()->json(['message' => 'Invitation envoyée.'], 201);
    }

    public function revoke(Request $request, PsychologistInvitation $invitation): JsonResponse
    {
        $this->authorize('manageAccounts');
        $invitation->update(['revoked_at' => now()]);

        return response()->json(['message' => 'Invitation révoquée.']);
    }

    public function accept(AcceptInvitationRequest $request): JsonResponse
    {
        $this->accounts->acceptInvitation($request->validated('token'), $request->validated('password'));

        return response()->json(['message' => 'Compte activé.']);
    }

    public function resendInvitation(Request $request, User $user): JsonResponse
    {
        $this->authorize('manageAccounts');
        $this->accounts->resendInvitation($request->user(), $user);

        return response()->json(['message' => 'Invitation renvoyée.']);
    }

    public function forgot(ForgotPasswordRequest $request): JsonResponse
    {
        $this->accounts->requestPasswordReset($request->validated('email'));

        return response()->json(['message' => 'Si cette adresse correspond à un compte autorisé, un lien de réinitialisation a été envoyé.']);
    }

    public function reset(ResetPasswordRequest $request): JsonResponse
    {
        if (! $this->accounts->resetPassword($request->validated())) {
            return response()->json(['message' => 'Lien de réinitialisation invalide ou expiré.'], 422);
        }

        return response()->json(['message' => 'Mot de passe réinitialisé.']);
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $this->accounts->changePassword($request->user(), $request->validated('current_password'), $request->validated('password'));

        return response()->json(['message' => 'Mot de passe modifié.']);
    }

    public function requestEmailChange(ChangeEmailRequest $request): JsonResponse
    {
        $this->accounts->requestEmailChange($request->user(), $request->validated('email'));

        return response()->json(['message' => 'Un lien de confirmation a été envoyé à la nouvelle adresse.']);
    }

    public function confirmEmailChange(TokenRequest $request): JsonResponse
    {
        $this->accounts->confirmEmailChange($request->validated('token'));

        return response()->json(['message' => 'Adresse email confirmée.']);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        $this->authorize('manageAccounts');
        $this->accounts->suspend($user);

        return response()->json(['message' => 'Compte suspendu.']);
    }

    public function reactivate(Request $request, User $user): JsonResponse
    {
        $this->authorize('manageAccounts');
        $this->accounts->reactivate($user);

        return response()->json(['message' => 'Compte réactivé.']);
    }
}
