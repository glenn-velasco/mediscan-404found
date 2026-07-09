import { Head, router, useForm } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import type { CountryCode } from 'libphonenumber-js';
import { getCountryCallingCode } from 'libphonenumber-js';
import {
    BadgeCheck,
    Pencil,
    Plus,
    Shield,
    ShieldAlert,
    Trash2,
} from 'lucide-react';
import type { ReactElement } from 'react';
import { useState } from 'react';
import AllergyController from '@/actions/App/Http/Controllers/AllergyController';
import EmergencyContactController from '@/actions/App/Http/Controllers/EmergencyContactController';
import { PhoneInput } from '@/components/phone-input';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetFooter,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import { useAuth } from '@/hooks/use-auth';
import { cn, formatDate } from '@/lib/utils';
import {
    allergySeverityBadgeVariant,
    AllergySeverityLabel,
    allergySeverityOptions,
    BloodType,
} from '@/types';
import type { Allergy, EmergencyContact, MedicalInfo } from '@/types';

interface DashboardProps {
    medicalInfo: MedicalInfo | null;
}

function SectionTitle({ title }: { title: string }) {
    return (
        <div className="flex items-center gap-3">
            <div className="h-px w-8 bg-muted-foreground/40" />
            <span className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                {title}
            </span>
        </div>
    );
}

function Stat({
    label,
    value,
    span,
}: {
    label: string;
    value?: string | null;
    span?: ReactElement<'span'>;
}) {
    return (
        <div className="flex flex-col gap-1">
            <span className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                {label}
            </span>
            <span className="text-base font-medium text-foreground">
                {span} {value || '—'}
            </span>
        </div>
    );
}

function Field({
    label,
    children,
}: {
    label: string;
    children: React.ReactNode;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <Label className="text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                {label}
            </Label>
            {children}
        </div>
    );
}

function AllergySeverityBadge({ severity }: { severity: string }) {
    const variant =
        allergySeverityBadgeVariant[
            severity as keyof typeof allergySeverityBadgeVariant
        ] ?? 'outline';
    const label =
        AllergySeverityLabel[severity as keyof typeof AllergySeverityLabel] ??
        severity;

    return <Badge variant={variant}>{label}</Badge>;
}

function AllergyFormDialog({
    allergy,
    open,
    onOpenChange,
}: {
    allergy?: Allergy;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({
        allergen: allergy?.allergen ?? '',
        reaction: allergy?.reaction ?? '',
        severity: allergy?.severity ?? '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        };

        if (allergy) {
            form.patch(AllergyController.update.url(allergy.id), options);
        } else {
            form.post(AllergyController.store.url(), options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {allergy ? 'Edit allergy' : 'Add allergy'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <Field label="Allergen">
                        <Input
                            value={form.data.allergen}
                            onChange={(e) =>
                                form.setData('allergen', e.target.value)
                            }
                        />
                        {form.errors.allergen && (
                            <p className="text-xs text-destructive">
                                {form.errors.allergen}
                            </p>
                        )}
                    </Field>

                    <Field label="Severity">
                        <Select
                            value={form.data.severity}
                            onValueChange={(v) => form.setData('severity', v)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select…" />
                            </SelectTrigger>
                            <SelectContent>
                                {allergySeverityOptions.map((opt) => (
                                    <SelectItem
                                        key={opt.value}
                                        value={opt.value}
                                    >
                                        {opt.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.severity && (
                            <p className="text-xs text-destructive">
                                {form.errors.severity}
                            </p>
                        )}
                    </Field>

                    <Field label="Reaction">
                        <Textarea
                            value={form.data.reaction}
                            placeholder="Optional details about the reaction…"
                            onChange={(e) =>
                                form.setData('reaction', e.target.value)
                            }
                        />
                        {form.errors.reaction && (
                            <p className="text-xs text-destructive">
                                {form.errors.reaction}
                            </p>
                        )}
                    </Field>

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving…' : 'Save'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteAllergyAlert({
    allergy,
    open,
    onOpenChange,
}: {
    allergy: Allergy;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({});

    function destroy() {
        form.delete(AllergyController.destroy.url(allergy.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        Remove {allergy.allergen}?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        This will permanently delete this allergy record,
                        including any reaction details.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        disabled={form.processing}
                        onClick={destroy}
                    >
                        Remove
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function AllergiesSection({ allergies }: { allergies: Allergy[] }) {
    return (
        <div className="flex flex-col gap-4">
            <SectionTitle title="Allergies" />

            {allergies.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No known allergies on record.
                </p>
            ) : (
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {allergies.map((allergy) => (
                        <div
                            key={allergy.id}
                            className="flex flex-col gap-1 rounded-lg border px-4 py-3"
                        >
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-foreground">
                                    {allergy.allergen}
                                </span>
                                <AllergySeverityBadge
                                    severity={allergy.severity}
                                />
                                {allergy.verified_at && (
                                    <Badge variant="secondary">
                                        <BadgeCheck className="mr-1 h-3.5 w-3.5" />
                                        Verified
                                    </Badge>
                                )}
                            </div>
                            {allergy.reaction && (
                                <p className="text-sm text-muted-foreground">
                                    {allergy.reaction}
                                </p>
                            )}
                            {allergy.verified_at && (
                                <p className="text-xs text-muted-foreground">
                                    Verified by{' '}
                                    {allergy.verified_by_name ??
                                        'a professional'}{' '}
                                    on {allergy.verified_at}. Editing this
                                    allergy resets its verification.
                                </p>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function EmergencyContactFormDialog({
    contact,
    open,
    onOpenChange,
    isFirstContact,
}: {
    contact?: EmergencyContact;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    isFirstContact: boolean;
}) {
    const form = useForm({
        name: contact?.name ?? '',
        relationship: contact?.relationship ?? '',
        phone_country_code: contact?.phone_country_code ?? '',
        phone: contact?.phone ?? '',
        is_primary: contact?.is_primary ?? false,
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();

        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        };

        if (contact) {
            form.patch(
                EmergencyContactController.update.url(contact.id),
                options,
            );
        } else {
            form.post(EmergencyContactController.store.url(), options);
        }
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {contact
                            ? 'Edit emergency contact'
                            : 'Add emergency contact'}
                    </DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="flex flex-col gap-4">
                    <Field label="Name">
                        <Input
                            name="name"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                        />
                        {form.errors.name && (
                            <p className="text-xs text-destructive">
                                {form.errors.name}
                            </p>
                        )}
                    </Field>

                    <Field label="Relationship">
                        <Input
                            name="relationship"
                            value={form.data.relationship}
                            onChange={(e) =>
                                form.setData('relationship', e.target.value)
                            }
                        />
                        {form.errors.relationship && (
                            <p className="text-xs text-destructive">
                                {form.errors.relationship}
                            </p>
                        )}
                    </Field>

                    <Field label="Phone">
                        <PhoneInput
                            idPrefix="ec"
                            countryValue={form.data.phone_country_code}
                            phoneValue={form.data.phone}
                            onCountryChange={(v) =>
                                form.setData('phone_country_code', v)
                            }
                            onPhoneChange={(v) => form.setData('phone', v)}
                            countryError={form.errors.phone_country_code}
                            phoneError={form.errors.phone}
                        />
                    </Field>

                    {isFirstContact ? (
                        <p className="text-xs text-muted-foreground">
                            This will be set as your primary contact.
                        </p>
                    ) : contact?.is_primary ? (
                        <div className="flex items-center gap-2">
                            <Checkbox checked disabled />
                            <Label className="text-sm text-muted-foreground">
                                This is your primary contact.
                            </Label>
                        </div>
                    ) : (
                        <div className="flex items-center gap-2">
                            <Checkbox
                                id="is_primary"
                                checked={form.data.is_primary}
                                onCheckedChange={(v) =>
                                    form.setData('is_primary', Boolean(v))
                                }
                            />
                            <Label htmlFor="is_primary" className="text-sm">
                                Set as primary contact
                            </Label>
                        </div>
                    )}

                    <DialogFooter>
                        <Button type="submit" disabled={form.processing} data-test="save-emergency-contact">
                            {form.processing ? 'Saving…' : 'Save'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function DeleteEmergencyContactAlert({
    contact,
    open,
    onOpenChange,
}: {
    contact: EmergencyContact;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({});

    function destroy() {
        form.delete(EmergencyContactController.destroy.url(contact.id), {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Remove {contact.name}?</AlertDialogTitle>
                    <AlertDialogDescription>
                        This will permanently delete this emergency contact.
                        {contact.is_primary &&
                            ' This is your primary contact — deleting it will leave no primary contact until you mark another one.'}
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel>Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        disabled={form.processing}
                        onClick={destroy}
                    >
                        Remove
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

function EmergencyContactsSection({
    contacts,
}: {
    contacts: EmergencyContact[];
}) {
    return (
        <div className="flex flex-col gap-4">
            <SectionTitle title="Emergency Contacts" />

            {contacts.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                    No emergency contacts on record.
                </p>
            ) : (
                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {contacts.map((contact) => (
                        <div
                            key={contact.id}
                            className="flex flex-col gap-1 rounded-lg border px-4 py-3"
                        >
                            <div className="flex items-center gap-2">
                                <span className="font-medium text-foreground">
                                    {contact.name}
                                </span>
                                {contact.is_primary && (
                                    <Badge variant="default">Primary</Badge>
                                )}
                            </div>
                            {contact.relationship && (
                                <p className="text-sm text-muted-foreground">
                                    {contact.relationship}
                                </p>
                            )}
                            {contact.phone && (
                                <p className="text-sm text-muted-foreground">
                                    {contact.phone_country_code &&
                                        `+${getCountryCallingCode(contact.phone_country_code as CountryCode)} `}
                                    {contact.phone}
                                </p>
                            )}
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

export default function Dashboard({ medicalInfo }: DashboardProps) {
    const { user } = useAuth();
    const allergies = medicalInfo?.allergies ?? [];
    const emergencyContacts = medicalInfo?.emergency_contacts ?? [];
    const { data, setData, patch, processing, errors } = useForm({
        email: user.email,
        first_name: medicalInfo?.first_name ?? '',
        middle_name: medicalInfo?.middle_name ?? '',
        last_name: medicalInfo?.last_name ?? '',
        suffix: medicalInfo?.suffix ?? '',
        date_of_birth: medicalInfo?.date_of_birth ?? '',
        gender: medicalInfo?.gender ?? '',
        blood_type: medicalInfo?.blood_type ?? '',
        phone_country_code: medicalInfo?.phone_country_code ?? '',
        phone: medicalInfo?.phone ?? '',
        religion: medicalInfo?.religion ?? '',
        address: medicalInfo?.address ?? '',
        no_blood_transfusion: medicalInfo?.no_blood_transfusion ?? false,
    });
    const [addAllergyOpen, setAddAllergyOpen] = useState(false);
    const [editingAllergy, setEditingAllergy] = useState<Allergy | null>(null);
    const [deletingAllergy, setDeletingAllergy] = useState<Allergy | null>(
        null,
    );
    const [addContactOpen, setAddContactOpen] = useState(false);
    const [editingContact, setEditingContact] =
        useState<EmergencyContact | null>(null);
    const [deletingContact, setDeletingContact] =
        useState<EmergencyContact | null>(null);

    useEcho(
        `App.Models.User.${user.id}`,
        ['.AllergyVerified', '.TransfusionConsentUpdated'],
        () => router.reload({ only: ['medicalInfo'] }),
    );

    function submit(e: React.FormEvent) {
        e.preventDefault();
        patch('/dashboard', { preserveScroll: true });
    }

    function recordTransfusionDecision(noBloodTransfusion: boolean) {
        router.patch(
            '/transfusion-consent',
            { no_blood_transfusion: noBloodTransfusion },
            { preserveScroll: true },
        );
    }

    return (
        <>
            <Head title="Health Record" />

            <div className="flex flex-col gap-6 sm:gap-10">
                {/* Name + DOB hero */}
                <div className="flex items-start justify-between gap-4">
                    <div className="flex flex-col gap-2">
                        {medicalInfo ? (
                            <>
                                <SectionTitle title={medicalInfo.full_name} />
                                <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl lg:text-5xl">
                                    {medicalInfo.full_name}
                                </h1>
                                {medicalInfo.date_of_birth && (
                                    <p className="text-sm text-muted-foreground">
                                        Born{' '}
                                        <span className="font-medium text-foreground">
                                            {formatDate(
                                                medicalInfo.date_of_birth,
                                            )}
                                        </span>
                                    </p>
                                )}
                            </>
                        ) : (
                            <>
                                <SectionTitle title="Health Record" />
                                <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl lg:text-5xl">
                                    No health record yet
                                </h1>
                                <p className="text-sm text-muted-foreground">
                                    Fill in your health record to get started.
                                </p>
                            </>
                        )}
                    </div>

                    <Sheet key={`${user.email}-${medicalInfo?.updated_at}`}>
                        <SheetTrigger asChild>
                            <Button
                                variant="outline"
                                size="sm"
                                className="shrink-0"
                            >
                                <Pencil className="mr-2 h-3.5 w-3.5" />
                                Edit
                            </Button>
                        </SheetTrigger>
                        <SheetContent
                            side="right"
                            className="w-full overflow-y-auto sm:max-w-lg"
                        >
                            <SheetHeader>
                                <SheetTitle>Edit Health Record</SheetTitle>
                            </SheetHeader>

                            <form
                                onSubmit={submit}
                                className="flex flex-col gap-5 px-4 py-2"
                            >
                                {/* Account */}
                                <Field label="Email">
                                    <Input
                                        type="email"
                                        value={data.email}
                                        onChange={(e) =>
                                            setData('email', e.target.value)
                                        }
                                    />
                                    {errors.email && (
                                        <p className="text-xs text-destructive">
                                            {errors.email}
                                        </p>
                                    )}
                                </Field>

                                {/* Name */}
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="First Name">
                                        <Input
                                            value={data.first_name}
                                            onChange={(e) =>
                                                setData(
                                                    'first_name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors.first_name && (
                                            <p className="text-xs text-destructive">
                                                {errors.first_name}
                                            </p>
                                        )}
                                    </Field>
                                    <Field label="Last Name">
                                        <Input
                                            value={data.last_name}
                                            onChange={(e) =>
                                                setData(
                                                    'last_name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {errors.last_name && (
                                            <p className="text-xs text-destructive">
                                                {errors.last_name}
                                            </p>
                                        )}
                                    </Field>
                                </div>
                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="Middle Name">
                                        <Input
                                            value={data.middle_name}
                                            onChange={(e) =>
                                                setData(
                                                    'middle_name',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <Field label="Suffix">
                                        <Input
                                            value={data.suffix}
                                            placeholder="Jr., Sr., III…"
                                            onChange={(e) =>
                                                setData(
                                                    'suffix',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                </div>

                                {/* Core */}
                                <Field label="Date of Birth">
                                    <Input
                                        type="date"
                                        value={data.date_of_birth}
                                        onChange={(e) =>
                                            setData(
                                                'date_of_birth',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {errors.date_of_birth && (
                                        <p className="text-xs text-destructive">
                                            {errors.date_of_birth}
                                        </p>
                                    )}
                                </Field>

                                <div className="grid grid-cols-2 gap-3">
                                    <Field label="Gender">
                                        <Select
                                            value={data.gender}
                                            onValueChange={(v) =>
                                                setData('gender', v)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="male">
                                                    Male
                                                </SelectItem>
                                                <SelectItem value="female">
                                                    Female
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {errors.gender && (
                                            <p className="text-xs text-destructive">
                                                {errors.gender}
                                            </p>
                                        )}
                                    </Field>
                                    <Field label="Blood Type">
                                        <Select
                                            value={data.blood_type}
                                            onValueChange={(v) =>
                                                setData('blood_type', v)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {(
                                                    Object.values(
                                                        BloodType,
                                                    ) as BloodType[]
                                                ).map((bt) => (
                                                    <SelectItem
                                                        key={bt}
                                                        value={bt}
                                                    >
                                                        {bt}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </Field>
                                </div>

                                {/* Contact */}
                                <div className="grid grid-cols-3 gap-3">
                                    <Field label="Country Code">
                                        <Input
                                            value={data.phone_country_code}
                                            placeholder="PH"
                                            onChange={(e) =>
                                                setData(
                                                    'phone_country_code',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                    <div className="col-span-2">
                                        <Field label="Phone">
                                            <Input
                                                value={data.phone}
                                                onChange={(e) =>
                                                    setData(
                                                        'phone',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </Field>
                                    </div>
                                </div>

                                <Field label="Address">
                                    <Input
                                        value={data.address}
                                        onChange={(e) =>
                                            setData('address', e.target.value)
                                        }
                                    />
                                </Field>

                                <Field label="Religion">
                                    <Input
                                        value={data.religion}
                                        onChange={(e) =>
                                            setData('religion', e.target.value)
                                        }
                                    />
                                </Field>

                                <div className="flex flex-col gap-3 rounded-md border p-3">
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="no_blood_transfusion"
                                            checked={data.no_blood_transfusion}
                                            onCheckedChange={(v) =>
                                                setData(
                                                    'no_blood_transfusion',
                                                    Boolean(v),
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor="no_blood_transfusion"
                                            className="text-sm"
                                        >
                                            No blood transfusion
                                        </Label>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {medicalInfo?.transfusion_decision_at
                                            ? `Decision recorded ${medicalInfo.transfusion_decision_at}.`
                                            : 'You have not recorded your decision yet.'}{' '}
                                        Recording a decision takes effect
                                        immediately and resets any professional
                                        attestations.
                                    </p>
                                    {medicalInfo?.transfusion_decision_at ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="self-start"
                                            onClick={() =>
                                                recordTransfusionDecision(
                                                    !medicalInfo.no_blood_transfusion,
                                                )
                                            }
                                        >
                                            {medicalInfo.no_blood_transfusion
                                                ? 'Consent to transfusion'
                                                : 'Withdraw consent'}
                                        </Button>
                                    ) : (
                                        <div className="flex gap-2">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    recordTransfusionDecision(
                                                        false,
                                                    )
                                                }
                                            >
                                                Consent
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    recordTransfusionDecision(
                                                        true,
                                                    )
                                                }
                                            >
                                                Decline
                                            </Button>
                                        </div>
                                    )}
                                </div>

                                {/* Allergies */}
                                <div className="border-t pt-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <p className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                            Allergies
                                        </p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setAddAllergyOpen(true)
                                            }
                                        >
                                            <Plus className="mr-2 h-3.5 w-3.5" />
                                            Add allergy
                                        </Button>
                                    </div>
                                    {allergies.length === 0 ? (
                                        <p className="text-xs text-muted-foreground">
                                            No known allergies on record.
                                        </p>
                                    ) : (
                                        <div className="grid grid-cols-2 gap-2">
                                            {allergies.map((allergy) => (
                                                <div
                                                    key={allergy.id}
                                                    className="flex flex-col gap-1 rounded-md border p-2"
                                                >
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        <span className="text-sm font-medium text-foreground">
                                                            {allergy.allergen}
                                                        </span>
                                                        <AllergySeverityBadge
                                                            severity={
                                                                allergy.severity
                                                            }
                                                        />
                                                    </div>
                                                    {allergy.reaction && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {allergy.reaction}
                                                        </p>
                                                    )}
                                                    <div className="mt-1 flex items-center gap-0.5">
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                setEditingAllergy(
                                                                    allergy,
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="h-3.5 w-3.5" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            onClick={() =>
                                                                setDeletingAllergy(
                                                                    allergy,
                                                                )
                                                            }
                                                        >
                                                            <Trash2 className="h-3.5 w-3.5 text-destructive" />
                                                        </Button>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>

                                {/* Emergency contacts */}
                                <div className="border-t pt-4">
                                    <div className="mb-3 flex items-center justify-between">
                                        <p className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                                            Emergency Contacts
                                        </p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            data-test="add-emergency-contact"
                                            onClick={() =>
                                                setAddContactOpen(true)
                                            }
                                        >
                                            <Plus className="mr-2 h-3.5 w-3.5" />
                                            Add contact
                                        </Button>
                                    </div>
                                    {emergencyContacts.length === 0 ? (
                                        <p className="text-xs text-muted-foreground">
                                            No emergency contacts on record.
                                        </p>
                                    ) : (
                                        <div className="grid grid-cols-2 gap-2">
                                            {emergencyContacts.map(
                                                (contact) => (
                                                    <div
                                                        key={contact.id}
                                                        className="flex flex-col gap-1 rounded-md border p-2"
                                                    >
                                                        <div className="flex flex-wrap items-center gap-1.5">
                                                            <span className="text-sm font-medium text-foreground">
                                                                {contact.name}
                                                            </span>
                                                            {contact.is_primary && (
                                                                <Badge variant="default">
                                                                    Primary
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        {contact.relationship && (
                                                            <p className="text-xs text-muted-foreground">
                                                                {
                                                                    contact.relationship
                                                                }
                                                            </p>
                                                        )}
                                                        <div className="mt-1 flex items-center gap-0.5">
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                data-test="edit-emergency-contact"
                                                                onClick={() =>
                                                                    setEditingContact(
                                                                        contact,
                                                                    )
                                                                }
                                                            >
                                                                <Pencil className="h-3.5 w-3.5" />
                                                            </Button>
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                data-test="delete-emergency-contact"
                                                                onClick={() =>
                                                                    setDeletingContact(
                                                                        contact,
                                                                    )
                                                                }
                                                            >
                                                                <Trash2 className="h-3.5 w-3.5 text-destructive" />
                                                            </Button>
                                                        </div>
                                                    </div>
                                                ),
                                            )}
                                        </div>
                                    )}
                                </div>

                                <SheetFooter>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full"
                                    >
                                        {processing ? 'Saving…' : 'Save'}
                                    </Button>
                                </SheetFooter>
                            </form>
                        </SheetContent>
                    </Sheet>
                </div>

                {medicalInfo && (
                    <>
                        {/* Divider */}
                        <div className="h-px bg-border" />
                        <SectionTitle title="Information" />
                        {/* Blood transfusion */}
                        <div
                            className={cn(
                                'flex items-center gap-3 rounded-lg border px-4 py-3',
                                medicalInfo.no_blood_transfusion
                                    ? 'border-destructive/20 bg-destructive/5'
                                    : 'border-green-400/20 bg-green-400/5',
                            )}
                        >
                            {medicalInfo.no_blood_transfusion ? (
                                <ShieldAlert className="h-4 w-4 shrink-0 text-destructive" />
                            ) : (
                                <Shield className="h-4 w-4 shrink-0 text-green-400" />
                            )}
                            <div className="flex flex-1 flex-col">
                                <span
                                    className={cn(
                                        'text-sm font-semibold text-green-400',
                                        medicalInfo.no_blood_transfusion &&
                                            'text-destructive',
                                    )}
                                >
                                    {medicalInfo.no_blood_transfusion
                                        ? 'No Blood Transfusion'
                                        : 'Blood Transfusion Consented'}
                                </span>
                                {(medicalInfo.transfusion_witnesses ?? [])
                                    .length > 0 && (
                                    <span className="text-xs text-muted-foreground">
                                        Witnessed by{' '}
                                        {medicalInfo.transfusion_witnesses
                                            .map(
                                                (witness) =>
                                                    witness.name ??
                                                    'a professional',
                                            )
                                            .join(', ')}
                                        . Changing this decision resets the
                                        attestations.
                                    </span>
                                )}
                                {!medicalInfo.transfusion_decision_at && (
                                    <span className="text-xs text-muted-foreground">
                                        You have not recorded your decision yet.
                                    </span>
                                )}
                            </div>
                            {(medicalInfo.transfusion_witnesses ?? []).length >
                                0 && (
                                <Badge variant="secondary">
                                    <BadgeCheck className="mr-1 h-3.5 w-3.5" />
                                    Witnessed ×
                                    {medicalInfo.transfusion_witnesses.length}
                                </Badge>
                            )}
                        </div>

                        {/* Primary stats */}
                        <div className="grid grid-cols-2 gap-6 sm:grid-cols-3">
                            <Stat
                                label="Gender"
                                value={
                                    medicalInfo.gender
                                        ? medicalInfo.gender
                                              .charAt(0)
                                              .toUpperCase() +
                                          medicalInfo.gender.slice(1)
                                        : null
                                }
                            />
                            <Stat
                                label="Blood Type"
                                value={medicalInfo.blood_type}
                            />
                            <Stat
                                label="Religion"
                                value={medicalInfo.religion}
                            />
                        </div>

                        {/* Contact details */}
                        <div className="grid grid-cols-1 gap-6 sm:grid-cols-2">
                            <Stat label="Email" value={user.email} />
                            <Stat
                                label="Phone"
                                value={medicalInfo.phone}
                                span={
                                    <span>
                                        {medicalInfo.phone_country_code
                                            ? `+${getCountryCallingCode(medicalInfo.phone_country_code as CountryCode)}`
                                            : ''}
                                    </span>
                                }
                            />
                            <Stat label="Address" value={medicalInfo.address} />
                        </div>

                        {/* Divider */}
                        <div className="h-px bg-border" />

                        {/* Emergency contacts */}
                        <EmergencyContactsSection
                            contacts={emergencyContacts}
                        />

                        <EmergencyContactFormDialog
                            open={addContactOpen}
                            onOpenChange={setAddContactOpen}
                            isFirstContact={emergencyContacts.length === 0}
                        />

                        {editingContact && (
                            <EmergencyContactFormDialog
                                contact={editingContact}
                                open={!!editingContact}
                                onOpenChange={(open) =>
                                    !open && setEditingContact(null)
                                }
                                isFirstContact={false}
                            />
                        )}

                        {deletingContact && (
                            <DeleteEmergencyContactAlert
                                contact={deletingContact}
                                open={!!deletingContact}
                                onOpenChange={(open) =>
                                    !open && setDeletingContact(null)
                                }
                            />
                        )}

                        {/* Divider */}
                        <div className="h-px bg-border" />

                        {/* Allergies */}
                        <AllergiesSection allergies={allergies} />

                        <AllergyFormDialog
                            open={addAllergyOpen}
                            onOpenChange={setAddAllergyOpen}
                        />

                        {editingAllergy && (
                            <AllergyFormDialog
                                allergy={editingAllergy}
                                open={!!editingAllergy}
                                onOpenChange={(open) =>
                                    !open && setEditingAllergy(null)
                                }
                            />
                        )}

                        {deletingAllergy && (
                            <DeleteAllergyAlert
                                allergy={deletingAllergy}
                                open={!!deletingAllergy}
                                onOpenChange={(open) =>
                                    !open && setDeletingAllergy(null)
                                }
                            />
                        )}

                        {/* Footer */}
                        <div className="h-px bg-border" />
                        <p className="text-xs text-muted-foreground">
                            Issued{' '}
                            <span className="font-medium text-foreground">
                                {formatDate(medicalInfo.created_at)}
                            </span>{' '}
                            · MediScan Patient Portal
                        </p>
                    </>
                )}
            </div>
        </>
    );
}
