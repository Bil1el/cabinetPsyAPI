<?php

namespace App\Http\Controllers\Dashboard;

use App\DTOs\WorkingHours\UpdateWorkingHoursDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateWorkingHoursRequest;
use App\Http\Resources\WorkingHoursResource;
use App\Services\PsychologistService;
use App\Services\WorkingHoursService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkingHoursController extends Controller
{
    public function __construct(private WorkingHoursService $service, private PsychologistService $psychologists) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $psy = $this->psychologists->current($request->user());
        $this->authorize('view', $psy);

        return WorkingHoursResource::collection($this->service->all($psy->id));
    }

    public function update(UpdateWorkingHoursRequest $request): AnonymousResourceCollection
    {
        $psy = $this->psychologists->current($request->user());
        $this->authorize('update', $psy);

        return WorkingHoursResource::collection($this->service->replace($psy->id, UpdateWorkingHoursDTO::fromArray($request->validated())));
    }
}
