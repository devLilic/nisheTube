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
            <div className="flex min-h-screen w-full flex-col">
                <SkipNavigation />
                {children}
            </div>
        );
    }

    return (
        <SidebarProvider defaultOpen={isOpen}>
            <SkipNavigation />
            {children}
        </SidebarProvider>
    );
}

function SkipNavigation() {
    return (
        <a
            href="#main-content"
            onClick={(event) => {
                event.preventDefault();
                const main = document.getElementById('main-content');
                main?.scrollIntoView({ block: 'start' });
                window.requestAnimationFrame(() => main?.focus());
            }}
            className="fixed top-2 left-2 z-[100] -translate-y-20 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow-lg transition-transform focus:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
            Skip to main content
        </a>
    );
}
