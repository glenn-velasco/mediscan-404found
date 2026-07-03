<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\Controller;
use App\Http\Requests\UpdateMedicalInfoRequest;
use App\Http\Resources\Api\V1\MedicalInformationResource;
use App\Services\User\MedicalInfoService;
use Illuminate\Http\JsonResponse;

class MedicalInformationController extends Controller
{
    public function __construct(private MedicalInfoService $medicalInfoService) {}

    public function update(UpdateMedicalInfoRequest $request): JsonResponse
    {
        $user = $request->user();

        $emailChanged = $this->medicalInfoService->update($user, $request->validated());

        $medicalInfo = $user->medicalInformation()->with('allergies')->firstOrFail();

        return $this->success(
            new MedicalInformationResource($medicalInfo),
            $emailChanged
                ? 'Medical information saved. Please verify your new email address.'
                : 'Medical information saved.',
        );
    }
}
