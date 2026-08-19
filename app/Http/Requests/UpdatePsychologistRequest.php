<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePsychologistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['sometimes', 'string', 'max:100'], 'last_name' => ['sometimes', 'string', 'max:100'],
            'email' => ['prohibited'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'], 'photo' => ['prohibited'],
            'speciality' => ['sometimes', 'string', 'max:150'], 'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'consultation_duration' => ['sometimes', 'integer', 'min:15', 'max:240'], 'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
