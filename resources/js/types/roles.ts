// Always check for typos and sync it with Roles.php enums
export const Role = {
    Admin: 'admin',
    User:  'user',
} as const;

export type Role = typeof Role[keyof typeof Role];

// Always check for typos and sync it with Roles.php labels
export const RoleLabel: Record<Role, string> = {
    admin: 'Administrator',
    user:  'User',
};

export const roleOptions = (Object.values(Role) as Role[]).map(value => ({
    value,
    label: RoleLabel[value],
}));

