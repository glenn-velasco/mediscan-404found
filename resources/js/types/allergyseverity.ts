export const AllergySeverity = {
    Mild: 'mild',
    Moderate: 'moderate',
    Severe: 'severe',
    LifeThreatening: 'life-threatening',
} as const;

export type AllergySeverity = typeof AllergySeverity[keyof typeof AllergySeverity];

export const AllergySeverityLabel: Record<AllergySeverity, string> = {
    mild: 'Mild',
    moderate: 'Moderate',
    severe: 'Severe',
    'life-threatening': 'Life-threatening',
};

export const allergySeverityOptions = (Object.values(AllergySeverity) as AllergySeverity[]).map(value => ({
    value,
    label: AllergySeverityLabel[value],
}));

export const allergySeverityBadgeVariant: Record<AllergySeverity, 'secondary' | 'outline' | 'destructive'> = {
    mild: 'secondary',
    moderate: 'outline',
    severe: 'destructive',
    'life-threatening': 'destructive',
};
