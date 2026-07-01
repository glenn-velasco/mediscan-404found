import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';
import { useInitials } from '@/hooks/use-initials';

interface AppLogoProps extends ComponentProps<typeof Link> {
    sidebar?: boolean;
}

export default function AppLogo({ sidebar = true, ...props }: AppLogoProps) {
    const getInitials = useInitials();

    return (
        <>
            <Link
                className="flex items-center gap-2 text-foreground transition-opacity hover:opacity-80"
                {...props}
                tabIndex={-1}
            >
                <span className="font-semibold tracking-tight">
                    {sidebar ? 'Mediscan' : getInitials('Mediscan')}
                </span>
            </Link>
        </>
    );
}
