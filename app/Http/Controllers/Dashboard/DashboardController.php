<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\DashboardResource;
use App\Services\DashboardService;
use App\Services\PsychologistService;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private DashboardService $service, private PsychologistService $psychologists) {}

    public function __invoke(Request $request): DashboardResource
    {
        $psy = $this->psychologists->current($request->user());
        $this->authorize('view', $psy);

        return new DashboardResource($this->service->forPsychologist($psy->id));
    }
}
