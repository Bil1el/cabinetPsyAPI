<?php

namespace App\Services;

use App\Contracts\Repositories\PsychologistRepositoryInterface;
use App\DTOs\Psychologist\UpdatePsychologistDTO;
use App\Models\Psychologist;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PsychologistService
{
    public function __construct(private PsychologistRepositoryInterface $psychologists) {}

    public function current(User $user): Psychologist
    {
        return $this->psychologists->forUser($user)->load('user');
    }

    public function publicBookable()
    {
        return $this->psychologists->publicBookable();
    }

    public function update(Psychologist $psychologist, UpdatePsychologistDTO $dto): Psychologist
    {
        return DB::transaction(function () use ($psychologist, $dto) {
            if (isset($dto->profile['first_name'], $dto->profile['last_name'])) {
                $psychologist->user->update(['name' => $dto->profile['first_name'].' '.$dto->profile['last_name']]);
            }

            return $this->psychologists->update($psychologist, $dto->profile);
        });
    }

    public function replacePhoto(Psychologist $psychologist, UploadedFile $photo): Psychologist
    {
        $path = $photo->storeAs(
            Psychologist::PHOTO_DIRECTORY,
            Str::uuid().'.'.$photo->extension(),
            'public',
        );

        if ($path === false) {
            throw new \RuntimeException('Unable to store psychologist photo.');
        }

        $previous = $psychologist->photo;

        try {
            DB::transaction(fn () => $psychologist->update(['photo' => $path]));
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($path);

            throw $exception;
        }

        if (Psychologist::isManagedPhotoPath($previous)) {
            Storage::disk('public')->delete($previous);
        }

        return $psychologist->fresh('user');
    }
}
