<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadPsychologistPhotoRequest;
use App\Http\Resources\PsychologistResource;
use App\Services\PsychologistService;

class PsychologistPhotoController extends Controller
{
    public function __invoke(UploadPsychologistPhotoRequest $request, PsychologistService $service): PsychologistResource
    {
        $psychologist = $service->current($request->user());
        $this->authorize('update', $psychologist);

        return new PsychologistResource($service->replacePhoto($psychologist, $request->file('photo')));
    }
}
