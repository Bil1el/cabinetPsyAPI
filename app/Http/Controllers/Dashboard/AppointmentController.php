<?php

namespace App\Http\Controllers\Dashboard;

use App\DTOs\Appointment\StoreAppointmentDTO;
use App\DTOs\Appointment\UpdateAppointmentDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\AppointmentIndexRequest;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Requests\UpdateAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentService;
use App\Services\PsychologistService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AppointmentController extends Controller
{
    public function __construct(private AppointmentService $service, private PsychologistService $psychologists) {}

    public function index(AppointmentIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Appointment::class);
        $psy = $this->psychologists->current($request->user());

        return AppointmentResource::collection($this->service->paginate($psy->id, $request->validated(), $request->integer('per_page', 15)));
    }

    public function store(StoreAppointmentRequest $request): AppointmentResource
    {
        $this->authorize('create', Appointment::class);
        if ($request->filled('patient_id')) {
            $this->authorize('view', Patient::query()->findOrFail($request->integer('patient_id')));
        } $psy = $this->psychologists->current($request->user());

        return new AppointmentResource($this->service->create($psy, StoreAppointmentDTO::fromArray($request->validated())));
    }

    public function show(Appointment $appointment): AppointmentResource
    {
        $this->authorize('view', $appointment);

        return new AppointmentResource($appointment->load(['patient', 'psychologist']));
    }

    public function update(UpdateAppointmentRequest $request, Appointment $appointment): AppointmentResource
    {
        $this->authorize('update', $appointment);

        if ($request->filled('patient_id')) {
            $this->authorize('view', Patient::query()->findOrFail($request->integer('patient_id')));
        }

        return new AppointmentResource($this->service->update($appointment, UpdateAppointmentDTO::fromArray($request->validated())));
    }

    public function confirm(Appointment $appointment): AppointmentResource
    {
        $this->authorize('update', $appointment);

        return new AppointmentResource($this->service->confirm($appointment));
    }

    public function cancel(CancelAppointmentRequest $request, Appointment $appointment): AppointmentResource
    {
        $this->authorize('update', $appointment);

        return new AppointmentResource($this->service->cancel($appointment, $request->validated('cancellation_reason')));
    }

    public function complete(Appointment $appointment): AppointmentResource
    {
        $this->authorize('update', $appointment);

        return new AppointmentResource($this->service->complete($appointment));
    }
}
