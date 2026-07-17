import type { BloodType } from './bloodtype';
import type { Gender } from './gender';
import type { RelationToPatient } from './relationtopatient';
import type { Religion } from './religion';
import type { NameSuffix } from './suffix';

export interface MedicalInformationAddress {
    province: string | null;
    street: string | null;
    unit: string | null;
    country: string | null;
    postal_code: string | null;
    city: string | null;
}

export interface MedicalInformationContact {
    id: number;
    name: string;
    relationship: RelationToPatient | null;
    phone_number: string;
    phone_country_code: string;
    is_primary: boolean;
}

export interface MedicalInformationTransfusionConsent {
    id: number;
    consenter_name: string;
    relationship_to_patient: RelationToPatient | null;
    consented_at: string | null;
}

export interface MedicalInformation {
    id: number;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    fullname: string;
    suffix: NameSuffix | null;
    dob: string | null;
    gender: Gender | null;
    blood_type: BloodType | null;
    religion: Religion | null;
    address: MedicalInformationAddress;
    no_blood_transfusion: boolean;
    avatar: string | null;
    linked_user_count: number;
    contacts: MedicalInformationContact[];
    transfusion_consents: MedicalInformationTransfusionConsent[];
    created_at: string | null;
    updated_at: string | null;
}
