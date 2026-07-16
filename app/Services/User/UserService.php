<?php

namespace App\Services\User;

use App\Enums\AuditLogType;
use App\Enums\Role;
use App\Events\RoleChanged;
use App\Events\UserDeactivated;
use App\Events\UserDeleted;
use App\Models\User;
use App\Repositories\Eloquent\UserRepository;
use App\Services\Admin\DashboardService;
use App\Services\Audit\AuditLogger;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UserService
{
    public function __construct(
        private UserRepository $userRepository,
        private DashboardService $adminDashboard,
        private AuditLogger $auditLogger,
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

        event(new RoleChanged($user));

        $this->auditLogger->log(
            action: 'user.role_assigned',
            type: AuditLogType::Update,
            actor: Auth::user(),
            subject: $user,
            metadata: ['role' => $role->value],
            channel: 'web',
        );

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

        $this->auditLogger->log(
            action: $active ? 'user.activated' : 'user.deactivated',
            type: AuditLogType::Update,
            actor: Auth::user(),
            subject: $user,
            channel: 'web',
        );

        return $user;
    }

    public function delete(User $user): void
    {
        $userId = $user->id;
        $actor = Auth::user();

        DB::transaction(function () use ($user) {
            $user->tokens()->delete();
            $user->delete();
        });

        $this->adminDashboard->flushCache();

        event(new UserDeleted($userId));

        $this->auditLogger->log(
            action: 'user.deleted',
            type: AuditLogType::Delete,
            actor: $actor,
            metadata: ['user_id' => $userId],
            channel: 'web',
        );
    }
}
