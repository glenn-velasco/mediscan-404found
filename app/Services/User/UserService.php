<?php

namespace App\Services\User;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Admin\DashboardService;
use Illuminate\Pagination\LengthAwarePaginator;

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

        return $user;
    }

    public function delete(User $user): void
    {
        $user->delete();
        $this->adminDashboard->flushCache();
    }
}
