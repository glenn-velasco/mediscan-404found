<?php

namespace App\Models;

use App\Models\Builders\UserInvitationBuilder;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable('email', 'role_id', 'token', 'invited_by', 'expires_at', 'accepted_at')]
#[UseEloquentBuilder(UserInvitationBuilder::class)]
class UserInvitation extends Model
{
    use MassPrunable;

    public function prunable(): Builder
    {
        return static::query()->where(fn ($q) =>
            $q->whereNotNull('accepted_at')
              ->orWhere(fn ($q) =>
                  $q->whereNull('accepted_at')->where('expires_at', '<', now())
              )
        );
    }

    protected function casts(): array
    {
        return [
            'expires_at'  => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function isValid(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
