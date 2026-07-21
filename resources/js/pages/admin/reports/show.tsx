import { Head, router } from '@inertiajs/react';
import { SlidersHorizontal } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDateTime } from '@/lib/utils';
import admin from '@/routes/admin';
import type { Paginated } from '@/types';

interface AuditLogListItem {
    id: number;
    action: string;
    description: string | null;
    type: string | null;
    actor: { id: number; name: string | null; email: string } | null;
    subject: { id: number; name: string | null; email: string } | null;
    channel: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
}

interface ReportUser {
    id: number;
    name: string | null;
    email: string;
}

interface ShowProps {
    category: string;
    categoryLabel: string;
    reports: Paginated<AuditLogListItem>;
    filters: { search: string; from: string; to: string; user_id: string };
}

export default function Show({
    category,
    categoryLabel,
    reports,
    filters,
}: ShowProps) {
    const [filterOpen, setFilterOpen] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [userId, setUserId] = useState(filters.user_id ?? '');
    const [selectedUser, setSelectedUser] = useState<ReportUser | null>(null);
    const [userQuery, setUserQuery] = useState('');
    const [userResults, setUserResults] = useState<ReportUser[]>([]);

    const hasActiveFilters = Boolean(search || from || to || userId);

    useEffect(() => {
        const timer = setTimeout(() => {
            router.get(
                admin.reports.show(category).url,
                {
                    search: search || undefined,
                    from: from || undefined,
                    to: to || undefined,
                    user_id: userId || undefined,
                },
                { preserveState: true, replace: true },
            );
        }, 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search, from, to, userId]);

    useEffect(() => {
        if (userQuery.trim() === '') {
            return;
        }

        const timer = setTimeout(() => {
            fetch(
                `${admin.reports.users.search(category).url}?q=${encodeURIComponent(userQuery)}`,
                { headers: { Accept: 'application/json' } },
            )
                .then((response) => response.json())
                .then((data: ReportUser[]) => setUserResults(data));
        }, 350);

        return () => clearTimeout(timer);
    }, [category, userQuery]);

    function selectUser(user: ReportUser) {
        setSelectedUser(user);
        setUserId(String(user.id));
        setUserQuery('');
        setUserResults([]);
    }

    function clearUser() {
        setSelectedUser(null);
        setUserId('');
        setUserQuery('');
        setUserResults([]);
    }

    return (
        <>
            <Head title={`${categoryLabel} Report`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h1 className="text-lg font-semibold">{categoryLabel}</h1>

                    <div className="flex items-center gap-2">
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search by action…"
                            className="w-64"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="icon"
                            className="relative"
                            onClick={() => setFilterOpen(true)}
                        >
                            <SlidersHorizontal className="size-4" />
                            {hasActiveFilters && (
                                <span className="absolute -top-1 -right-1 size-2 rounded-full bg-primary" />
                            )}
                        </Button>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium">
                                    Timestamp
                                </th>
                                <th className="px-4 py-3 font-medium">
                                    Description
                                </th>
                                <th className="px-4 py-3 font-medium">Type</th>
                                <th className="px-4 py-3 font-medium">Actor</th>
                                <th className="px-4 py-3 font-medium">
                                    Subject
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {reports.data.map((log) => (
                                <tr
                                    key={log.id}
                                    className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                                >
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {formatDateTime(log.created_at)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {log.description ?? log.action}
                                    </td>
                                    <td className="px-4 py-3 capitalize">
                                        {log.type ?? '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {log.actor?.name ??
                                            log.actor?.email ??
                                            '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        {log.subject?.name ??
                                            log.subject?.email ??
                                            '—'}
                                    </td>
                                </tr>
                            ))}
                            {reports.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-6 text-center text-muted-foreground"
                                    >
                                        No records found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex flex-wrap gap-1">
                    {reports.links.map((link, index) =>
                        link.url ? (
                            <Button
                                key={index}
                                asChild
                                size="sm"
                                variant={link.active ? 'default' : 'outline'}
                            >
                                <a
                                    href={link.url}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
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

            <Dialog open={filterOpen} onOpenChange={setFilterOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Filter report</DialogTitle>
                    </DialogHeader>
                    <div className="flex flex-col gap-4">
                        <div className="grid gap-2">
                            <Label htmlFor="user-search">User</Label>
                            {selectedUser ? (
                                <div className="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm">
                                    <span>
                                        {selectedUser.name ??
                                            selectedUser.email}
                                    </span>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={clearUser}
                                    >
                                        Change
                                    </Button>
                                </div>
                            ) : (
                                <div className="relative">
                                    <Input
                                        id="user-search"
                                        value={userQuery}
                                        onChange={(e) => {
                                            const value = e.target.value;
                                            setUserQuery(value);

                                            if (value.trim() === '') {
                                                setUserResults([]);
                                            }
                                        }}
                                        placeholder="Search by name or email…"
                                    />
                                    {userResults.length > 0 && (
                                        <div className="absolute z-10 mt-1 w-full rounded-md border bg-popover shadow-md">
                                            {userResults.map((user) => (
                                                <button
                                                    key={user.id}
                                                    type="button"
                                                    className="block w-full px-3 py-2 text-left text-sm hover:bg-muted"
                                                    onClick={() =>
                                                        selectUser(user)
                                                    }
                                                >
                                                    {user.name ?? user.email}
                                                </button>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="from">From</Label>
                                <Input
                                    id="from"
                                    type="date"
                                    value={from}
                                    onChange={(e) => setFrom(e.target.value)}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="to">To</Label>
                                <Input
                                    id="to"
                                    type="date"
                                    value={to}
                                    onChange={(e) => setTo(e.target.value)}
                                />
                            </div>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => {
                                setSearch('');
                                setFrom('');
                                setTo('');
                                clearUser();
                            }}
                        >
                            Clear filters
                        </Button>
                        <Button
                            type="button"
                            onClick={() => setFilterOpen(false)}
                        >
                            Apply
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Show.layout = ({ categoryLabel }: ShowProps) => ({
    breadcrumbs: [
        { title: 'Dashboard', href: admin.dashboard() },
        { title: 'Reports', href: admin.reports.show('authentication') },
        { title: categoryLabel, href: admin.dashboard() },
    ],
});
