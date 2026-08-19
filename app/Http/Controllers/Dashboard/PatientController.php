<?php

namespace App\Http\Controllers\Dashboard;

use App\DTOs\Patient\StorePatientDTO;
use App\DTOs\Patient\UpdatePatientDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\PatientIndexRequest;
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use App\Services\PatientService;
use App\Services\PsychologistService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PatientController extends Controller
{
    public function __construct(private PatientService $service, private PsychologistService $psychologists) {}

    public function index(PatientIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Patient::class);
        $psy = $this->psychologists->current($request->user());

        return PatientResource::collection($this->service->paginate($psy->id, $request->string('search')->trim()->value() ?: null, $request->integer('per_page', 15)));
    }

    public function store(StorePatientRequest $request): PatientResource
    {
        $this->authorize('create', Patient::class);
        $psy = $this->psychologists->current($request->user());

        return new PatientResource($this->service->create($psy->id, StorePatientDTO::fromArray($request->validated())));
    }

    public function show(Patient $patient): PatientResource
    {
        $this->authorize('view', $patient);

        return new PatientResource($patient->load(['appointments' => fn ($q) => $q->latest('starts_at')->limit(20)]));
    }

    public function update(UpdatePatientRequest $request, Patient $patient): PatientResource
    {
        $this->authorize('update', $patient);

        return new PatientResource($this->service->update($patient, UpdatePatientDTO::fromArray($request->validated())));
    }
}
