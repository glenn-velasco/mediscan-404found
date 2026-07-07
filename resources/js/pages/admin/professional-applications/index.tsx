import { Head, Link, router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { Eye } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import admin from '@/routes/admin';
import type { Paginated, ProfessionalApplicationStatus } from '@/types';
import { professionalApplicationStatusBadgeVariant } from '@/types';

interface ApplicationListItem {
    id: number;
    applicant: { id: number; name: string | null; email: string };
    profession: string | null;
    specialty: string | null;
    status: ProfessionalApplicationStatus;
    created_at: string | null;
}

interface IndexProps {
    applications: Paginated<ApplicationListItem>;
    filters: { status?: string };
}

export default function Index({ applications, filters }: IndexProps) {
    useEcho('admin-dashboard', '.ProfessionalApplicationStatusChanged', () =>
        router.reload(),
    );

    function onStatusChange(value: string) {
        router.get(
            admin.professionalApplications.index().url,
            { status: value || undefined },
            { preserveState: true, replace: true },
        );
    }

    return (
        <>
            <Head title="Professional Applications" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-end gap-2">
                    <Select
                        value={filters.status ?? ''}
                        onValueChange={onStatusChange}
                    >
                        <SelectTrigger className="w-48">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="">All statuses</SelectItem>
                            <SelectItem value="processing">
                                Processing
                            </SelectItem>
                            <SelectItem value="pending_review">
                                Pending Review
                            </SelectItem>
                            <SelectItem value="approved">Approved</SelectItem>
                            <SelectItem value="denied">Denied</SelectItem>
                            <SelectItem value="auto_rejected">
                                Auto-Rejected
                            </SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Applicant
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Profession
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Submitted
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {applications.data.map((application) => (
                                <tr
                                    key={application.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-4 py-3">
                                        <div className="font-medium">
                                            {application.applicant.name ?? '—'}
                                        </div>
                                        <div className="text-muted-foreground">
                                            {application.applicant.email}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {application.profession ?? '—'}
                                        {application.specialty &&
                                            ` (${application.specialty})`}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            variant={
                                                professionalApplicationStatusBadgeVariant[
                                                    application.status
                                                ]
                                            }
                                            className="capitalize"
                                        >
                                            {application.status.replace(
                                                /_/g,
                                                ' ',
                                            )}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {application.created_at ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={admin.professionalApplications.show(
                                                application.id,
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
                            {applications.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        No applications found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-1">
                    {applications.links.map((link, index) =>
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
            title: 'Professional Applications',
            href: admin.professionalApplications.index(),
        },
    ],
};
