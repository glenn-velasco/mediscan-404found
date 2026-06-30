<?php

namespace App\Repositories\Eloquent;

use App\Models\MedicalInformation;
use App\Models\User;

class MedicalInformationRepository extends BaseRepository
{
    public function __construct(MedicalInformation $medicalInformation)
    {
        parent::__construct($medicalInformation);
    }

    public function findByUser(User $user): ?MedicalInformation
    {
        return $user->medicalInformation;
    }

    public function findWithRelationsByUser(User $user): ?MedicalInformation
    {
        return $user->medicalInformation()->with(['allergies', 'emergencyContacts'])->first();
    }

    public function upsertForUser(User $user, array $data): MedicalInformation
    {
        return $user->medicalInformation()->updateOrCreate(
            ['user_id' => $user->id],
            $data,
        );
    }
}
