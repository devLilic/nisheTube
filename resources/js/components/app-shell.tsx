import type { ReactNode } from 'react';

type Props = { children: ReactNode; variant?: 'sidebar' | 'header' };

export function AppShell({ children }: Props) {
    return (
        <div className="min-h-screen">
            <SkipNavigation />
            {children}
        </div>
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
            className="fixed top-2 left-2 z-[100] -translate-y-20 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-transform focus:translate-y-0 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
        >
            Skip to main content
        </a>
    );
}
