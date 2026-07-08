<?php

namespace App\Http\Controllers\Professional;

use App\Http\Controllers\Controller;
use App\Models\Allergy;
use App\Models\User;
use App\Services\Professional\PatientVerificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PatientController extends Controller
{
    public function __construct(private PatientVerificationService $service) {}

    public function index(Request $request): Response
    {
        $email = $request->query('email');

        if (! is_string($email) || $email === '') {
            return Inertia::render('professional/patients/index');
        }

        try {
            $patient = $this->service->findPatientByEmail($email);
        } catch (NotFoundHttpException) {
            throw ValidationException::withMessages([
                'email' => 'No patient with medical information was found for that email.',
            ]);
        }

        return Inertia::render('professional/patients/index', [
            'patient' => $this->service->patientView($patient),
        ]);
    }

    public function verifyAllergy(Allergy $allergy): RedirectResponse
    {
        $this->service->verifyAllergy(auth()->user(), $allergy);

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Allergy verified.',
        ])->back();
    }

    public function witnessTransfusion(User $patient): RedirectResponse
    {
        $medicalInfo = $patient->medicalInformation;

        abort_if(! $medicalInfo, 404);

        $this->service->witnessTransfusionDecision(auth()->user(), $medicalInfo);

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Transfusion decision witnessed.',
        ])->back();
    }
}
