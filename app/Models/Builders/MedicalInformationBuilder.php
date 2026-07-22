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
     * Name/dob matching no longer runs as a SQL predicate here.
     * `first_name`/`middle_name`/`last_name`/`suffix`/`dob` are `encrypted`
     * casts (see `MedicalInformation::casts()`) - the DB only ever sees
     * ciphertext, so `ilike`/`whereDate` against these columns can't match
     * anything. `MedicalInformationRepository::findMatchingByName()` does
     * the equivalent comparison in PHP after Eloquent decrypts each row.
     */
    public function forUser(int $userId): static
    {
        return $this->whereHas('users', fn ($q) => $q->whereKey($userId));
    }
}
