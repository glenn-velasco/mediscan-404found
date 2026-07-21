import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { CopyableField } from '@/components/copyable-field';
import ImageViewerModal from '@/components/image-viewer-modal';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { formatDate, formatDateTime } from '@/lib/utils';
import admin from '@/routes/admin';
import type { WorkflowStatus } from '@/types';
import { workflowStatusBadgeVariant } from '@/types';

const PRC_VERIFICATION_URL = 'https://verification.prc.gov.ph/';

interface ApplicationDetail {
    id: number;
    applicant: { id: number; name: string | null; email: string };
    id_type: string;
    issuing_country: string;
    profession: string | null;
    full_name_on_id: string | null;
    date_of_birth: string | null;
    license_number: string | null;
    license_expiry: string | null;
    status: WorkflowStatus;
    face_match_score: number | null;
    face_match_passed: boolean | null;
    liveness_score: number | null;
    liveness_passed: boolean | null;
    rejection_reason: string | null;
    verification_notes: string | null;
    role_granted: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    created_at: string | null;
}

const idTypeLabels: Record<string, string> = {
    ph_prc: 'PRC ID',
};

interface ShowProps {
    application: ApplicationDetail;
    files: { id_photo: string; selfie: string };
}

function Field({
    label,
    value,
}: {
    label: string;
    value: string | number | null;
}) {
    return (
        <div className="flex flex-col gap-1">
            <span className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                {label}
            </span>
            <span className="text-sm font-medium text-foreground">
                {value ?? '—'}
            </span>
        </div>
    );
}

function PrcVerificationModal({
    open,
    onOpenChange,
    application,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    application: ApplicationDetail;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex h-[90vh] max-h-[90vh] w-[95vw] max-w-[95vw] flex-col gap-3 sm:max-w-[95vw]">
                <DialogHeader>
                    <DialogTitle>PRC Verification</DialogTitle>
                </DialogHeader>

                <div className="flex flex-1 flex-col gap-4 overflow-hidden lg:flex-row">
                    <div className="flex flex-col gap-3 lg:w-80 lg:shrink-0">
                        <div className="grid grid-cols-2 gap-4 rounded-lg border p-4 lg:grid-cols-1">
                            <CopyableField
                                label="ID Type"
                                value={
                                    idTypeLabels[application.id_type] ??
                                    application.id_type
                                }
                            />
                            <CopyableField
                                label="Issuing Country"
                                value={application.issuing_country}
                            />
                            <CopyableField
                                label="Profession"
                                value={application.profession}
                            />
                            <CopyableField
                                label="Full Name on ID"
                                value={application.full_name_on_id}
                            />
                            <CopyableField
                                label="Date of Birth"
                                value={formatDate(application.date_of_birth)}
                            />
                            <CopyableField
                                label="License Number"
                                value={application.license_number}
                            />
                            <CopyableField
                                label="License Expiry"
                                value={formatDate(application.license_expiry)}
                            />
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Cross-check the license number against the
                            PRC&apos;s own verification portal. If the embedded
                            page appears blank, PRC&apos;s site is blocking
                            being framed — use the link instead.
                        </p>
                        <a
                            href={PRC_VERIFICATION_URL}
                            target="_blank"
                            rel="noreferrer"
                            className="text-sm text-primary underline"
                        >
                            Open in new tab ↗
                        </a>
                    </div>

                    <iframe
                        src={PRC_VERIFICATION_URL}
                        title="PRC License Verification"
                        className="h-full min-h-[50vh] w-full flex-1 rounded-lg border"
                        sandbox="allow-scripts allow-same-origin allow-forms allow-popups"
                        referrerPolicy="no-referrer"
                    />
                </div>
            </DialogContent>
        </Dialog>
    );
}

export default function Show({ application, files }: ShowProps) {
    const [rejectOpen, setRejectOpen] = useState(false);
    const [viewerIndex, setViewerIndex] = useState<number | null>(null);
    const [prcVerificationOpen, setPrcVerificationOpen] = useState(false);
    const approveForm = useForm({});
    const rejectForm = useForm({ rejection_reason: '' });

    const viewerImages = [
        { src: files.id_photo, alt: 'Professional ID' },
        { src: files.selfie, alt: 'Selfie' },
    ];

    useEcho('admin-dashboard', '.ProfessionalApplicationStatusChanged', () =>
        router.reload(),
    );

    const canReview =
        application.status === 'pending_review' ||
        application.status === 'processing';

    function approve() {
        approveForm.patch(
            admin.professionalApplications.approve(application.id).url,
        );
    }

    function reject(e: FormEvent) {
        e.preventDefault();
        rejectForm.patch(
            admin.professionalApplications.reject(application.id).url,
            {
                onSuccess: () => setRejectOpen(false),
            },
        );
    }

    return (
        <>
            <Head title={`Application — ${application.applicant.email}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardContent className="divide-y p-0">
                        <div className="flex items-center justify-between px-6 py-4">
                            <div className="space-y-1">
                                <p className="font-medium">
                                    {application.applicant.name ?? '—'}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {application.applicant.email}
                                </p>
                                {application.created_at && (
                                    <p className="text-sm text-muted-foreground">
                                        Submitted{' '}
                                        {formatDateTime(application.created_at)}
                                    </p>
                                )}
                            </div>
                            <Badge
                                variant={
                                    workflowStatusBadgeVariant[
                                        application.status
                                    ]
                                }
                            >
                                {application.status.replace(/_/g, ' ')}
                            </Badge>
                        </div>

                        <div className="grid grid-cols-2 gap-4 px-6 py-4 sm:grid-cols-3">
                            <Field
                                label="ID Type"
                                value={
                                    idTypeLabels[application.id_type] ??
                                    application.id_type
                                }
                            />
                            <Field
                                label="Issuing Country"
                                value={application.issuing_country}
                            />
                            <Field
                                label="Profession"
                                value={application.profession}
                            />
                            <Field
                                label="Full Name on ID"
                                value={application.full_name_on_id}
                            />
                            <Field
                                label="Date of Birth"
                                value={formatDate(application.date_of_birth)}
                            />
                            <Field
                                label="License Number"
                                value={application.license_number}
                            />
                            <Field
                                label="License Expiry"
                                value={formatDate(application.license_expiry)}
                            />
                            <Field
                                label="Face Match Score"
                                value={
                                    application.face_match_score !== null
                                        ? `${(application.face_match_score * 100).toFixed(1)}% ${application.face_match_passed ? '(passed)' : '(failed)'}`
                                        : '—'
                                }
                            />
                            <Field
                                label="Liveness Check"
                                value={
                                    application.liveness_score !== null
                                        ? `${(application.liveness_score * 100).toFixed(1)}% ${application.liveness_passed ? '(live)' : '(failed)'}`
                                        : 'Not checked'
                                }
                            />
                        </div>

                        <div className="grid grid-cols-1 gap-4 px-6 py-4 sm:grid-cols-2">
                            <div className="flex flex-col gap-2">
                                <span className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                    ID Photo
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setViewerIndex(0)}
                                    className="cursor-zoom-in"
                                >
                                    <img
                                        src={files.id_photo}
                                        alt="Professional ID"
                                        className="rounded-lg border"
                                    />
                                </button>
                            </div>
                            <div className="flex flex-col gap-2">
                                <span className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                    Selfie
                                </span>
                                <button
                                    type="button"
                                    onClick={() => setViewerIndex(1)}
                                    className="cursor-zoom-in"
                                >
                                    <img
                                        src={files.selfie}
                                        alt="Selfie"
                                        className="rounded-lg border"
                                    />
                                </button>
                            </div>
                        </div>

                        {application.id_type === 'ph_prc' && (
                            <div className="px-6 py-4">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setPrcVerificationOpen(true)}
                                >
                                    Verify against PRC
                                </Button>
                            </div>
                        )}

                        {application.verification_notes && (
                            <div className="px-6 py-4">
                                <p className="text-sm text-muted-foreground">
                                    {application.verification_notes}
                                </p>
                            </div>
                        )}

                        {application.rejection_reason && (
                            <div className="px-6 py-4">
                                <p className="mb-1 text-sm font-medium">
                                    Rejection reason
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {application.rejection_reason}
                                </p>
                            </div>
                        )}

                        {application.reviewed_by && (
                            <div className="px-6 py-4">
                                <p className="text-sm text-muted-foreground">
                                    Reviewed by {application.reviewed_by} on{' '}
                                    {formatDateTime(application.reviewed_at)}
                                </p>
                            </div>
                        )}

                        {canReview && (
                            <div className="flex gap-2 px-6 py-4">
                                <Button
                                    onClick={approve}
                                    disabled={approveForm.processing}
                                >
                                    Approve
                                </Button>
                                <Button
                                    variant="destructive"
                                    onClick={() => setRejectOpen(true)}
                                >
                                    Deny
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={rejectOpen} onOpenChange={setRejectOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Deny application?</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={reject} className="flex flex-col gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="rejection_reason">Reason</Label>
                            <Textarea
                                id="rejection_reason"
                                value={rejectForm.data.rejection_reason}
                                onChange={(e) =>
                                    rejectForm.setData(
                                        'rejection_reason',
                                        e.target.value,
                                    )
                                }
                                placeholder="Explain why this application is being denied…"
                                autoFocus
                            />
                            <InputError
                                message={rejectForm.errors.rejection_reason}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={rejectForm.processing}
                            >
                                Deny application
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ImageViewerModal
                images={viewerImages}
                initialIndex={viewerIndex ?? 0}
                open={viewerIndex !== null}
                onOpenChange={(open) => !open && setViewerIndex(null)}
            />

            <PrcVerificationModal
                open={prcVerificationOpen}
                onOpenChange={setPrcVerificationOpen}
                application={application}
            />
        </>
    );
}

Show.layout = ({ application }: ShowProps) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: admin.dashboard() },
        {
            title: 'Professional Applications',
            href: admin.professionalApplications.index(),
        },
        {
            title: application.applicant.email,
            href: admin.professionalApplications.show(application.id),
        },
    ],
});
