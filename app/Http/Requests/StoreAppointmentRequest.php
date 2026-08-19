<?php

namespace App\Http\Requests;

use App\Enums\AppointmentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'psychologist_id' => ['prohibited'], 'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'patient' => ['required_without:patient_id', 'array'], 'patient.first_name' => ['required_with:patient', 'string', 'max:100'], 'patient.last_name' => ['required_with:patient', 'string', 'max:100'], 'patient.email' => ['required_with:patient', 'email', 'max:255'], 'patient.phone' => ['required_with:patient', 'string', 'max:30'], 'patient.birth_date' => ['nullable', 'date', 'before:today'],
            'starts_at' => ['required', 'date'], 'type' => ['required', Rule::enum(AppointmentType::class)], 'patient_message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
