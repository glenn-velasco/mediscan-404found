import { Head, Link, router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useAuth } from '@/hooks/use-auth';
import professionalApplication from '@/routes/professional-application';
import type { ProfessionalApplicationStatus } from '@/types';
import {
    professionalApplicationStatusBadgeVariant,
    ProfessionalApplicationStatusLabel,
} from '@/types';

interface Application {
    id: number;
    id_type: string;
    issuing_country: string;
    profession: string | null;
    specialty: string | null;
    status: ProfessionalApplicationStatus;
    rejection_reason: string | null;
    verification_notes: string | null;
    role_granted: string | null;
    created_at: string | null;
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
                                        {application.specialty &&
                                            ` — ${application.specialty}`}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Submitted{' '}
                                        {application.created_at ?? '—'}
                                    </p>
                                </div>
                                <Badge
                                    variant={
                                        professionalApplicationStatusBadgeVariant[
                                            application.status
                                        ]
                                    }
                                >
                                    {
                                        ProfessionalApplicationStatusLabel[
                                            application.status
                                        ]
                                    }
                                </Badge>
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
