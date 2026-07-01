<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\InviteUserRequest;
use App\Models\UserInvitation;
use App\Services\User\InvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public function __construct(private InvitationService $userInvitationService) {}

    public function index(): Response
    {
        $invitations = $this->userInvitationService->paginate(15);

        return Inertia::render('admin/invitations/index', ['invitations' => $invitations]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/invitations/create');
    }

    public function resend(UserInvitation $invitation): RedirectResponse
    {
        $this->userInvitationService->resend($invitation);

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => "Invitation resent to {$invitation->email}."    
        ])->back();
    }

    public function destroy(UserInvitation $invitation): RedirectResponse
    {
        $this->userInvitationService->delete($invitation);

        return Inertia::flash('toast', [
            'type'    => 'success',
            'message' => 'Invitation deleted.',
        ])->back();
    }

    public function prune(): RedirectResponse
    {
        $count = $this->userInvitationService->pruneExpired();

        return Inertia::flash('toast', [
            'type'    => 'success',
            'message' => "{$count} expired invitation(s) pruned.",
        ])->back();
    }

    public function store(InviteUserRequest $request): RedirectResponse
    {
        ['email' => $email, 'role' => $role, 'expires_in_days' => $days] = $request->validated();

        $this->userInvitationService->invite($email, $role, $days, Auth::user());

        Inertia::flash('toast', [
            'type'    => 'success',
            'message' => "Invitation sent to {$email}.",
        ]);

        return redirect()->route('admin.users.index');
    }
}
