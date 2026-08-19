<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\AdminPsychologistController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Dashboard\AbsenceController;
use App\Http\Controllers\Dashboard\AppointmentController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Dashboard\PatientController;
use App\Http\Controllers\Dashboard\PsychologistController;
use App\Http\Controllers\Dashboard\PsychologistPhotoController;
use App\Http\Controllers\Dashboard\WorkingHoursController;
use App\Http\Controllers\Public\AvailabilityController;
use App\Http\Controllers\Public\PublicAppointmentController;
use App\Http\Controllers\Public\PublicPsychologistController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/account/invitations/accept', [AccountController::class, 'accept'])->middleware('throttle:account-token');
Route::post('/account/password/forgot', [AccountController::class, 'forgot'])->middleware('throttle:account-public');
Route::post('/account/password/reset', [AccountController::class, 'reset'])->middleware('throttle:account-token');
Route::post('/account/email/confirm', [AccountController::class, 'confirmEmailChange'])->middleware('throttle:account-token');
Route::get('/public/psychologists', [PublicPsychologistController::class, 'index'])->middleware('throttle:60,1');
Route::get('/psychologists/{psychologist}/availability', AvailabilityController::class)->middleware('throttle:60,1');
Route::post('/public/appointments', [PublicAppointmentController::class, 'store'])->middleware('throttle:public-booking');

Route::middleware(['auth:sanctum', 'active.professional'])->group(function () {
    Route::get('/me', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::put('/account/password', [AccountController::class, 'changePassword'])->middleware('throttle:account-private');
    Route::post('/account/email-change', [AccountController::class, 'requestEmailChange'])->middleware('throttle:account-private');
    Route::post('/admin/psychologists/invitations', [AccountController::class, 'invite'])->middleware('throttle:account-private');
    Route::get('/admin/psychologists', [AdminPsychologistController::class, 'index']);
    Route::post('/admin/psychologists/{user}/invitation/resend', [AccountController::class, 'resendInvitation'])->middleware('throttle:account-private');
    Route::delete('/admin/psychologists/invitations/{invitation}', [AccountController::class, 'revoke'])->middleware('throttle:account-private');
    Route::patch('/admin/users/{user}/suspend', [AccountController::class, 'suspend'])->middleware('throttle:account-private');
    Route::patch('/admin/users/{user}/reactivate', [AccountController::class, 'reactivate'])->middleware('throttle:account-private');
    Route::get('/psychologist/profile', [PsychologistController::class, 'show']);
    Route::patch('/psychologist/profile', [PsychologistController::class, 'update']);
    Route::post('/psychologist/profile/photo', PsychologistPhotoController::class)->middleware('throttle:profile-photo');
    Route::get('/working-hours', [WorkingHoursController::class, 'index']);
    Route::put('/working-hours', [WorkingHoursController::class, 'update']);
    Route::apiResource('absences', AbsenceController::class)->parameters(['absences' => 'absence']);
    Route::apiResource('patients', PatientController::class)->except('destroy');
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::patch('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
    Route::patch('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::patch('/appointments/{appointment}/complete', [AppointmentController::class, 'complete']);
    Route::get('/dashboard', DashboardController::class);
});
