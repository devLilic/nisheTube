import { Link, usePage } from '@inertiajs/react';
import { Heart, Menu } from 'lucide-react';
import { useState } from 'react';
import { ResearchNavigation } from '@/components/app-sidebar';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { CompletedRunNotifications } from '@/components/completed-run-notifications';
import { GlobalResearchSearch } from '@/components/global-research-search';
import { GlassCommandBar, GlassSheet } from '@/components/liquid-glass';
import { QuotaWidget } from '@/components/quota-widget';
import { ResearchContextControls } from '@/components/research-context-controls';
import { Button } from '@/components/ui/button';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
    collapsed,
    onCollapse,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    collapsed: boolean;
    onCollapse: () => void;
}) {
    const context = usePage().props.researchContext;
    const contextLabel =
        [
            context?.selection.market?.label,
            context?.selection.project?.label,
            context?.selection.workspace?.label,
        ]
            .filter(Boolean)
            .join(' · ') || 'Research context';
    const [mobileOpen, setMobileOpen] = useState(false);

    return (
        <>
            <GlassCommandBar className="sticky top-4 z-20 flex min-h-14 flex-wrap items-center gap-2">
                <GlassSheet
                    open={mobileOpen}
                    onOpenChange={setMobileOpen}
                    side="left"
                    title="Research navigation"
                    description="Discover, validate, analyze, organize, and manage all research work."
                    trigger={
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="min-h-11 min-w-11 lg:hidden"
                            aria-label="Open navigation"
                        >
                            <Menu />
                        </Button>
                    }
                >
                    <ResearchNavigation
                        onNavigate={() => setMobileOpen(false)}
                    />
                </GlassSheet>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="hidden min-h-11 min-w-11 lg:inline-flex"
                    onClick={onCollapse}
                    aria-label={
                        collapsed ? 'Expand navigation' : 'Collapse navigation'
                    }
                >
                    <Menu />
                </Button>
                <div className="order-3 hidden min-w-0 basis-full lg:order-none lg:block lg:flex-1 lg:basis-auto">
                    {breadcrumbs.length > 0 && (
                        <Breadcrumbs breadcrumbs={breadcrumbs} />
                    )}
                </div>
                <div className="order-2 flex min-w-0 flex-1 items-center gap-2 lg:order-none lg:flex-none">
                    <GlobalResearchSearch />
                    <div className="hidden xl:block">
                        <ResearchContextControls />
                    </div>
                    <GlassSheet
                        title="Research context"
                        description="Market, project, and workspace selections stay separate from the UI locale."
                        trigger={
                            <Button
                                type="button"
                                variant="outline"
                                className="min-h-11 max-w-52 justify-start xl:hidden"
                            >
                                <span className="truncate">
                                    Context: {contextLabel}
                                </span>
                            </Button>
                        }
                    >
                        <ResearchContextControls />
                    </GlassSheet>
                    <Button
                        variant="ghost"
                        size="icon"
                        asChild
                        className="hidden min-h-11 min-w-11 sm:inline-flex"
                        title="Open Shortlist"
                    >
                        <Link href="/shortlist" aria-label="Open Shortlist">
                            <Heart />
                        </Link>
                    </Button>
                    <div className="hidden sm:block">
                        <CompletedRunNotifications />
                    </div>
                    <QuotaWidget />
                </div>
            </GlassCommandBar>
            {breadcrumbs.length > 1 && (
                <div className="mt-2 px-2 text-xs text-[var(--lg-ink-muted)] lg:hidden">
                    <Breadcrumbs breadcrumbs={breadcrumbs} />
                </div>
            )}
        </>
    );
}
