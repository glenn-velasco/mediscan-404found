<?php

namespace App\Repositories\Eloquent;

use App\Models\UserInvitation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class UserInvitationRepository extends BaseRepository
{
    public function __construct(UserInvitation $userInvitation)
    {
        parent::__construct($userInvitation);
    }

    public function paginate(int $perPage, array $filters = []): LengthAwarePaginator
    {
        return $this->model->query()
            ->with(['role:id,name', 'invitedBy:id,name'])
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
        return $this->model->query()
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
            default                           => 'pending',
        };

        return [
            'id'          => $invitation->id,
            'email'       => $invitation->email,
            'role'        => $invitation->role?->name,
            'status'      => $status,
            'invited_by'  => $invitation->invitedBy?->name,
            'expires_at'  => $invitation->expires_at->toDateString(),
            'accepted_at' => $invitation->accepted_at?->toDateString(),
        ];
    }

    public function updateEmail(string $oldEmail, string $newEmail)
    {
        return $this->model->query()->whereEmail($oldEmail)->update(['email' => $newEmail]);
    }
}
