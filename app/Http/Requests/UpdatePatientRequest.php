<?php

namespace App\Http\Requests;

class UpdatePatientRequest extends StorePatientRequest
{
    public function rules(): array
    {
        return collect(parent::rules())->map(fn ($rules) => array_map(fn ($rule) => $rule === 'required' ? 'sometimes' : $rule, $rules))->all();
    }
}
