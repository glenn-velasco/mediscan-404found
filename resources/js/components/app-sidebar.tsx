import {
    FileText,
    IdCard,
    KeyRound,
    LayoutGrid,
    Mail,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import admin from '@/routes/admin';
import type { NavItem } from '@/types';
import { reportCategoryOptions } from '@/types/report-category';

export function AppSidebar() {
    const { open } = useSidebar();

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: admin.dashboard(),
            icon: LayoutGrid,
        },
        {
            title: 'Users',
            href: admin.users.index(),
            icon: Users,
        },
        {
            title: 'Invitations',
            href: admin.invitations.index(),
            icon: Mail,
        },
        {
            title: 'Professional Applications',
            href: admin.professionalApplications.index(),
            icon: IdCard,
        },
        {
            title: 'Account Retrieval Requests',
            href: admin.accountRetrievalRequests.index(),
            icon: KeyRound,
        },
        {
            title: 'Reports',
            href: admin.reports.show(reportCategoryOptions[0].value),
            icon: FileText,
            items: reportCategoryOptions.map((category) => ({
                title: category.label,
                href: admin.reports.show(category.value),
            })),
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <div className="px-2 pb-2">
                            <AppLogo
                                href={admin.dashboard()}
                                prefetch
                                sidebar={open}
                            />
                        </div>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
