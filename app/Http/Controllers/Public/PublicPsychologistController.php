<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicPsychologistResource;
use App\Services\PsychologistService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicPsychologistController extends Controller
{
    public function __construct(private readonly PsychologistService $service) {}

    public function index(): AnonymousResourceCollection
    {
        return PublicPsychologistResource::collection($this->service->publicBookable());
    }
}
