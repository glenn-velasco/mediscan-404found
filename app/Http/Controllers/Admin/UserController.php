<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignRoleRequest;
use App\Models\User;
use App\Services\User\UserService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class UserController extends Controller
{
    public function __construct(private UserService $userService) {}

    public function index(Request $request): Response
    {
        $filters = $request->only(['search', 'role', 'status']);

        $users = $this->userService
            ->paginate(15, $filters)
            ->withQueryString()
            ->through($this->userService->transform(...));

        return Inertia::render('admin/users/index', [
            'users' => $users,
            'filters' => $filters,
        ]);
    }

    public function show(User $user): Response
    {
        return Inertia::render('admin/users/show', [
            'user' => $this->userService->transform($user),
        ]);
    }

    public function assignRole(AssignRoleRequest $request, User $user): RedirectResponse
    {
        $this->userService->assignRole($user, Role::from($request->validated()['role']));

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Role updated.',
        ])->back();
    }

    public function toggleActivation(User $user): RedirectResponse
    {
        $this->userService->setActive($user, ! $user->isActive());

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => $user->isActive() ? 'User activated.' : 'User deactivated.',
        ])->back();
    }

    public function destroy(User $user): RedirectResponse
    {
        $this->userService->delete($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'User deleted.',
        ]);

        return redirect()->route('admin.users.index');
    }
}
