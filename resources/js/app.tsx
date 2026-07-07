import { createInertiaApp } from '@inertiajs/react';
import { configureEcho } from '@laravel/echo-react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AdaptiveAppLayout from '@/layouts/adaptive-app-layout';
import AppLayout from '@/layouts/app-layout';
import AuthWideLayout from '@/layouts/auth/auth-wide-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import UsersLayout from '@/layouts/user-layout';

configureEcho({
    broadcaster: 'reverb',
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name === 'auth/register':
            case name === 'auth/accept-invitation':
                return AuthWideLayout;
            case name.startsWith('auth/'):
                return AuthLayout;
            case name === 'dashboard':
            case name.startsWith('medical-information/'):
            case name.startsWith('professional-application/'):
            case name === 'welcome':
                return UsersLayout;
            case name.startsWith('settings/'):
                return [AdaptiveAppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
