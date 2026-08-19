<?php

namespace App\Http\Requests;

use App\Enums\AppointmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['patient_id' => ['sometimes', 'integer', 'exists:patients,id'], 'starts_at' => ['sometimes', 'date'], 'type' => ['sometimes', Rule::enum(AppointmentType::class)], 'patient_message' => ['sometimes', 'nullable', 'string', 'max:2000']];
    }
}
