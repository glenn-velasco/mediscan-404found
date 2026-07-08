<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitProfessionalApplicationRequest;
use App\Services\Kyc\ProfessionalApplicationService;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ProfessionalApplicationController extends Controller
{
    public function __construct(private ProfessionalApplicationService $professionalApplicationService) {}

    public function create(): Response
    {
        return Inertia::render('professional-application/create');
    }

    public function show(): Response
    {
        $application = $this->professionalApplicationService->latestFor(Auth::user());

        return Inertia::render('professional-application/show', [
            'application' => $application ? $this->professionalApplicationService->transform($application) : null,
        ]);
    }

    public function store(SubmitProfessionalApplicationRequest $request): RedirectResponse
    {
        $this->professionalApplicationService->submit($request->user(), $request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Application submitted. We will review it shortly.',
        ]);

        return redirect()->route('professional-application.show');
    }
}
