import AppLogoIcon from '@/components/app-logo-icon';
import { useInitials } from '@/hooks/use-initials';
import { Link } from '@inertiajs/react';
import { ComponentProps } from 'react';

interface AppLogoProps extends ComponentProps<typeof Link> {
    sidebar?: boolean;
}

export default function AppLogo({  sidebar = true, ...props }: AppLogoProps) {
    const getInitials = useInitials();
    
    return (
        <>
            <Link className="flex items-center gap-2 text-foreground hover:opacity-80 transition-opacity" {...props} tabIndex={-1}> 
                <span className="font-semibold tracking-tight">{sidebar ? 'Mediscan' : getInitials('Mediscan')}</span>
            </Link>
        </>
    );
}
