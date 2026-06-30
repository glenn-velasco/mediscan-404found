<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAllergyRequest;
use App\Http\Requests\UpdateAllergyRequest;
use App\Models\Allergy;
use App\Services\User\AllergyService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AllergyController extends Controller
{
    public function __construct(private AllergyService $allergyService) {}

    public function store(StoreAllergyRequest $request): RedirectResponse
    {
        $this->allergyService->create(auth()->user(), $request->validated());

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Allergy added.',
        ])->back();
    }

    public function update(UpdateAllergyRequest $request, Allergy $allergy): RedirectResponse
    {
        $this->allergyService->update(auth()->user(), $allergy, $request->validated());

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Allergy updated.',
        ])->back();
    }

    public function destroy(Allergy $allergy): RedirectResponse
    {
        $this->allergyService->delete(auth()->user(), $allergy);

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Allergy removed.',
        ])->back();
    }
}
