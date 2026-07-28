import { Head, Link, router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { useEffect } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAuth } from '@/hooks/use-auth';
import { formatDateTime } from '@/lib/utils';
import professionalApplication from '@/routes/professional-application';
import type { WorkflowStatus } from '@/types';
import { workflowStatusBadgeVariant, WorkflowStatusLabel } from '@/types';

interface Application {
    id: number;
    id_type: string;
    issuing_country: string;
    profession: string | null;
    full_name_on_id: string | null;
    license_number: string | null;
    license_expiry: string | null;
    status: WorkflowStatus;
    rejection_reason: string | null;
    verification_notes: string | null;
    role_granted: string | null;
    created_at: string | null;
}

const idTypeLabels: Record<string, string> = {
    ph_prc: 'PRC ID',
};

function formatDate(value: string | null): string | null {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

interface ShowProps {
    application: Application | null;
}

export default function Show({ application }: ShowProps) {
    const { user } = useAuth();

    useEcho(
        `App.Models.User.${user?.id}`,
        '.ProfessionalApplicationStatusChanged',
        () => router.reload(),
    );

    useEffect(() => {
        const handleVisibilityChange = () => {
            if (document.visibilityState === 'visible') {
                router.reload({ only: ['application'] });
            }
        };
        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () =>
            document.removeEventListener(
                'visibilitychange',
                handleVisibilityChange,
            );
    }, []);

    const canResubmit =
        application === null ||
        application.status === 'denied' ||
        application.status === 'auto_rejected';

    return (
        <>
            <Head title="Professional Application" />
            <div className="mx-auto flex max-w-xl flex-col gap-6 p-4">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        Professional Application
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Track the status of your professional verification.
                    </p>
                </div>

                {application ? (
                    <Card>
                        <CardContent className="flex flex-col gap-4 p-6">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium">
                                        {application.profession ??
                                            'Professional application'}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Submitted{' '}
                                        {formatDateTime(application.created_at)}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        workflowStatusBadgeVariant[
                                            application.status
                                        ]
                                    }
                                >
                                    {WorkflowStatusLabel[application.status]}
                                </Badge>
                            </div>

                            <div className="grid grid-cols-2 gap-2 text-sm">
                                <div>
                                    <span className="text-muted-foreground">
                                        ID Type:
                                    </span>{' '}
                                    {idTypeLabels[application.id_type] ??
                                        application.id_type}
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Name on ID:
                                    </span>{' '}
                                    {application.full_name_on_id ?? '—'}
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        License:
                                    </span>{' '}
                                    {application.license_number ?? '—'}
                                </div>
                                <div>
                                    <span className="text-muted-foreground">
                                        Expiry:
                                    </span>{' '}
                                    {formatDate(application.license_expiry) ??
                                        '—'}
                                </div>
                            </div>

                            {application.status === 'approved' &&
                                application.role_granted && (
                                    <Alert>
                                        <AlertTitle>
                                            You&apos;re verified
                                        </AlertTitle>
                                        <AlertDescription>
                                            You&apos;ve been granted the{' '}
                                            <span className="font-medium capitalize">
                                                {application.role_granted.replace(
                                                    /-/g,
                                                    ' ',
                                                )}
                                            </span>{' '}
                                            role, and your selfie is now your
                                            profile picture.
                                        </AlertDescription>
                                    </Alert>
                                )}

                            {(application.status === 'denied' ||
                                application.status === 'auto_rejected') &&
                                application.rejection_reason && (
                                    <Alert variant="destructive">
                                        <AlertTitle>
                                            Application rejected
                                        </AlertTitle>
                                        <AlertDescription>
                                            {application.rejection_reason}
                                        </AlertDescription>
                                    </Alert>
                                )}

                            {application.verification_notes && (
                                <p className="text-xs text-muted-foreground">
                                    {application.verification_notes}
                                </p>
                            )}
                        </CardContent>
                    </Card>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        You haven&apos;t submitted a professional application
                        yet.
                    </p>
                )}

                {canResubmit && (
                    <Button asChild>
                        <Link href={professionalApplication.create().url}>
                            {application ? 'Resubmit application' : 'Apply now'}
                        </Link>
                    </Button>
                )}
            </div>
        </>
    );
}
