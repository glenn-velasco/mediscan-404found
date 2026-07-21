<?php

namespace App\Enums;

use App\Traits\HasEnumHelpers;

enum ReportCategory: string
{
    use HasEnumHelpers;

    case Authentication = 'authentication';
    case ProfessionalApplications = 'professional-applications';
    case AccountRetrieval = 'account-retrieval';
    case UserManagement = 'user-management';
    case MedicalRecords = 'medical-records';

    public function label(): string
    {
        return match ($this) {
            self::Authentication => 'Authentication',
            self::ProfessionalApplications => 'Professional Applications',
            self::AccountRetrieval => 'Account Retrieval',
            self::UserManagement => 'User Management',
            self::MedicalRecords => 'Medical Records',
        };
    }

    /**
     * The `action` prefixes (before the first dot) that fall under this
     * category, matched via `action LIKE '{prefix}.%'`.
     *
     * @return array<int, string>
     */
    public function actionPrefixes(): array
    {
        return match ($this) {
            self::Authentication => ['auth'],
            self::ProfessionalApplications => ['professional_application'],
            self::AccountRetrieval => ['account_retrieval_request', 'medical_information_registration_match'],
            self::UserManagement => ['user', 'invitation', 'device_key'],
            self::MedicalRecords => ['medical_information', 'envelope', 'qr', 'emergency_qr'],
        };
    }
}
