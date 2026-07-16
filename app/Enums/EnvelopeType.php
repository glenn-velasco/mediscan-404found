<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum EnvelopeType: string
{
    use HasEnumHelpers;

    case AllergyVerification = 'allergy_verification';
    case TransfusionWitness = 'transfusion_witness';
    case GeneralNote = 'general_note';
    case DiagnosisVerification = 'diagnosis_verification';
    case MedicalInformationUpdate = 'medical_information_update';

    public function label(): string
    {
        return match ($this) {
            self::AllergyVerification => 'Allergy Verification',
            self::TransfusionWitness => 'Transfusion Witness',
            self::GeneralNote => 'General Note',
            self::DiagnosisVerification => 'Diagnosis Verification',
            self::MedicalInformationUpdate => 'Medical Information Update',
        };
    }
}
