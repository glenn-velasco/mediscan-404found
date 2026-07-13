import type { Permission, Role } from '@/types';

export type User = {
    id: number;
    first_name: string | null;
    middle_name: string | null;
    last_name: string | null;
    suffix: string | null;
    fullname: string;
    dob: string | null;
    age: number | null;
    gender: string | null;
    address: string | null;
    phone_number: string | null;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
    roles: Role[];
    permissions: Permission[];
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
