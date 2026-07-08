// Always check for typos and sync it with app/Enums/ProfessionalApplicationStatus.php
export const ProfessionalApplicationStatus = {
    Processing: 'processing',
    PendingReview: 'pending_review',
    Approved: 'approved',
    Denied: 'denied',
    AutoRejected: 'auto_rejected',
} as const;

export type ProfessionalApplicationStatus =
    (typeof ProfessionalApplicationStatus)[keyof typeof ProfessionalApplicationStatus];

export const ProfessionalApplicationStatusLabel: Record<
    ProfessionalApplicationStatus,
    string
> = {
    processing: 'Processing',
    pending_review: 'Pending Review',
    approved: 'Approved',
    denied: 'Denied',
    auto_rejected: 'Auto-Rejected',
};

export const professionalApplicationStatusBadgeVariant: Record<
    ProfessionalApplicationStatus,
    'default' | 'secondary' | 'destructive'
> = {
    processing: 'secondary',
    pending_review: 'secondary',
    approved: 'default',
    denied: 'destructive',
    auto_rejected: 'destructive',
};
