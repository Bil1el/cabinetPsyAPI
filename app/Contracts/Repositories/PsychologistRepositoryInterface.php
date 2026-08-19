<?php

namespace App\Contracts\Repositories;

use App\Models\Psychologist;
use App\Models\User;
use Illuminate\Support\Collection;

interface PsychologistRepositoryInterface
{
    public function forUser(User $user): Psychologist;

    public function findOrFail(int $id): Psychologist;

    public function publicBookable(): Collection;

    public function update(Psychologist $psychologist, array $attributes): Psychologist;
}
