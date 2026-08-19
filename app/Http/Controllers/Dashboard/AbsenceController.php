<?php

namespace App\Http\Controllers\Dashboard;

use App\DTOs\Absence\StoreAbsenceDTO;
use App\DTOs\Absence\UpdateAbsenceDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\AbsenceIndexRequest;
use App\Http\Requests\StoreAbsenceRequest;
use App\Http\Requests\UpdateAbsenceRequest;
use App\Http\Resources\AbsenceResource;
use App\Models\PsychologistAbsence;
use App\Services\AbsenceService;
use App\Services\PsychologistService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class AbsenceController extends Controller
{
    public function __construct(private AbsenceService $service, private PsychologistService $psychologists) {}

    public function index(AbsenceIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', PsychologistAbsence::class);
        $psy = $this->psychologists->current($request->user());

        return AbsenceResource::collection($this->service->paginate($psy->id, $request->integer('per_page', 15)));
    }

    public function store(StoreAbsenceRequest $request): AbsenceResource
    {
        $this->authorize('create', PsychologistAbsence::class);
        $psy = $this->psychologists->current($request->user());

        return new AbsenceResource($this->service->create($psy->id, StoreAbsenceDTO::fromArray($request->validated())));
    }

    public function show(PsychologistAbsence $absence): AbsenceResource
    {
        $this->authorize('view', $absence);

        return new AbsenceResource($absence);
    }

    public function update(UpdateAbsenceRequest $request, PsychologistAbsence $absence): AbsenceResource
    {
        $this->authorize('update', $absence);

        return new AbsenceResource($this->service->update($absence, UpdateAbsenceDTO::fromArray($request->validated())));
    }

    public function destroy(PsychologistAbsence $absence): Response
    {
        $this->authorize('delete', $absence);
        $this->service->delete($absence);

        return response()->noContent();
    }
}
