<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEmergencyContactRequest;
use App\Http\Requests\UpdateEmergencyContactRequest;
use App\Models\EmergencyContact;
use App\Services\User\EmergencyContactService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class EmergencyContactController extends Controller
{
    public function __construct(private EmergencyContactService $emergencyContactService) {}

    public function store(StoreEmergencyContactRequest $request): RedirectResponse
    {
        $this->emergencyContactService->create(auth()->user(), $request->validated());

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Emergency contact added.',
        ])->back();
    }

    public function update(UpdateEmergencyContactRequest $request, EmergencyContact $emergencyContact): RedirectResponse
    {
        $this->emergencyContactService->update(auth()->user(), $emergencyContact, $request->validated());

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Emergency contact updated.',
        ])->back();
    }

    public function destroy(EmergencyContact $emergencyContact): RedirectResponse
    {
        $this->emergencyContactService->delete(auth()->user(), $emergencyContact);

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Emergency contact removed.',
        ])->back();
    }
}
