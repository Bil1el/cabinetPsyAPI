<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->psychologist()->exists();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, Patient $patient): bool
    {
        return $patient->psychologist()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, Patient $patient): bool
    {
        return $this->view($user, $patient);
    }
}
