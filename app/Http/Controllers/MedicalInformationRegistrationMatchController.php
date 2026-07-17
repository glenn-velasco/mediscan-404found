<?php

namespace App\Http\Controllers;

use App\Models\MedicalInformationRegistrationMatch;
use App\Services\Medical\MedicalInformationRegistrationMatchService;
use Inertia\Inertia;
use Inertia\Response;

class MedicalInformationRegistrationMatchController extends Controller
{
    public function __construct(private MedicalInformationRegistrationMatchService $registrationMatchService) {}

    public function accept(MedicalInformationRegistrationMatch $registrationMatch): Response
    {
        $this->registrationMatchService->accept($registrationMatch);

        return Inertia::render('registration-match-response', ['outcome' => 'accepted']);
    }

    public function deny(MedicalInformationRegistrationMatch $registrationMatch): Response
    {
        $this->registrationMatchService->deny($registrationMatch);

        return Inertia::render('registration-match-response', ['outcome' => 'denied']);
    }
}
