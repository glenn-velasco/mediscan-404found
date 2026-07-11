<?php

namespace App\Models\Builders;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<User>
 */
class UserBuilder extends Builder
{
    public function search(string $term): static
    {
        return $this->where(function ($q) use ($term) {
            $q->where('users.email', 'ilike', "%{$term}%")
                ->orWhere('users.username', 'ilike', "%{$term}%")
                ->orWhere('users.first_name', 'ilike', "%{$term}%")
                ->orWhere('users.middle_name', 'ilike', "%{$term}%")
                ->orWhere('users.last_name', 'ilike', "%{$term}%")
                ->orWhereRaw(
                    "trim(regexp_replace(coalesce(users.first_name, '') || ' ' || coalesce(users.middle_name, '') || ' ' || coalesce(users.last_name, '') || ' ' || coalesce(users.suffix, ''), '\\s+', ' ', 'g')) ilike ?",
                    ["%{$term}%"]
                );
        });
    }

    public function filterByRole(string $role): static
    {
        return $this->whereHas('roles', fn ($r) => $r->where('name', $role));
    }

    public function filterByStatus(string $status): self
    {
        return match ($status) {
            'active' => $this->whereNull('users.deactivated_at'),
            'deactivated' => $this->whereNotNull('users.deactivated_at'),
            default => $this,
        };
    }

    public function whereActive(): self
    {
        return $this->whereNull('deactivated_at');
    }

    public function filterByAge(int $min, ?int $max = null): static
    {
        $this->whereRaw('extract(year from age(users.dob)) >= ?', [$min]);

        if ($max !== null) {
            $this->whereRaw('extract(year from age(users.dob)) <= ?', [$max]);
        }

        return $this;
    }
}
