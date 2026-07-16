import { Head } from '@inertiajs/react';
import { useState } from 'react';
import StatCard from '@/components/stat-card';
import admin from '@/routes/admin';

interface DashboardStats {
    total: number;
    active: number;
    deactivated: number;
    by_role: { [key: string]: number };
}

export default function AdminDashboard({
    stats: initialStats,
}: {
    stats: DashboardStats;
}) {
    const [stats] = useState<DashboardStats>(initialStats);

    const adminCount =
        stats.by_role && typeof stats.by_role === 'object'
            ? (stats.by_role['admin'] ?? 0)
            : 0;

    const userCount =
        stats.by_role && typeof stats.by_role === 'object'
            ? (stats.by_role['user'] ?? 0)
            : 0;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <StatCard title="Total Accounts" value={stats.total} />
                    <StatCard title="Total Users" value={userCount} />
                    <StatCard title="Total Admins" value={adminCount} />
                    <StatCard title="Active" value={stats.active} />
                    <StatCard title="Deactivated" value={stats.deactivated} />
                </div>
            </div>
        </>
    );
}

AdminDashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: admin.dashboard() }],
};
