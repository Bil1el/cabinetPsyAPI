<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AppointmentIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['date' => ['nullable', 'date_format:Y-m-d'], 'from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from', function ($attribute, $value, $fail) {
            if ($this->filled('from') && CarbonImmutable::parse($this->input('from'))->diffInDays(CarbonImmutable::parse($value)) > 366) {
                $fail('La période demandée ne peut pas dépasser 366 jours.');
            }
        }], 'status' => ['nullable', Rule::enum(AppointmentStatus::class)], 'patient_id' => ['nullable', 'integer'], 'per_page' => ['nullable', 'integer', 'min:1', 'max:100']];
    }
}
