// Always check for typos and sync it with app/Enums/Gender.php
export const Gender = {
    Male: 'male',
    Female: 'female',
} as const;

export type Gender = (typeof Gender)[keyof typeof Gender];

export const GenderLabel: Record<Gender, string> = {
    male: 'Male',
    female: 'Female',
};

export const genderOptions = (Object.values(Gender) as Gender[]).map(
    (value) => ({
        value,
        label: GenderLabel[value],
    }),
);
