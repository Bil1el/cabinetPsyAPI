<?php

namespace App\Http\Controllers\Public;

use App\DTOs\Appointment\StoreAppointmentDTO;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicStoreAppointmentRequest;
use App\Http\Resources\PublicAppointmentResource;
use App\Models\Psychologist;
use App\Services\AppointmentService;

class PublicAppointmentController extends Controller
{
    public function __construct(private AppointmentService $service) {}

    public function store(PublicStoreAppointmentRequest $request): PublicAppointmentResource
    {
        $psychologist = Psychologist::query()
            ->where('is_active', true)
            ->whereHas('user', fn ($query) => $query->where('status', UserStatus::ACTIVE->value))
            ->findOrFail($request->integer('psychologist_id'));

        return new PublicAppointmentResource($this->service->createPublicRequest($psychologist, StoreAppointmentDTO::fromArray($request->validated())));
    }
}
