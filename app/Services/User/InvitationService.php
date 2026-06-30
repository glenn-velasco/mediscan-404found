<?php

namespace App\Services\User;

use App\Enums\Role as RoleEnum;
use App\Actions\Fortify\CreateNewUser;
use App\Exceptions\TooManyAttemptsException;
use App\Exceptions\UserInvitationLinkInvalidException;
use App\Models\Role;
use App\Models\User;
use App\Models\UserInvitation;
use App\Notifications\UserInvitationNotification;
use App\Repositories\Eloquent\UserInvitationRepository;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class InvitationService
{
    public function __construct(
        private UserInvitationRepository $userInvitationRepository,
        private CreateNewUser $createNewUser
    ) {}

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return $this->userInvitationRepository->paginate($perPage)
            ->through(fn ($inv) => $this->userInvitationRepository->transform($inv));
    }

    public function resend(UserInvitation $invitation): void
    {
        $token     = Str::random(64);
        $expiresAt = now()->addDays(3);

        $this->userInvitationRepository->update($invitation, [
            'token'      => $token,
            'expires_at' => $expiresAt,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new UserInvitationNotification(
                route('invitation.accept', ['token' => $token]),
                $expiresAt,
                RoleEnum::from($invitation->role)->label()
            ));

    }

    public function delete(UserInvitation $invitation): void
    {
        $this->userInvitationRepository->delete($invitation);
    }

    public function pruneExpired(): int
    {
        return $this->userInvitationRepository->pruneExpired();
    }

    public function invite(string $email, string $role, int $expiresInDays, Authenticatable $invitedBy): void
    {
        $roleId    = Role::where('name', $role)->value('id');
        $token     = Str::random(64);
        $expiresAt = now()->addDays($expiresInDays);

        $invitation = $this->userInvitationRepository->create([
            'email'      => $email,
            'role_id'    => $roleId,
            'token'      => $token,
            'invited_by' => $invitedBy->getAuthIdentifier(),
            'expires_at' => $expiresAt,
        ]);

        Notification::route('mail', $invitation->email)
            ->notify(new UserInvitationNotification(
                route('invitation.accept', ['token' => $token]),
                $expiresAt,
                $role
            ));

        Broadcast::private('admin-dashboard')
            ->as('InvitationSent')
            ->with([])
            ->send();

    }

    public function verifyInvitation(string $token): ?UserInvitation
    {
        $invitation = $this->userInvitationRepository->findByToken($token);

        if (! $this->checkInvitationStatus($invitation)) {
            throw new UserInvitationLinkInvalidException();
        }

        return $invitation;
    }

    public function acceptInvitation(string $token, array $data): ?User
    {
        $throttleKey = 'accept_invitation' . request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw new TooManyAttemptsException(RateLimiter::availableIn($throttleKey));
        }

        RateLimiter::hit($throttleKey, 30);

        $invitation = $this->userInvitationRepository->findByToken($token);

        if (! $this->checkInvitationStatus($invitation)) {
            throw new UserInvitationLinkInvalidException();
        }

        $user = $this->createNewUser->create([
            ...$data,
            'email' => $invitation->email,
        ]);

        if ($invitation->role_id) {
            $user->assignRole(Role::find($invitation->role_id));
        }

        $this->userInvitationRepository->markAccepted($invitation);

        Auth::login($user);

        RateLimiter::clear($throttleKey);

        event(new Registered($user));

        return $user;
    }

    public function checkInvitationStatus(?UserInvitation $userInvitation): bool
    {
        if (! $userInvitation || ! $userInvitation->isValid() || $userInvitation->accepted_at !== null) {
            return false;
        }

        return true;
    }
}
