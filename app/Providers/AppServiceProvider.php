<?php

namespace App\Providers;

use App\Contracts\Auth\AuthServiceInterface;
use App\Contracts\Repositories\AbsenceRepositoryInterface;
use App\Contracts\Repositories\AppointmentRepositoryInterface;
use App\Contracts\Repositories\PatientRepositoryInterface;
use App\Contracts\Repositories\PsychologistRepositoryInterface;
use App\Contracts\Repositories\WorkingHoursRepositoryInterface;
use App\Enums\UserRole;
use App\Models\PsychologistAbsence;
use App\Policies\AbsencePolicy;
use App\Repositories\AbsenceRepository;
use App\Repositories\AppointmentRepository;
use App\Repositories\PatientRepository;
use App\Repositories\PsychologistRepository;
use App\Repositories\WorkingHoursRepository;
use App\Services\Auth\AuthService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            AuthServiceInterface::class,
            AuthService::class,
        );
        $this->app->bind(PsychologistRepositoryInterface::class, PsychologistRepository::class);
        $this->app->bind(PatientRepositoryInterface::class, PatientRepository::class);
        $this->app->bind(AppointmentRepositoryInterface::class, AppointmentRepository::class);
        $this->app->bind(WorkingHoursRepositoryInterface::class, WorkingHoursRepository::class);
        $this->app->bind(AbsenceRepositoryInterface::class, AbsenceRepository::class);
    }

    public function boot(): void
    {
        Gate::policy(PsychologistAbsence::class, AbsencePolicy::class);
        Gate::define('manageAccounts', fn ($user) => $user->role === UserRole::ADMIN);
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
        RateLimiter::for('public-booking', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('profile-photo', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('account-public', fn (Request $request) => [
            Limit::perMinute(20)->by($request->ip()),
            Limit::perMinute(5)->by($request->ip().'|'.strtolower((string) $request->input('email'))),
        ]);
        RateLimiter::for('account-token', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('account-private', fn (Request $request) => Limit::perMinute(5)->by((string) ($request->user()?->id ?? $request->ip())));
    }
}
