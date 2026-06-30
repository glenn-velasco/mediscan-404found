import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';
import UsersLayout from '@/layouts/user-layout';
import type { BreadcrumbItem } from '@/types';

export default function AdaptiveAppLayout({
    children,
    breadcrumbs = [],
}: {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}) {
    const { auth } = usePage().props;
    const isAdmin = auth.roles?.includes('admin');

    if (isAdmin) {
        return <AppLayout breadcrumbs={breadcrumbs}>{children}</AppLayout>;
    }

    return <UsersLayout>{children}</UsersLayout>;
}
