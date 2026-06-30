import { Link, usePage } from '@inertiajs/react';
import { LayoutGrid, Mail, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import { useInitials } from '@/hooks/use-initials';

export function AppSidebar() {
    const { auth } = usePage().props;
    const { state, open } = useSidebar();
    const getInitials = useInitials();
    
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
                            <AppLogo href={dashboardHref} prefetch sidebar={open} />
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
