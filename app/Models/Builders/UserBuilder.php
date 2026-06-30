<?php

namespace App\Models\Builders;

use Illuminate\Database\Eloquent\Builder;

class UserBuilder extends Builder
{
    public function search(string $term): static
    {
        return $this->where(function ($q) use ($term) {
            $q->where('users.email', 'ilike', "%{$term}%")
                ->orWhere('mi.full_name', 'ilike', "%{$term}%");
        });
    }

    public function filterByRole(string $role): static
    {
        return $this->whereHas('roles', fn ($r) => $r->where('name', $role));
    }

    public function filterByStatus(string $status): static
    {
        return match ($status) {
            'active' => $this->whereNull('users.deactivated_at'),
            'deactivated' => $this->whereNotNull('users.deactivated_at'),
            default => $this,
        };
    }

    public function whereActive(): static
    {
        return $this->whereNull('deactivated_at');
    }
}
