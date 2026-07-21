import { Head, router } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import TrendChart from '@/components/trend-chart';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import admin from '@/routes/admin';

interface TrendPoint {
    date: string;
    value: number;
}

interface DashboardTrends {
    signups: TrendPoint[];
    qr_scans: TrendPoint[];
    logins: TrendPoint[];
    total_accounts: TrendPoint[];
    total_users: TrendPoint[];
    total_admins: TrendPoint[];
    active: TrendPoint[];
    deactivated: TrendPoint[];
}

interface DashboardFilters {
    from: string;
    to: string;
    earliest: string | null;
    latest: string;
}

export default function AdminDashboard({
    trends,
    filters,
}: {
    trends: DashboardTrends;
    filters: DashboardFilters;
}) {
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    useEffect(() => {
        if (from === filters.from && to === filters.to) {
            return;
        }

        const timer = setTimeout(() => {
            router.get(
                admin.dashboard().url,
                { from, to },
                { preserveState: true, replace: true },
            );
        }, 500);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [from, to]);

    const rangeLabel = `${filters.from.replace('T', ' ')} – ${filters.to.replace('T', ' ')}`;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-end gap-2">
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="dashboard-from">From</Label>
                        <Input
                            id="dashboard-from"
                            type="datetime-local"
                            value={from}
                            min={filters.earliest ?? undefined}
                            max={to || filters.latest}
                            onChange={(e) => setFrom(e.target.value)}
                            className="w-56"
                        />
                    </div>
                    <div className="flex flex-col gap-1">
                        <Label htmlFor="dashboard-to">To</Label>
                        <Input
                            id="dashboard-to"
                            type="datetime-local"
                            value={to}
                            min={from || filters.earliest || undefined}
                            max={filters.latest}
                            onChange={(e) => setTo(e.target.value)}
                            className="w-56"
                        />
                    </div>
                </div>
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <TrendChart
                        title="Signups"
                        subtitle={rangeLabel}
                        data={trends.signups}
                    />
                    <TrendChart
                        title="QR Scans"
                        subtitle={rangeLabel}
                        data={trends.qr_scans}
                    />
                    <TrendChart
                        title="Logins"
                        subtitle={rangeLabel}
                        data={trends.logins}
                    />
                    <TrendChart
                        title="Total Accounts"
                        subtitle={rangeLabel}
                        data={trends.total_accounts}
                    />
                    <TrendChart
                        title="Total Users"
                        subtitle={rangeLabel}
                        data={trends.total_users}
                    />
                    <TrendChart
                        title="Total Admins"
                        subtitle={rangeLabel}
                        data={trends.total_admins}
                    />
                    <TrendChart
                        title="Active"
                        subtitle={rangeLabel}
                        data={trends.active}
                    />
                    <TrendChart
                        title="Deactivated"
                        subtitle={rangeLabel}
                        data={trends.deactivated}
                    />
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: admin.dashboard() }],
};
