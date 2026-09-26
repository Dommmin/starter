import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { SidebarProvider } from '@/components/ui/sidebar';
import type { AppVariant } from '@/types';

type Props = {
    children: ReactNode;
    variant?: AppVariant;
};

export function AppShell({ children, variant = 'sidebar' }: Props) {
    const isOpen = usePage().props.sidebarOpen;

    if (variant === 'header') {
        return (
            <div className="bg-background text-foreground selection:bg-primary selection:text-primary-foreground relative flex min-h-screen w-full flex-col">
                <div
                    aria-hidden="true"
                    className="pointer-events-none fixed inset-0 overflow-hidden"
                >
                    <div className="bg-brand-glow/70 absolute -top-32 -left-28 h-96 w-96 rounded-full blur-3xl motion-safe:animate-pulse" />
                    <div className="bg-brand-glow/50 absolute top-1/3 -right-24 h-[450px] w-[450px] rounded-full blur-3xl" />
                    <div className="bg-brand-subtle/60 absolute -bottom-20 left-1/4 h-80 w-80 rounded-full blur-3xl" />
                </div>
                {children}
            </div>
        );
    }

    return <SidebarProvider defaultOpen={isOpen}>{children}</SidebarProvider>;
}
