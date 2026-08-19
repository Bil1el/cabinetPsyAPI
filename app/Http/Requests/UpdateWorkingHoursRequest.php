<?php

namespace App\Http\Requests;

use App\Enums\DayOfWeek;
use App\Enums\WorkingHoursMode;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWorkingHoursRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['ranges' => ['present', 'array', 'max:28'], 'ranges.*.day_of_week' => ['required', Rule::enum(DayOfWeek::class)], 'ranges.*.starts_at' => ['required', 'date_format:H:i'], 'ranges.*.ends_at' => ['required', 'date_format:H:i', 'after:ranges.*.starts_at'], 'ranges.*.mode' => ['required', Rule::enum(WorkingHoursMode::class)], 'ranges.*.is_active' => ['sometimes', 'boolean']];
    }
}
