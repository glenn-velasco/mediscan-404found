<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateMedicalInfoRequest;
use App\Http\Requests\UpdateTransfusionConsentRequest;
use App\Services\User\MedicalInfoService;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class DashboardController extends Controller
{
    public function __construct(private MedicalInfoService $medicalInfoService) {}

    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'medicalInfo' => $this->medicalInfoService->show(auth()->user()),
        ]);
    }

    public function update(UpdateMedicalInfoRequest $request): RedirectResponse
    {
        $result = $this->medicalInfoService->update(auth()->user(), $request->validated());

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => $result['emailChanged']
                ? 'Health record updated. Please verify your new email.'
                : 'Health record updated.',
        ])->back();
    }

    public function updateTransfusionConsent(UpdateTransfusionConsentRequest $request): RedirectResponse
    {
        $noBloodTransfusion = $request->boolean('no_blood_transfusion');

        $this->medicalInfoService->recordTransfusionDecision(auth()->user(), $noBloodTransfusion);

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => $noBloodTransfusion
                ? 'Transfusion refusal recorded.'
                : 'Transfusion consent recorded.',
        ])->back();
    }
}
