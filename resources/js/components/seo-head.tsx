import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface SeoHeadProps {
    title: string;
    description: string;
    path: string;
    image?: string;
    children?: ReactNode;
}

const SITE_NAME = 'MediScan';
const SITE_URL = 'https://app.mediscan.cloud';
const DEFAULT_IMAGE = '/apple-touch-icon.png';

export default function SeoHead({
    title,
    description,
    path,
    image = DEFAULT_IMAGE,
    children,
}: SeoHeadProps) {
    const url = `${SITE_URL}${path}`;
    const imageUrl = image.startsWith('http') ? image : `${SITE_URL}${image}`;

    return (
        <Head title={title}>
            <meta name="description" content={description} />
            <link rel="canonical" href={url} />

            <meta property="og:type" content="website" />
            <meta property="og:site_name" content={SITE_NAME} />
            <meta property="og:title" content={title} />
            <meta property="og:description" content={description} />
            <meta property="og:url" content={url} />
            <meta property="og:image" content={imageUrl} />

            <meta name="twitter:card" content="summary_large_image" />
            <meta name="twitter:title" content={title} />
            <meta name="twitter:description" content={description} />
            <meta name="twitter:image" content={imageUrl} />

            {children}
        </Head>
    );
}
