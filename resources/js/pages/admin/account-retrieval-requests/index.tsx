import { Head, Link, router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { Eye } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/utils';
import admin from '@/routes/admin';
import type { Paginated, WorkflowStatus } from '@/types';
import { workflowStatusBadgeVariant } from '@/types';

interface RetrievalRequestListItem {
    id: number;
    requester: { id: number; fullname: string | null; email: string } | null;
    old_email: string;
    status: WorkflowStatus;
    expires_at: string | null;
    created_at: string | null;
}

interface IndexProps {
    requests: Paginated<RetrievalRequestListItem>;
}

function expiryVariant(
    expiresAt: string | null,
): 'outline' | 'secondary' | 'destructive' {
    if (!expiresAt) {
        return 'outline';
    }

    const hoursLeft = (new Date(expiresAt).getTime() - Date.now()) / 3_600_000;

    if (hoursLeft <= 24) {
        return 'destructive';
    }

    if (hoursLeft <= 48) {
        return 'secondary';
    }

    return 'outline';
}

export default function Index({ requests }: IndexProps) {
    useEcho('admin-dashboard', '.AccountRetrievalRequestStatusChanged', () =>
        router.reload(),
    );

    return (
        <>
            <Head title="Account Retrieval Requests" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Requester
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Old email
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Submitted
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Expires
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {requests.data.map((request) => (
                                <tr
                                    key={request.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-4 py-3">
                                        {request.requester ? (
                                            <>
                                                <div className="font-medium">
                                                    {request.requester
                                                        .fullname ?? '—'}
                                                </div>
                                                <div className="text-muted-foreground">
                                                    {request.requester.email}
                                                </div>
                                            </>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                Pre-registration
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        {request.old_email}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                workflowStatusBadgeVariant[
                                                    request.status
                                                ]
                                            }
                                            className="capitalize"
                                        >
                                            {request.status.replace(/_/g, ' ')}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatDateTime(request.created_at)}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={expiryVariant(
                                                request.expires_at,
                                            )}
                                        >
                                            {formatDateTime(request.expires_at)}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={admin.accountRetrievalRequests.show(
                                                request.id,
                                            )}
                                        >
                                            <Eye
                                                className="text-muted-foreground hover:text-foreground"
                                                size="1rem"
                                            />
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {requests.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        No retrieval requests found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-1">
                    {requests.links.map((link, index) =>
                        link.url ? (
                            <Button
                                key={index}
                                asChild
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                            >
                                <Link
                                    href={link.url}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                    preserveScroll
                                />
                            </Button>
                        ) : (
                            <Button
                                key={index}
                                size="sm"
                                variant="outline"
                                disabled
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ),
                    )}
                </div>
            </div>
        </>
    );
}

Index.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: admin.dashboard() },
        {
            title: 'Account Retrieval Requests',
            href: admin.accountRetrievalRequests.index(),
        },
    ],
};
