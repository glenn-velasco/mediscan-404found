import { Link, router } from '@inertiajs/react';
import {
    IdCard,
    LayoutGrid,
    LogOut,
    Settings,
    Stethoscope,
} from 'lucide-react';
import {
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { UserInfo } from '@/components/user-info';
import { useAuth } from '@/hooks/use-auth';
import { useMobileNavigation } from '@/hooks/use-mobile-navigation';
import { dashboard, logout } from '@/routes';
import { edit as editAccount } from '@/routes/account';
import admin from '@/routes/admin';
import professional from '@/routes/professional';
import professionalApplication from '@/routes/professional-application';
import { Permission, Role } from '@/types';
import type { User } from '@/types';

type Props = {
    user: User;
};

export function UserMenuContent({ user }: Props) {
    const cleanup = useMobileNavigation();
    const { hasRole, hasPermission } = useAuth();
    const handleLogout = () => {
        cleanup();
        router.flushAll();
    };

    return (
        <>
            <DropdownMenuLabel className="p-0 font-normal">
                <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                    <UserInfo user={user} showEmail={true} />
                </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuGroup>
                {hasRole(Role.Admin) && (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={admin.dashboard()}
                            onClick={cleanup}
                        >
                            <LayoutGrid className="mr-2" />
                            Admin Dashboard
                        </Link>
                    </DropdownMenuItem>
                )}
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={dashboard()}
                        onClick={cleanup}
                    >
                        <LayoutGrid className="mr-2" />
                        Dashboard
                    </Link>
                </DropdownMenuItem>
                {hasPermission(Permission.VerifiedProfessional) && (
                    <DropdownMenuItem asChild>
                        <Link
                            className="block w-full cursor-pointer"
                            href={professional.patients.index()}
                            onClick={cleanup}
                        >
                            <Stethoscope className="mr-2" />
                            Patient Lookup
                        </Link>
                    </DropdownMenuItem>
                )}
                {hasPermission(Permission.VerifiedProfessional) ?
                    <DropdownMenuItem asChild>
                        <Link
                            href={professional.patients.index()}
                            className="cursor-pointer"
                        >
                            <Stethoscope className="mr-2 h-4 w-4" />
                            Professional
                        </Link>
                    </DropdownMenuItem>
                    :
                    <DropdownMenuItem asChild>
                        <Link
                            href={professionalApplication.show()}
                            className="cursor-pointer"
                        >
                            <IdCard className="mr-2 h-4 w-4" />
                            Professional Application
                        </Link>
                    </DropdownMenuItem>
                }
                <DropdownMenuItem asChild>
                    <Link
                        className="block w-full cursor-pointer"
                        href={editAccount()}
                        prefetch
                        onClick={cleanup}
                    >
                        <Settings className="mr-2" />
                        Settings
                    </Link>
                </DropdownMenuItem>
            </DropdownMenuGroup>
            <DropdownMenuSeparator />
            <DropdownMenuItem asChild>
                <Link
                    className="block w-full cursor-pointer"
                    href={logout()}
                    as="button"
                    onClick={handleLogout}
                    data-test="logout-button"
                >
                    <LogOut className="mr-2" />
                    Log out
                </Link>
            </DropdownMenuItem>
        </>
    );
}
