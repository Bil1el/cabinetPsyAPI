<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:create-admin', function () {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Cette commande est réservée aux environnements locaux.');

        return 1;
    }

    $firstName = trim((string) $this->ask('Prénom'));
    $lastName = trim((string) $this->ask('Nom'));
    $email = strtolower(trim((string) $this->ask('Email')));
    $password = (string) $this->secret('Mot de passe');

    if ($firstName === '' || $lastName === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
        $this->error('Prénom, nom, email valide et mot de passe d’au moins 12 caractères sont requis.');

        return 1;
    }
    if (User::query()->where('email', $email)->exists()) {
        $this->error('Cette adresse email est déjà utilisée.');

        return 1;
    }

    $admin = User::make([
        'name' => $firstName.' '.$lastName,
        'email' => $email,
        'password' => Hash::make($password),
        'role' => UserRole::ADMIN,
        'status' => UserStatus::ACTIVE,
    ]);
    $admin->forceFill(['email_verified_at' => now()])->save();
    $this->info('Compte administrateur local créé.');
})->purpose('Create a local development administrator account');

Schedule::command('auth:clear-resets')
    ->dailyAt('02:15')
    ->withoutOverlapping();

Schedule::command('queue:prune-failed --hours=720')
    ->dailyAt('02:30')
    ->withoutOverlapping();
