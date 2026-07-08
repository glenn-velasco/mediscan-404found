import { Head, router, useForm } from '@inertiajs/react';
import { BadgeCheck, Search, Shield, ShieldAlert } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAuth } from '@/hooks/use-auth';
import { cn } from '@/lib/utils';
import professional from '@/routes/professional';
import type { Allergy, TransfusionWitness } from '@/types';
import { AllergySeverityLabel, allergySeverityBadgeVariant } from '@/types';

interface PatientView {
    id: number;
    email: string;
    full_name: string;
    date_of_birth: string | null;
    gender: string | null;
    blood_type: string | null;
    religion: string | null;
    no_blood_transfusion: boolean;
    transfusion_decision_at: string | null;
    transfusion_witnesses: TransfusionWitness[];
    allergies: Allergy[];
}

interface IndexProps {
    patient?: PatientView | null;
}

function Field({ label, value }: { label: string; value: string | null }) {
    return (
        <div className="flex flex-col gap-1">
            <span className="text-[10px] font-semibold tracking-widest text-muted-foreground uppercase">
                {label}
            </span>
            <span className="text-sm font-medium text-foreground">
                {value || '—'}
            </span>
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

function PatientDetails({ patient }: { patient: PatientView }) {
    const { user } = useAuth();

    function verifyAllergy(allergy: Allergy) {
        router.post(
            professional.allergies.verify.url(allergy.id),
            {},
            { preserveScroll: true },
        );
    }

    function witnessTransfusion() {
        router.post(
            professional.patients.transfusionWitness.url(patient.id),
            {},
            { preserveScroll: true },
        );
    }

    const witnesses = patient.transfusion_witnesses ?? [];
    const witnessedByMe = witnesses.some(
        (witness) => witness.user_id === user.id,
    );

    return (
        <>
            {/* Patient summary */}
            <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                <h1 className="text-lg font-semibold">{patient.full_name}</h1>
                <p className="text-sm text-muted-foreground">{patient.email}</p>
                <div className="mt-4 grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Field
                        label="Date of Birth"
                        value={patient.date_of_birth}
                    />
                    <Field label="Gender" value={patient.gender} />
                    <Field label="Blood Type" value={patient.blood_type} />
                    <Field label="Religion" value={patient.religion} />
                </div>
            </div>

            {/* Transfusion decision */}
            <div
                className={cn(
                    'flex flex-wrap items-center gap-3 rounded-xl border px-6 py-4',
                    patient.no_blood_transfusion
                        ? 'border-destructive/20 bg-destructive/5'
                        : 'border-green-400/20 bg-green-400/5',
                )}
            >
                {patient.no_blood_transfusion ? (
                    <ShieldAlert className="h-4 w-4 shrink-0 text-destructive" />
                ) : (
                    <Shield className="h-4 w-4 shrink-0 text-green-400" />
                )}
                <div className="flex-1">
                    <p
                        className={cn(
                            'text-sm font-semibold text-green-400',
                            patient.no_blood_transfusion && 'text-destructive',
                        )}
                    >
                        {patient.no_blood_transfusion
                            ? 'No Blood Transfusion'
                            : 'Blood Transfusion Consented'}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {patient.transfusion_decision_at
                            ? `Decision recorded ${patient.transfusion_decision_at}.`
                            : 'The patient has not recorded a transfusion decision yet.'}
                        {witnesses.length > 0 &&
                            ` Witnessed by ${witnesses
                                .map(
                                    (witness) =>
                                        witness.name ?? 'a professional',
                                )
                                .join(', ')}.`}
                    </p>
                </div>
                {witnesses.length > 0 && (
                    <Badge variant="secondary">
                        <BadgeCheck className="mr-1 h-3.5 w-3.5" />
                        Witnessed ×{witnesses.length}
                    </Badge>
                )}
                {witnessedByMe ? (
                    <Badge variant="outline">You witnessed this</Badge>
                ) : (
                    <Button
                        size="sm"
                        variant="outline"
                        disabled={patient.transfusion_decision_at === null}
                        onClick={witnessTransfusion}
                    >
                        Witness this decision
                    </Button>
                )}
            </div>

            {/* Allergies */}
            <div className="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table className="w-full text-sm">
                    <thead className="bg-muted/50 text-left">
                        <tr>
                            <th className="px-4 py-3 font-medium">Allergen</th>
                            <th className="px-4 py-3 font-medium">Severity</th>
                            <th className="px-4 py-3 font-medium">Reaction</th>
                            <th className="px-4 py-3 font-medium">
                                Verification
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {patient.allergies.map((allergy) => (
                            <tr
                                key={allergy.id}
                                className="border-t border-sidebar-border/70 dark:border-sidebar-border"
                            >
                                <td className="px-4 py-3 font-medium">
                                    {allergy.allergen}
                                </td>
                                <td className="px-4 py-3">
                                    <AllergySeverityBadge
                                        severity={allergy.severity}
                                    />
                                </td>
                                <td className="px-4 py-3 text-muted-foreground">
                                    {allergy.reaction ?? '—'}
                                </td>
                                <td className="px-4 py-3">
                                    {allergy.verified_at ? (
                                        <Badge variant="secondary">
                                            <BadgeCheck className="mr-1 h-3.5 w-3.5" />
                                            Verified by{' '}
                                            {allergy.verified_by_name ??
                                                'a professional'}{' '}
                                            on {allergy.verified_at}
                                        </Badge>
                                    ) : (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                verifyAllergy(allergy)
                                            }
                                        >
                                            Verify
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {patient.allergies.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="px-4 py-6 text-center text-muted-foreground"
                                >
                                    No allergies on record.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}

export default function Index({ patient }: IndexProps) {
    const form = useForm({ email: patient?.email ?? '' });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.get(professional.patients.index.url(), {
            preserveState: true,
        });
    }

    return (
        <>
            <Head title="Patient Lookup" />
            <div className="flex flex-col gap-4">
                <div className="rounded-xl border border-sidebar-border/70 p-6 dark:border-sidebar-border">
                    <h1 className="text-lg font-semibold">Patient Lookup</h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Enter a patient's exact email address to view their
                        medical information, verify allergies, and witness
                        transfusion decisions.
                    </p>

                    <form
                        onSubmit={submit}
                        className="mt-6 flex flex-col gap-4 sm:max-w-lg"
                    >
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="patient-email">Email</Label>
                            <Input
                                id="patient-email"
                                type="email"
                                placeholder="patient@example.com"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                            />
                            {form.errors.email && (
                                <p className="text-xs text-destructive">
                                    {form.errors.email}
                                </p>
                            )}
                        </div>

                        <Button type="submit" disabled={form.processing}>
                            <Search className="mr-2 h-4 w-4" />
                            {form.processing ? 'Searching…' : 'Find patient'}
                        </Button>
                    </form>
                </div>

                {patient && <PatientDetails patient={patient} />}
            </div>
        </>
    );
}
