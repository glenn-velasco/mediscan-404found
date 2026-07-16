import type { InertiaLinkProps } from '@inertiajs/react';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(url: NonNullable<InertiaLinkProps['href']>): string {
    return typeof url === 'string' ? url : url.url;
}

export function formatDate(dateStr: string | null) {
    if (!dateStr) {
        return '—';
    }

    return new Date(dateStr).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        timeZone: 'Asia/Manila',
    });
}

// Always renders in Philippine time (UTC+8) regardless of the viewer's
// browser locale, since this is an internal PH-ops-facing timestamp.
export function formatDateTime(dateStr: string | null) {
    if (!dateStr) {
        return '—';
    }

    return new Date(dateStr).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        timeZone: 'Asia/Manila',
    });
}
