<?php

namespace App\Models\Builders;

use App\Models\MedicalInformation;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<MedicalInformation>
 */
class MedicalInformationBuilder extends Builder
{
    /**
     * Normalized-concatenation name match, same collapsing technique as
     * `UserBuilder::search()` but compared for equality (not partial ilike)
     * and additionally constrained to an exact `dob` match - name alone
     * (twins, common names) is not proof of identity, see the security
     * review notes on `MedicalInformationRepository::findMatchingByName()`.
     *
     * @param  array{first_name: string, middle_name: ?string, last_name: string, suffix: ?string}  $nameFields
     */
    public function matchingName(array $nameFields, string $dob): static
    {
        $normalized = trim(preg_replace(
            '/\s+/',
            ' ',
            implode(' ', array_filter([
                $nameFields['first_name'],
                $nameFields['middle_name'] ?? null,
                $nameFields['last_name'],
                $nameFields['suffix'] ?? null,
            ]))
        ));

        $this->whereRaw(
            "trim(regexp_replace(coalesce(first_name, '') || ' ' || coalesce(middle_name, '') || ' ' || coalesce(last_name, '') || ' ' || coalesce(suffix, ''), '\\s+', ' ', 'g')) ilike ?",
            [$normalized]
        )->whereDate('dob', $dob);

        return $this;
    }

    public function forUser(int $userId): static
    {
        return $this->whereHas('users', fn ($q) => $q->whereKey($userId));
    }

    /**
     * Simple per-field filtering, independent of `matchingName()` above.
     * Uses ilike for consistency with `UserBuilder::search()`'s convention.
     */
    public function whereName(
        ?string $firstName = null,
        ?string $middleName = null,
        ?string $lastName = null,
        ?string $suffix = null,
    ): static {
        return $this->when($firstName, fn ($q) => $q->where('first_name', 'ilike', $firstName))
            ->when($middleName, fn ($q) => $q->where('middle_name', 'ilike', $middleName))
            ->when($lastName, fn ($q) => $q->where('last_name', 'ilike', $lastName))
            ->when($suffix, fn ($q) => $q->where('suffix', 'ilike', $suffix));
    }
}
