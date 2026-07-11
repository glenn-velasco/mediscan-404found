<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<User>
 */
class UserRepository extends BaseRepository
{
    public function __construct(User $user)
    {
        parent::__construct($user);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, User>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        $search = $filters['search'] ?? '';
        $role = $filters['role'] ?? '';
        $status = $filters['status'] ?? '';

        return $this->model->newQuery()
            ->with(['roles:id,name'])
            ->when($search !== '', fn ($q) => $q->search($search))
            ->when($role !== '', fn ($q) => $q->filterByRole($role))
            ->when($status !== '', fn ($q) => $q->filterByStatus($status))
            ->latest('users.created_at')
            ->paginate($perPage);
    }

    public function updateEmail(User $user, string $email): bool
    {
        return $user->forceFill([
            'email' => $email,
            'email_verified_at' => null,
        ])->save();
    }

    public function findByEmail(string $email): ?User
    {
        return $this->model->newQuery()->where('email', $email)->first();
    }

    public function countAll(): int
    {
        return $this->model->count();
    }

    public function countActive(): int
    {
        return $this->model->whereActive()->count();
    }

    public function countByRole(string $role): int
    {
        return $this->model->filterByRole($role)->count();
    }

    /** @return array<string, mixed> */
    public function transform(User $user): array
    {
        $user->loadMissing(['roles']);

        return [
            'id' => $user->id,
            'username' => $user->username,
            'fullname' => $user->fullname,
            'age' => $user->age,
            'email' => $user->email,
            'role' => $user->roles->pluck('name')->first(),
            'is_active' => $user->isActive(),
            'created_at' => $user->created_at?->toDateString(),
        ];
    }
}
