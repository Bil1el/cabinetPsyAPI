<?php

namespace App\Http\Controllers\Dashboard;

use App\DTOs\Psychologist\UpdatePsychologistDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePsychologistRequest;
use App\Http\Resources\PsychologistResource;
use App\Services\PsychologistService;
use Illuminate\Http\Request;

class PsychologistController extends Controller
{
    public function __construct(private readonly PsychologistService $service) {}

    public function show(Request $request): PsychologistResource
    {
        $psychologist = $this->service->current($request->user());
        $this->authorize('view', $psychologist);

        return new PsychologistResource($psychologist);
    }

    public function update(UpdatePsychologistRequest $request): PsychologistResource
    {
        $psychologist = $this->service->current($request->user());
        $this->authorize('update', $psychologist);

        return new PsychologistResource($this->service->update($psychologist, UpdatePsychologistDTO::fromArray($request->validated())));
    }
}
