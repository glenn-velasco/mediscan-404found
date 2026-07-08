<?php

namespace App\Services\User;

use App\Actions\Fortify\CreateNewUser;
use App\Events\EmailChanged;
use App\Models\User;
use App\Repositories\Eloquent\UserInvitationRepository;
use App\Repositories\Eloquent\UserRepository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\NewAccessToken;

class AccountService
{
    public function __construct(
        private UserRepository $userRepository,
        private UserInvitationRepository $userInvitationRepository,
        private CreateNewUser $createNewUser,
    ) {}

    public function login(string $email, string $password, string $deviceName): NewAccessToken
    {
        $user = $this->userRepository->findByEmail($email);

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.failed')],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['Your account has been deactivated.'],
            ]);
        }

        return $user->createToken($deviceName, $user->tokenAbilities());
    }

    /** @param  array<string, mixed>  $data */
    public function register(array $data): NewAccessToken
    {
        $user = $this->createNewUser->create($data);

        return $user->createToken($data['device_name'], $user->tokenAbilities());
    }

    public function updateEmail(User $user, string $newEmail, string $origin = 'settings'): User
    {
        $previousEmail = $user->email;

        $this->userRepository->updateEmail($user, $newEmail);

        $this->userInvitationRepository->updateEmail($previousEmail, $newEmail);

        Cache::forget(MedicalInfoService::cacheKey($user->id));

        if (request()->hasSession()) {
            session()->put('email_change_origin', $origin);
        }

        $freshUser = $user->fresh();

        event(new EmailChanged($freshUser, $origin));

        return $freshUser;
    }
}
