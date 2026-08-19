<?php

namespace App\Http\Requests;

class UpdateAbsenceRequest extends StoreAbsenceRequest
{
    public function rules(): array
    {
        return ['starts_at' => ['sometimes', 'date'], 'ends_at' => ['sometimes', 'date', 'after:starts_at'], 'reason' => ['sometimes', 'nullable', 'string', 'max:500']];
    }
}
