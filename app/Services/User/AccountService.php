<?php

namespace App\Services\User;

use App\Events\EmailChanged;
use App\Models\User;
use App\Repositories\Eloquent\UserInvitationRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\Facades\Cache;

class AccountService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserInvitationRepository $userInvitationRepository,
    ) {}

    public function updateEmail(User $user, string $newEmail, string $origin = 'settings'): void
    {
        $previousEmail = $user->email;

        $this->userRepository->updateEmail($user, $newEmail);

        $this->userInvitationRepository->updateEmail($previousEmail, $newEmail);

        Cache::forget("user.{$user->id}.dashboard");

        session()->put('email_change_origin', $origin);

        event(new EmailChanged($user->fresh()));
    }
}
