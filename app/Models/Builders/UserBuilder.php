<?php

namespace App\Models\Builders;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<User>
 */
class UserBuilder extends Builder
{
    /**
     * Name fields are `encrypted` casts, so the DB can't filter/ilike on
     * them - every row (subject to constraints already on the query) is
     * fetched and compared in PHP after Eloquent decrypts it, then narrowed
     * back down to a `whereIn` so the rest of the builder chain (further
     * filters, pagination) keeps working normally. Mirrors
     * MedicalInformationRepository::findMatchingByName().
     */
    public function search(string $term): self
    {
        $needle = mb_strtolower($term);

        $matchingIds = $this->clone()->get()
            ->filter(function (User $user) use ($needle) {
                if (str_contains(mb_strtolower($user->email), $needle)) {
                    return true;
                }

                $fullName = collect([$user->first_name, $user->middle_name, $user->last_name, $user->suffix])
                    ->filter()
                    ->implode(' ');

                return str_contains(mb_strtolower($fullName), $needle);
            })
            ->pluck('id');

        return $this->whereIn('users.id', $matchingIds);
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

    /**
     * `dob` is encrypted at rest (via a manual accessor, since `encrypted`
     * doesn't compose with a date column), so age can't be computed in SQL -
     * filtered in PHP instead, same approach as search() above.
     */
    public function filterByAge(int $min, ?int $max = null): self
    {
        $matchingIds = $this->clone()->get()
            ->filter(function (User $user) use ($min, $max) {
                $age = $user->dob?->age;

                if ($age === null) {
                    return false;
                }

                return $age >= $min && ($max === null || $age <= $max);
            })
            ->pluck('id');

        return $this->whereIn('users.id', $matchingIds);
    }
}
