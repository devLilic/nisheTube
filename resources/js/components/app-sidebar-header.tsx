import { Globe2 } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { QuotaWidget } from '@/components/quota-widget';
import { Button } from '@/components/ui/button';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex h-16 shrink-0 items-center gap-3 border-b border-sidebar-border/60 bg-card/80 px-4 backdrop-blur transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-14 md:px-6">
            <div className="flex min-w-0 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            <div className="ml-auto flex max-w-full min-w-0 items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    className="hidden gap-2 sm:flex"
                    disabled
                >
                    <Globe2 />
                    Market not set
                </Button>
                <QuotaWidget />
            </div>
        </header>
    );
}
