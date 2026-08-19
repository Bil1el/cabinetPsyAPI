<?php

namespace App\Policies;

use App\Models\PsychologistAbsence;
use App\Models\User;

class AbsencePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->psychologist()->exists();
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function view(User $user, PsychologistAbsence $absence): bool
    {
        return $absence->psychologist()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, PsychologistAbsence $absence): bool
    {
        return $this->view($user, $absence);
    }

    public function delete(User $user, PsychologistAbsence $absence): bool
    {
        return $this->view($user, $absence);
    }
}
