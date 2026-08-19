<?php

namespace App\Http\Requests;

use App\Enums\AppointmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'], 'type' => ['required', Rule::enum(AppointmentType::class)]];
    }
}
