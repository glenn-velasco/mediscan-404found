import { usePage } from '@inertiajs/react';
import type { Permission, Role } from '@/types';

export function useAuth() {
    const { auth } = usePage().props;

    return {
        user: auth.user,
        roles: auth.roles,
        permissions: auth.permissions,
        hasRole: (role: Role) => auth.roles.includes(role),
        hasAnyRole: (roles: Role[]) =>
            roles.some((r) => auth.roles.includes(r)),
        hasPermission: (perm: Permission) => auth.permissions.includes(perm),
        hasAnyPermission: (perms: Permission[]) =>
            perms.some((p) => auth.permissions.includes(p)),
    };
}
