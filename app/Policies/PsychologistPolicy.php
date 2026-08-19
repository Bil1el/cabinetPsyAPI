<?php

namespace App\Policies;

use App\Models\Psychologist;
use App\Models\User;

class PsychologistPolicy
{
    public function view(User $user, Psychologist $psychologist): bool
    {
        return $psychologist->user_id === $user->id;
    }

    public function update(User $user, Psychologist $psychologist): bool
    {
        return $this->view($user, $psychologist);
    }
}
