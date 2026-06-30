<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateEmailRequest;
use App\Services\User\AccountService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    public function __construct(private AccountService $accountService) {}

    public function edit(): Response
    {
        return Inertia::render('settings/account');
    }

    public function update(UpdateEmailRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->email === $request->validated('email')) {
            return Inertia::flash('toast', ['type' => 'info', 'message' => 'No changes made.'])->back();
        }

        $this->accountService->updateEmail($user, $request->validated('email'));

        return Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Email updated. Please verify your new address.',
        ])->back();
    }
}
