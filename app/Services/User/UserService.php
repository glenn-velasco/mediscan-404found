<?php

namespace App\Services\User;

use App\Enums\Role;
use App\Events\UserDeactivated;
use App\Events\UserDeleted;
use App\Models\User;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Admin\DashboardService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private UserRepository $userRepository,
        private DashboardService $adminDashboard,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->userRepository->paginate($perPage, $filters);
    }

    /** @return array<string, mixed> */
    public function transform(User $user): array
    {
        return $this->userRepository->transform($user);
    }

    public function assignRole(User $user, Role $role): User
    {
        $user->syncRoles([$role->value]);
        $this->adminDashboard->flushCache();

        return $user;
    }

    public function setActive(User $user, bool $active): User
    {
        $user->forceFill(['deactivated_at' => $active ? null : now()])->save();
        $this->adminDashboard->flushCache();

        if (! $active) {
            $user->tokens()->delete();
            event(new UserDeactivated($user));
        }

        return $user;
    }

    public function delete(User $user): void
    {
        $userId = $user->id;

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        $this->adminDashboard->flushCache();

        event(new UserDeleted($userId));
    }
}
