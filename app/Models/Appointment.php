<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable('psychologist_id', 'patient_id', 'starts_at', 'ends_at', 'type', 'status', 'patient_message', 'cancellation_reason', 'meeting_url', 'confirmed_at', 'cancelled_at', 'completed_at')]
class Appointment extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'confirmed_at' => 'datetime', 'cancelled_at' => 'datetime', 'completed_at' => 'datetime', 'type' => AppointmentType::class, 'status' => AppointmentStatus::class];
    }

    public function psychologist(): BelongsTo
    {
        return $this->belongsTo(Psychologist::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }
}
