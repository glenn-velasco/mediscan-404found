export const BloodType = {
    APositive:  'A+',
    ANegative:  'A-',
    BPositive:  'B+',
    BNegative:  'B-',
    ABPositive: 'AB+',
    ABNegative: 'AB-',
    OPositive:  'O+',
    ONegative:  'O-',
} as const;

export type BloodType = typeof BloodType[keyof typeof BloodType];

export const bloodTypeOptions = (Object.values(BloodType) as BloodType[]).map(value => ({
    value,
    label: value,
}));