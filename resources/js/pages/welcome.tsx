import { Head, Link, usePage } from '@inertiajs/react';
import { ActivitySquare, QrCode, ShieldCheck } from 'lucide-react';
import React from 'react';
import { Button } from '@/components/ui/button';
import { login, register, dashboard } from '@/routes';
import admin from '@/routes/admin';

const features = [
    {
        icon: QrCode,
        title: 'Your QR, always on you',
        description:
            'A unique QR code lives on your phone. Show it once — any provider can pull up your full medical profile instantly.',
    },
    {
        icon: ShieldCheck,
        title: 'Critical info, first',
        description:
            'Blood type, allergies, current medications — the things that matter most in triage, front and center.',
    },
    {
        icon: ActivitySquare,
        title: 'Full history behind it',
        description:
            'Past visits, diagnoses, emergency contacts, and attachments — everything a provider needs for informed care.',
    },
];

export default function Welcome() {
    const { auth } = usePage().props;

    const dashboardHref = auth.user
        ? auth.roles?.includes('admin')
            ? admin.dashboard()
            : dashboard()
        : null;

    return (
        <>
            <Head title="MediScan — Your medical history, one scan away" />

            {/* Hero */}
            <section className="flex flex-col items-center justify-center gap-8 px-6 py-20 text-center sm:py-28">
                <div className="inline-flex items-center gap-2 rounded-full bg-muted px-4 py-1.5 text-sm font-medium text-muted-foreground">
                    <QrCode className="h-3.5 w-3.5" />
                    Scan. No forms. No delays.
                </div>
                <h1 className="max-w-2xl text-5xl font-bold tracking-tight sm:text-6xl lg:text-7xl">
                    When you can't explain,{' '}
                    <span className="text-primary">your QR will.</span>
                </h1>
                <p className="max-w-lg text-lg text-muted-foreground">
                    MediScan puts your complete medical profile behind a QR code
                    on your phone. One scan gives any provider everything they
                    need — instantly.
                </p>
                <div className="flex flex-wrap justify-center gap-3">
                    {auth.user && dashboardHref ? (
                        <Button asChild size="lg">
                            <Link href={dashboardHref}>Go to Dashboard</Link>
                        </Button>
                    ) : (
                        <>
                            <Button asChild size="lg">
                                <Link href={register()}>
                                    Create your profile
                                </Link>
                            </Button>
                            <Button asChild size="lg" variant="outline">
                                <Link href={login()}>Log in</Link>
                            </Button>
                        </>
                    )}
                </div>
            </section>

            {/* How it works — 3 steps */}
            <section className="mx-auto w-full max-w-2xl px-6 pb-12">
                <p className="mb-6 text-xs font-semibold tracking-widest text-muted-foreground uppercase">
                    How it works
                </p>
                <ol className="relative border-l">
                    {[
                        {
                            step: 'Fill in your medical profile',
                            detail: 'Allergies, medications, blood type, emergency contacts.',
                        },
                        {
                            step: 'Get your personal QR code',
                            detail: "Save it to your phone's lock screen or wallet.",
                        },
                        {
                            step: 'Show it when it matters',
                            detail: 'Any provider scans it and sees everything instantly.',
                        },
                    ].map(({ step, detail }) => (
                        <li key={step} className="mb-8 ml-6">
                            <span className="absolute -left-1.5 h-3 w-3 rounded-full border bg-background" />
                            <p className="font-semibold">{step}</p>
                            <p className="text-sm text-muted-foreground">
                                {detail}
                            </p>
                        </li>
                    ))}
                </ol>
            </section>

            {/* Features */}
            <section className="mx-auto w-full max-w-2xl px-6 pb-24">
                <div className="divide-y">
                    {features.map((feature, i) => (
                        <div
                            key={feature.title}
                            className="flex items-start gap-8 py-8"
                        >
                            <span className="w-8 shrink-0 text-xl font-bold text-muted-foreground/20 tabular-nums select-none">
                                {String(i + 1).padStart(2, '0')}
                            </span>
                            <div className="flex flex-col gap-1.5">
                                <div className="flex items-center gap-2">
                                    <feature.icon className="h-4 w-4 text-primary" />
                                    <h3 className="font-semibold">
                                        {feature.title}
                                    </h3>
                                </div>
                                <p className="text-sm text-muted-foreground">
                                    {feature.description}
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            {/* Footer */}
            <footer className="border-t py-6 text-center text-sm text-muted-foreground">
                &copy; {new Date().getFullYear()} MediScan. All rights reserved.
            </footer>
        </>
    );
}
