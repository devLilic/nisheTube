import { Link } from '@inertiajs/react';
import { Heart } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { CompletedRunNotifications } from '@/components/completed-run-notifications';
import { GlobalResearchSearch } from '@/components/global-research-search';
import { QuotaWidget } from '@/components/quota-widget';
import { ResearchContextControls } from '@/components/research-context-controls';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex min-h-16 shrink-0 flex-wrap items-center gap-3 border-b border-sidebar-border/60 bg-card/80 px-4 py-2 backdrop-blur transition-[width,height] ease-linear md:px-6">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="ml-auto flex min-w-52 flex-1 items-center justify-end gap-2">
                <GlobalResearchSearch />
                <ResearchContextControls />
                <Button
                    variant="ghost"
                    size="icon"
                    asChild
                    title="Open Shortlist"
                >
                    <Link href="/favorites" aria-label="Open Shortlist">
                        <Heart />
                    </Link>
                </Button>
                <CompletedRunNotifications />
                <QuotaWidget />
            </div>
        </header>
    );
}
