// Always check for typos and sync it with app/Enums/NameSuffix.php
export const NameSuffix = {
    junior: 'junior',
    senior: 'senior',
    thesecond: 'the_second',
    the_third: 'the_third',
    the_forth: 'the_forth',
    the_fifth: 'the_fifth',
} as const;

export type NameSuffix = (typeof NameSuffix)[keyof typeof NameSuffix];

export const NameSuffixLabel: Record<NameSuffix, string> = {
    junior: 'Jr',
    senior: 'Sr',
    the_second: 'Ⅱ',
    the_third: 'Ⅲ',
    the_forth: 'Ⅳ',
    the_fifth: 'Ⅴ',
};

export const NameSuffixOptions = (
    Object.values(NameSuffix) as NameSuffix[]
).map((value) => ({
    value,
    label: NameSuffixLabel[value],
}));
