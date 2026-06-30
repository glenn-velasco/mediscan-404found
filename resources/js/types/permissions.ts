// Always check for typos and sync it with enums.php
export const Permission = {
    ManageUsers:              'manage users',
    ManageMedicalInformation: 'manage medical information',
    ManageRecords:            'manage records',
    ManageAllergies:          'manage allergies',
    InviteUserAsAdmin:        'invite user as admin',
} as const;

export type Permission = typeof Permission[keyof typeof Permission];

