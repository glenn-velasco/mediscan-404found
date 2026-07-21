// Always check for typos and sync it with app/Enums/ReportCategory.php
export const ReportCategory = {
    Authentication: 'authentication',
    ProfessionalApplications: 'professional-applications',
    AccountRetrieval: 'account-retrieval',
    UserManagement: 'user-management',
    MedicalRecords: 'medical-records',
} as const;

export type ReportCategory =
    (typeof ReportCategory)[keyof typeof ReportCategory];

export const ReportCategoryLabel: Record<ReportCategory, string> = {
    authentication: 'Authentication',
    'professional-applications': 'Professional Applications',
    'account-retrieval': 'Account Retrieval',
    'user-management': 'User Management',
    'medical-records': 'Medical Records',
};

export const reportCategoryOptions = (
    Object.values(ReportCategory) as ReportCategory[]
).map((value) => ({
    value,
    label: ReportCategoryLabel[value],
}));
