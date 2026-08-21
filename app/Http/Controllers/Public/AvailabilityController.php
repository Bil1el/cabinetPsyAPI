<?php

namespace App\Http\Controllers\Public;

use App\Enums\AppointmentType;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Models\Psychologist;
use App\Services\AvailabilityService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __construct(private AvailabilityService $service) {}

    public function __invoke(AvailabilityRequest $request, Psychologist $psychologist): JsonResponse
    {
        $psychologist = Psychologist::query()
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::ACTIVE->value))
            ->findOrFail($psychologist->id);

        return response()->json(['data' => $this->service->slots($psychologist, CarbonImmutable::parse($request->validated('date'))->startOfDay(), AppointmentType::from($request->validated('type')))]);
    }
}
