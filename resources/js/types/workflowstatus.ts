// Always check for typos and sync it with app/Enums/WorkflowStatus.php
export const WorkflowStatus = {
    Processing: 'processing',
    PendingReview: 'pending_review',
    Pending: 'pending',
    Approved: 'approved',
    Denied: 'denied',
    AutoRejected: 'auto_rejected',
    Acknowledged: 'acknowledged',
    Expired: 'expired',
    Unclaimed: 'unclaimed',
} as const;

export type WorkflowStatus =
    (typeof WorkflowStatus)[keyof typeof WorkflowStatus];

export const WorkflowStatusLabel: Record<WorkflowStatus, string> = {
    processing: 'Processing',
    pending_review: 'Pending Review',
    pending: 'Pending',
    approved: 'Approved',
    denied: 'Denied',
    auto_rejected: 'Auto-Rejected',
    acknowledged: 'Acknowledged',
    expired: 'Expired',
    unclaimed: 'Unclaimed',
};

export const workflowStatusBadgeVariant: Record<
    WorkflowStatus,
    'default' | 'secondary' | 'destructive'
> = {
    processing: 'secondary',
    pending_review: 'secondary',
    pending: 'secondary',
    approved: 'default',
    denied: 'destructive',
    auto_rejected: 'destructive',
    acknowledged: 'default',
    expired: 'destructive',
    unclaimed: 'destructive',
};
