<?php

namespace App\Policies;

use App\Models\MedicalInformationRegistrationMatch;
use App\Models\User;

class MedicalInformationRegistrationMatchPolicy
{
    /**
     * Only the candidate record's primary user may decide a match - the
     * requester never gets a vote, and 404 (never 403) on failure so this
     * never confirms a match's existence to anyone but its intended
     * recipient.
     */
    public function decide(User $user, MedicalInformationRegistrationMatch $registrationMatch): bool
    {
        return $registrationMatch->candidate->primary_user_id === $user->id;
    }
}
