<?php

namespace App\Repositories\Eloquent;

use App\Models\UserInvitation;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * @extends BaseRepository<UserInvitation>
 */
class UserInvitationRepository extends BaseRepository
{
    public function __construct(UserInvitation $userInvitation)
    {
        parent::__construct($userInvitation);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, UserInvitation>
     */
    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->model->newQuery()
            ->with(['role:id,name', 'invitedBy:id,first_name,middle_name,last_name,suffix'])
            ->latest()
            ->paginate($perPage);
    }

    public function findByToken(string $token): ?UserInvitation
    {
        return $this->model->whereToken($token)->first();
    }

    public function findByEmail(string $email): ?UserInvitation
    {
        return $this->model->whereEmail($email)->first();
    }

    public function markAccepted(UserInvitation $invitation): void
    {
        $invitation->update(['accepted_at' => now()]);
    }

    public function pruneExpired(): int
    {
        return $this->model->newQuery()
            ->whereNull('accepted_at')
            ->where('expires_at', '<', now())
            ->delete();
    }

    /** @return array<string, mixed> */
    public function transform(UserInvitation $invitation): array
    {
        $status = match (true) {
            $invitation->accepted_at !== null => 'accepted',
            $invitation->expires_at->isPast() => 'expired',
            default => 'pending',
        };

        return [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role?->name,
            'status' => $status,
            'invited_by' => $invitation->invitedBy?->fullname,
            'expires_at' => $invitation->expires_at->toDateString(),
            'accepted_at' => $invitation->accepted_at?->toDateString(),
        ];
    }

    public function updateEmail(string $oldEmail, string $newEmail): int
    {
        return $this->model->newQuery()->whereEmail($oldEmail)->update(['email' => $newEmail]);
    }
}
