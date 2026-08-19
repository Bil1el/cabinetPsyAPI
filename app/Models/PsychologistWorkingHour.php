<?php

namespace App\Models;

use App\Enums\DayOfWeek;
use App\Enums\WorkingHoursMode;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable('psychologist_id', 'day_of_week', 'starts_at', 'ends_at', 'mode', 'is_active')]
class PsychologistWorkingHour extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['day_of_week' => DayOfWeek::class, 'mode' => WorkingHoursMode::class, 'is_active' => 'boolean'];
    }

    public function psychologist(): BelongsTo
    {
        return $this->belongsTo(Psychologist::class);
    }
}
