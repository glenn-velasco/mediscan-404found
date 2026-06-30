<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\User\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function __construct(private InvitationService $userInvitationService) {}

    public function show(string $token): Response|RedirectResponse
    {
        $invitation = $this->userInvitationService->verifyInvitation($token);
        
        return Inertia::render('auth/accept-invitation', [
            'email' => $invitation->email,
            'token' => $token,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        $this->userInvitationService->acceptInvitation($token, $request->all());

        return redirect()->intended('dashboard');
    }
}
