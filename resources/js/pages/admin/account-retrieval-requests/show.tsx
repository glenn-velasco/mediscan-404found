import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
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

interface RetrievalRequestDetail {
    id: number;
    requester: { id: number; fullname: string | null; email: string } | null;
    old_email: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    dob: string;
    status: WorkflowStatus;
    face_match_score: number | null;
    face_match_passed: boolean | null;
    verification_notes: string | null;
    rejection_reason: string | null;
    reviewed_by: string | null;
    reviewed_at: string | null;
    expires_at: string | null;
    created_at: string | null;
}

interface ShowProps {
    retrievalRequest: RetrievalRequestDetail;
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

export default function Show({ retrievalRequest, files }: ShowProps) {
    const [denyOpen, setDenyOpen] = useState(false);
    const [viewerIndex, setViewerIndex] = useState<number | null>(null);
    const approveForm = useForm({});
    const denyForm = useForm({ rejection_reason: '' });

    const viewerImages = [
        { src: files.id_photo, alt: 'Government ID' },
        { src: files.selfie, alt: 'Selfie' },
    ];

    useEcho('admin-dashboard', '.AccountRetrievalRequestStatusChanged', () =>
        router.reload(),
    );

    const canReview = retrievalRequest.status === 'pending';

    function approve() {
        approveForm.patch(
            admin.accountRetrievalRequests.approve(retrievalRequest.id).url,
        );
    }

    function deny(e: FormEvent) {
        e.preventDefault();
        denyForm.patch(
            admin.accountRetrievalRequests.deny(retrievalRequest.id).url,
            {
                onSuccess: () => setDenyOpen(false),
            },
        );
    }

    return (
        <>
            <Head title={`Retrieval Request — ${retrievalRequest.old_email}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardContent className="divide-y p-0">
                        <div className="flex items-center justify-between px-6 py-4">
                            <div className="space-y-1">
                                <p className="font-medium">
                                    {retrievalRequest.old_email}
                                </p>
                                {retrievalRequest.requester && (
                                    <p className="text-sm text-muted-foreground">
                                        Submitted by{' '}
                                        {retrievalRequest.requester.email}
                                    </p>
                                )}
                                {retrievalRequest.created_at && (
                                    <p className="text-sm text-muted-foreground">
                                        Submitted{' '}
                                        {formatDateTime(
                                            retrievalRequest.created_at,
                                        )}{' '}
                                        · expires{' '}
                                        {formatDateTime(
                                            retrievalRequest.expires_at,
                                        )}
                                    </p>
                                )}
                            </div>
                            <Badge
                                variant={
                                    workflowStatusBadgeVariant[
                                        retrievalRequest.status
                                    ]
                                }
                            >
                                {retrievalRequest.status.replace(/_/g, ' ')}
                            </Badge>
                        </div>

                        <div className="grid grid-cols-2 gap-4 px-6 py-4 sm:grid-cols-3">
                            <Field
                                label="First Name"
                                value={retrievalRequest.first_name}
                            />
                            <Field
                                label="Middle Name"
                                value={retrievalRequest.middle_name}
                            />
                            <Field
                                label="Last Name"
                                value={retrievalRequest.last_name}
                            />
                            <Field
                                label="Date of Birth"
                                value={formatDate(retrievalRequest.dob)}
                            />
                            <Field
                                label="Face Match Score"
                                value={
                                    retrievalRequest.face_match_score !== null
                                        ? `${(retrievalRequest.face_match_score * 100).toFixed(1)}% ${retrievalRequest.face_match_passed ? '(passed)' : '(failed)'}`
                                        : '—'
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
                                        alt="Government ID"
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

                        {retrievalRequest.verification_notes && (
                            <div className="px-6 py-4">
                                <p className="mb-1 text-sm font-medium">
                                    Verification notes
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {retrievalRequest.verification_notes}
                                </p>
                            </div>
                        )}

                        {retrievalRequest.rejection_reason && (
                            <div className="px-6 py-4">
                                <p className="mb-1 text-sm font-medium">
                                    Rejection reason
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {retrievalRequest.rejection_reason}
                                </p>
                            </div>
                        )}

                        {retrievalRequest.reviewed_by && (
                            <div className="px-6 py-4">
                                <p className="text-sm text-muted-foreground">
                                    Reviewed by {retrievalRequest.reviewed_by}{' '}
                                    on{' '}
                                    {formatDateTime(
                                        retrievalRequest.reviewed_at,
                                    )}
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
                                    onClick={() => setDenyOpen(true)}
                                >
                                    Deny
                                </Button>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>

            <Dialog open={denyOpen} onOpenChange={setDenyOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Deny retrieval request?</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={deny} className="flex flex-col gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="rejection_reason">Reason</Label>
                            <Textarea
                                id="rejection_reason"
                                value={denyForm.data.rejection_reason}
                                onChange={(e) =>
                                    denyForm.setData(
                                        'rejection_reason',
                                        e.target.value,
                                    )
                                }
                                placeholder="Explain why this request is being denied…"
                                autoFocus
                            />
                            <InputError
                                message={denyForm.errors.rejection_reason}
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={denyForm.processing}
                            >
                                Deny request
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
        </>
    );
}

Show.layout = ({ retrievalRequest }: ShowProps) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: admin.dashboard() },
        {
            title: 'Account Retrieval Requests',
            href: admin.accountRetrievalRequests.index(),
        },
        {
            title: retrievalRequest.old_email,
            href: admin.accountRetrievalRequests.show(retrievalRequest.id),
        },
    ],
});
