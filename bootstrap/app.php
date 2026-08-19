<?php

use App\Exceptions\AbsenceConflictException;
use App\Exceptions\AppointmentConflictException;
use App\Exceptions\AppointmentNotAvailableException;
use App\Exceptions\InvalidAppointmentTransitionException;
use App\Exceptions\PatientIdentityConflictException;
use App\Exceptions\PsychologistUnavailableException;
use App\Exceptions\WorkingHoursConflictException;
use App\Http\Middleware\EnsureActiveProfessional;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // This is an API backend. Prevent Laravel's default guest redirect
        // callback from resolving a non-existent Laravel `login` route before
        // the API exception renderer can return its JSON 401 response.
        $middleware->redirectGuestsTo(null);

        $middleware->statefulApi();
        $middleware->alias(['active.professional' => EnsureActiveProfessional::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
        $exceptions->render(function (WorkingHoursConflictException $exception, Request $request) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'SCHEDULE_CONFLICT'], 409);
        });
        $exceptions->render(function (AbsenceConflictException $exception, Request $request) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'ABSENCE_CONFLICT'], 409);
        });
        $exceptions->render(function (PatientIdentityConflictException $exception, Request $request) {
            return response()->json(['message' => $exception->getMessage(), 'code' => 'PATIENT_IDENTITY_CONFLICT'], 409);
        });
        $exceptions->render(function (AppointmentConflictException $exception, Request $request) {
            if ($request->is('api/public/*')) {
                return response()->json(['message' => 'Le créneau sélectionné n’est plus disponible.', 'code' => 'SLOT_UNAVAILABLE'], 409);
            }

            return response()->json(['message' => $exception->getMessage(), 'code' => 'SLOT_UNAVAILABLE'], 409);
        });
        $exceptions->render(function (AppointmentNotAvailableException|PsychologistUnavailableException $exception, Request $request) {
            if ($request->is('api/public/*')) {
                return response()->json(['message' => 'Le créneau sélectionné n’est plus disponible.', 'code' => 'SLOT_UNAVAILABLE'], 409);
            }

            return response()->json(['message' => $exception->getMessage(), 'code' => 'SLOT_UNAVAILABLE'], 409);
        });
        $exceptions->render(fn (InvalidAppointmentTransitionException $exception) => response()->json(['message' => $exception->getMessage(), 'code' => 'INVALID_APPOINTMENT_TRANSITION'], 422));
    })->create();
