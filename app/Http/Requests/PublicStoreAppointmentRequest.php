<?php

namespace App\Http\Requests;

class PublicStoreAppointmentRequest extends StoreAppointmentRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'psychologist_id' => ['required', 'integer'],
            'patient_id' => ['prohibited'],
            'patient' => ['required', 'array'],
        ];
    }
}
