import { useEffect, useState } from 'react';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { AppCanvas } from '@/components/liquid-glass';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    const [collapsed, setCollapsed] = useState(false);

    useEffect(() => {
        const setDefaultRailState = () =>
            setCollapsed(window.innerWidth < 1200);

        setDefaultRailState();
        window.addEventListener('resize', setDefaultRailState);

        return () => window.removeEventListener('resize', setDefaultRailState);
    }, []);

    return (
        <AppShell variant="sidebar">
            <AppCanvas className="min-h-screen p-4">
                <div className="mx-auto flex max-w-[1680px] gap-6">
                    <AppSidebar
                        collapsed={collapsed}
                        onCollapse={() => setCollapsed((value) => !value)}
                    />
                    <main
                        id="main-content"
                        tabIndex={-1}
                        className="min-w-0 flex-1 space-y-6"
                    >
                        <AppSidebarHeader
                            breadcrumbs={breadcrumbs}
                            collapsed={collapsed}
                            onCollapse={() => setCollapsed((value) => !value)}
                        />
                        {children}
                    </main>
                </div>
            </AppCanvas>
        </AppShell>
    );
}
