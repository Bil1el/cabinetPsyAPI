<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

#[Fillable('user_id', 'first_name', 'last_name', 'phone', 'speciality', 'bio', 'photo', 'consultation_duration', 'price', 'online_enabled', 'is_active')]
class Psychologist extends Model
{
    use HasFactory;

    public const PHOTO_DIRECTORY = 'psychologists/photos';

    protected function casts(): array
    {
        return ['consultation_duration' => 'integer', 'price' => 'decimal:2', 'online_enabled' => 'boolean', 'is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workingHours(): HasMany
    {
        return $this->hasMany(PsychologistWorkingHour::class);
    }

    public function absences(): HasMany
    {
        return $this->hasMany(PsychologistAbsence::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function photoUrl(): ?string
    {
        if ($this->photo === null) {
            return null;
        }

        if (filter_var($this->photo, FILTER_VALIDATE_URL)) {
            return in_array(parse_url($this->photo, PHP_URL_SCHEME), ['http', 'https'], true)
                ? $this->photo
                : null;
        }

        if (str_starts_with($this->photo, '/') || str_contains($this->photo, '..') || str_contains($this->photo, '\\')) {
            return null;
        }

        return Storage::disk('public')->url($this->photo);
    }

    public static function isManagedPhotoPath(?string $path): bool
    {
        return is_string($path) && preg_match(
            '#^psychologists/(?:photos|[1-9][0-9]*)/[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\\.(?:jpe?g|png|webp)$#i',
            $path,
        ) === 1;
    }
}
