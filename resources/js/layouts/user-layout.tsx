import { Link, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import {
    ChevronsUpDown,
    IdCard,
    LayoutGrid,
    LogOut,
    Settings,
} from 'lucide-react';
import type { ReactNode } from 'react';
import AppLogo from '@/components/app-logo';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAuth } from '@/hooks/use-auth';
import { useInitials } from '@/hooks/use-initials';
import { logout } from '@/routes';
import { edit as editAccount } from '@/routes/account';
import admin, { dashboard } from '@/routes/admin';
import professionalApplication from '@/routes/professional-application';
import { Role } from '@/types';

export default function UsersLayout({ children }: { children: ReactNode }) {
    const { auth } = usePage().props;
    const { hasRole } = useAuth();
    const getInitials = useInitials();
    const user = auth.user;

    useEcho(
        `App.Models.User.${user?.id}`,
        ['.UserDeactivated', '.UserDeleted'],
        () => router.post(logout.url()),
    );

    useEcho(`App.Models.User.${user?.id}`, '.EmailChanged', () =>
        router.reload({ only: ['auth'] }),
    );

    useEcho(`App.Models.User.${user?.id}`, '.RoleChanged', () =>
        router.reload({ only: ['auth'] }),
    );

    useEcho(
        `App.Models.User.${user?.id}`,
        '.ProfessionalApplicationStatusChanged',
        () => router.reload(),
    );

    return (
        <div className="min-h-screen bg-muted/30">
            {/* Top navbar */}
            <header className="sticky top-0 z-10 border-b bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60">
                <div className="mx-auto flex h-14 max-w-5xl items-center justify-between px-4 sm:px-6">
                    {/* Brand */}
                    <AppLogo href={auth ? dashboard() : '/'} />

                    {user && (
                        <div className="flex items-center gap-4">
                            {/* User menu */}
                            <DropdownMenu>
                                <DropdownMenuTrigger className="flex items-center gap-2 rounded-full p-1 ring-ring transition-shadow outline-none hover:bg-accent focus-visible:ring-2">
                                    <Avatar className="h-8 w-8">
                                        <AvatarImage
                                            src={user.avatar}
                                            alt={user.fullname || undefined}
                                        />
                                        <AvatarFallback className="bg-primary/10 text-xs font-medium text-primary">
                                            {getInitials(
                                                user.fullname || user.email,
                                            )}
                                        </AvatarFallback>
                                    </Avatar>
                                    <span className="hidden text-sm font-medium sm:block">
                                        {user.fullname || user.email}
                                    </span>
                                    <ChevronsUpDown className="h-3.5 w-3.5 text-muted-foreground" />
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="w-56"
                                >
                                    <DropdownMenuLabel className="p-0 font-normal">
                                        <div className="flex items-center gap-2 px-2 py-2">
                                            <Avatar className="h-8 w-8">
                                                <AvatarImage
                                                    src={user.avatar}
                                                    alt={
                                                        user.fullname ||
                                                        undefined
                                                    }
                                                />
                                                <AvatarFallback className="bg-primary/10 text-xs font-medium text-primary">
                                                    {getInitials(
                                                        user.fullname ||
                                                            user.email,
                                                    )}
                                                </AvatarFallback>
                                            </Avatar>
                                            <div className="grid flex-1 text-left text-sm leading-tight">
                                                <span className="truncate font-medium">
                                                    {user.fullname || '—'}
                                                </span>
                                                <span className="truncate text-xs text-muted-foreground">
                                                    {user.email}
                                                </span>
                                            </div>
                                        </div>
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuGroup>
                                        {hasRole(Role.Admin) && (
                                            <DropdownMenuItem asChild>
                                                <Link
                                                    href={admin.dashboard()}
                                                    className="cursor-pointer"
                                                >
                                                    <LayoutGrid className="mr-2 h-4 w-4" />
                                                    Admin Dashboard
                                                </Link>
                                            </DropdownMenuItem>
                                        )}
                                        <DropdownMenuItem asChild>
                                            <Link
                                                href={professionalApplication.show()}
                                                className="cursor-pointer"
                                            >
                                                <IdCard className="mr-2 h-4 w-4" />
                                                Professional Application
                                            </Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem asChild>
                                            <Link
                                                href={editAccount()}
                                                className="cursor-pointer"
                                            >
                                                <Settings className="mr-2 h-4 w-4" />
                                                Settings
                                            </Link>
                                        </DropdownMenuItem>
                                    </DropdownMenuGroup>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem asChild>
                                        <Link
                                            href={logout()}
                                            as="button"
                                            className="w-full cursor-pointer"
                                        >
                                            <LogOut className="mr-2 h-4 w-4" />
                                            Log out
                                        </Link>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    )}
                </div>
            </header>

            {/* Page content */}
            <main className="mx-auto max-w-5xl px-4 py-8 sm:px-6">
                {children}
            </main>
        </div>
    );
}
