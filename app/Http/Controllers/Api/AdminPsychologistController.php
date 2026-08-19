<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AdminPsychologistIndexRequest;
use App\Http\Resources\AdminPsychologistResource;
use App\Services\AccountService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminPsychologistController extends Controller
{
    public function __construct(private readonly AccountService $accounts) {}

    public function index(AdminPsychologistIndexRequest $request): AnonymousResourceCollection
    {
        $this->authorize('manageAccounts');

        return AdminPsychologistResource::collection($this->accounts->adminPsychologists($request->integer('per_page', 25)));
    }
}
