import { usePage } from '@inertiajs/react';
import { LayoutGrid, Mail, Users } from 'lucide-react';
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
import { dashboard } from '@/routes';
import admin from '@/routes/admin';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth } = usePage().props;
    const { open } = useSidebar();

    const isAdmin = auth.roles?.includes('admin');

    const dashboardHref = isAdmin ? admin.dashboard() : dashboard();

    const mainNavItems: NavItem[] = isAdmin
        ? [
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
          ]
        : [
              {
                  title: 'Dashboard',
                  href: dashboard(),
                  icon: LayoutGrid,
              },
          ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <div className="px-2 pb-2">
                            <AppLogo
                                href={dashboardHref}
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
