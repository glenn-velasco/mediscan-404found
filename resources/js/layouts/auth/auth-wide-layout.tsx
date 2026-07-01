import AppLogo from '@/components/app-logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthWideLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="min-h-svh bg-muted/30 px-4 py-10 sm:px-6">
            <div className="mx-auto w-full max-w-2xl">
                {/* Header */}
                <div className="mb-8 flex flex-col items-center gap-3 text-center">
                    <AppLogo href={home()} />
                    <div className="space-y-1">
                        <h1 className="text-xl font-medium">{title}</h1>
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                </div>

                {children}
            </div>
        </div>
    );
}
