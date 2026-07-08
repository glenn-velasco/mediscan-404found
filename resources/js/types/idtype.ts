// Always check for typos and sync it with app/Enums/IdType.php
export const IdType = {
    PhPrc: 'ph_prc',
} as const;

export type IdType = (typeof IdType)[keyof typeof IdType];

export const IdTypeLabel: Record<IdType, string> = {
    ph_prc: 'Professional Regulation Commission (Philippines)',
};

export const idTypeOptions = (Object.values(IdType) as IdType[]).map(
    (value) => ({
        value,
        label: IdTypeLabel[value],
    }),
);
