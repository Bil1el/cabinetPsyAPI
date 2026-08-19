<?php

namespace App\Repositories;

use App\Contracts\Repositories\PsychologistRepositoryInterface;
use App\Enums\UserStatus;
use App\Models\Psychologist;
use App\Models\User;
use Illuminate\Support\Collection;

class PsychologistRepository implements PsychologistRepositoryInterface
{
    public function forUser(User $user): Psychologist
    {
        return $user->psychologist()->firstOrFail();
    }

    public function findOrFail(int $id): Psychologist
    {
        return Psychologist::query()->findOrFail($id);
    }

    public function publicBookable(): Collection
    {
        return Psychologist::query()
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::ACTIVE->value))
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->orderBy('id')
            ->get();
    }

    public function update(Psychologist $psychologist, array $attributes): Psychologist
    {
        $psychologist->update($attributes);

        return $psychologist->refresh()->load('user');
    }
}
