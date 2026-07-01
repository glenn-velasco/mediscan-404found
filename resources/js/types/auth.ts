import type { Permission, Role } from '@/types';

export type User = {
    id: number;
    name: string | null;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    two_factor_enabled?: boolean;
    created_at: string;
    updated_at: string;
    medicalInfo: MedicalInfo;
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

export type Allergy = {
    id: number;
    allergen: string;
    reaction: string | null;
    severity: string;
};

export type EmergencyContact = {
    id: number;
    name: string;
    relationship: string | null;
    phone_country_code: string | null;
    phone: string | null;
    is_primary: boolean;
};

export type MedicalInfo = {
    full_name: string;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    suffix: string | null;
    date_of_birth: string | null;
    gender: string | null;
    blood_type: string | null;
    phone: string | null;
    phone_country_code: string | null;
    address: string | null;
    religion: string | null;
    no_blood_transfusion: boolean;
    allergies: Allergy[];
    emergency_contacts: EmergencyContact[];
    created_at: string | null;
    updated_at: string | null;
};
