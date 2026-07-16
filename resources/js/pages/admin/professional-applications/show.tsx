import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
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
import type { ProfessionalApplicationStatus } from '@/types';
import { professionalApplicationStatusBadgeVariant } from '@/types';

interface ApplicationDetail {
    id: number;
    applicant: { id: number; name: string | null; email: string };
    id_type: string;
    issuing_country: string;
    profession: string | null;
    full_name_on_id: string | null;
    license_number: string | null;
    license_expiry: string | null;
    status: ProfessionalApplicationStatus;
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
    files: { id_photo: string; selfie: string; coe: string };
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

export default function Show({ application, files }: ShowProps) {
    const [rejectOpen, setRejectOpen] = useState(false);
    const approveForm = useForm({});
    const rejectForm = useForm({ rejection_reason: '' });

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
                                    professionalApplicationStatusBadgeVariant[
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

                        <div className="grid grid-cols-1 gap-4 px-6 py-4 sm:grid-cols-3">
                            <div className="flex flex-col gap-2">
                                <span className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                    ID Photo
                                </span>
                                <a
                                    href={files.id_photo}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <img
                                        src={files.id_photo}
                                        alt="Professional ID"
                                        className="rounded-lg border"
                                    />
                                </a>
                            </div>
                            <div className="flex flex-col gap-2">
                                <span className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                    Selfie
                                </span>
                                <a
                                    href={files.selfie}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <img
                                        src={files.selfie}
                                        alt="Selfie"
                                        className="rounded-lg border"
                                    />
                                </a>
                            </div>
                            <div className="flex flex-col gap-2">
                                <span className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                    Certificate of Employment
                                </span>
                                <a
                                    href={files.coe}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="text-sm text-primary underline"
                                >
                                    View document
                                </a>
                            </div>
                        </div>

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
