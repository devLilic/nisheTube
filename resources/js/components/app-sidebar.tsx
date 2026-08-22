import { Link, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Compass,
    FileDown,
    FolderKanban,
    Heart,
    History,
    LayoutDashboard,
    Lightbulb,
    PanelsTopLeft,
    ScanSearch,
    SearchCheck,
    Settings,
    Telescope,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { GlassNavigationRail } from '@/components/liquid-glass';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { create as createResearch } from '@/routes/research';
import type { NavItem } from '@/types';

export type JourneyNavGroup = {
    label: 'Discover' | 'Validate' | 'Analyze' | 'Organize' | 'Manage';
    items: NavItem[];
};

export const navigationGroups: JourneyNavGroup[] = [
    {
        label: 'Discover',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
            { title: 'Discovery', href: '/discover', icon: Compass },
            { title: 'Explore', href: '/explore', icon: SearchCheck },
        ],
    },
    {
        label: 'Validate',
        items: [
            {
                title: 'New research',
                href: createResearch(),
                icon: SearchCheck,
                activePaths: ['/search', '/research'],
            },
            { title: 'History', href: '/history', icon: History },
            {
                title: 'Compare niches',
                href: '/history',
                icon: History,
                activePaths: ['/history/compare'],
            },
        ],
    },
    {
        label: 'Analyze',
        items: [
            {
                title: 'Analyzer',
                href: '/analyzer',
                icon: ScanSearch,
                excludedPaths: ['/analyzer/compare'],
            },
            {
                title: 'Compare channels',
                href: '/analyzer/compare',
                icon: PanelsTopLeft,
            },
        ],
    },
    {
        label: 'Organize',
        items: [
            { title: 'Shortlist', href: '/shortlist', icon: Heart },
            { title: 'Favorites', href: '/favorites', icon: Heart },
            { title: 'Projects', href: '/projects', icon: FolderKanban },
            { title: 'Topics', href: '/topics', icon: PanelsTopLeft },
            { title: 'Watchlist', href: '/watchlist', icon: Telescope },
            { title: 'Ideas', href: '/ideas', icon: Lightbulb },
        ],
    },
    {
        label: 'Manage',
        items: [
            { title: 'Exports', href: '/exports', icon: FileDown },
            {
                title: 'Settings',
                href: '/settings/profile',
                icon: Settings,
                activePaths: ['/settings'],
            },
        ],
    },
];

const path = (url: string) =>
    new URL(
        url,
        typeof window === 'undefined'
            ? 'http://localhost'
            : window.location.origin,
    ).pathname;
const matches = (current: string, candidate: string) =>
    current === candidate || current.startsWith(`${candidate}/`);
const isActive = (item: NavItem, current: string) =>
    !item.excludedPaths?.some((value) => matches(current, value)) &&
    (item.activePaths ?? [String(item.href).split('?')[0]]).some((value) =>
        matches(current, value),
    );

export function ResearchNavigation({
    collapsed = false,
    onNavigate,
}: {
    collapsed?: boolean;
    onNavigate?: () => void;
}) {
    const current = path(usePage().url);

    return (
        <div className="space-y-4">
            {navigationGroups.map((group) => (
                <section key={group.label} aria-label={group.label}>
                    <p
                        className={cn(
                            'px-2 text-xs font-bold tracking-widest text-[var(--lg-ink-muted)] uppercase',
                            collapsed && 'sr-only',
                        )}
                    >
                        {group.label}
                    </p>
                    <ul className="mt-1 space-y-1">
                        {group.items.map((item) => {
                            const Icon = item.icon ?? Compass;
                            const active = isActive(item, current);
                            const link = (
                                <Link
                                    href={item.href}
                                    prefetch
                                    aria-current={active ? 'page' : undefined}
                                    onClick={onNavigate}
                                    className={cn(
                                        'relative flex min-h-11 items-center gap-3 rounded-xl px-3 text-sm font-semibold transition-colors hover:bg-primary/10 focus-visible:bg-primary/10',
                                        active &&
                                            'bg-primary/12 text-primary before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-full before:bg-primary',
                                        collapsed && 'justify-center px-0',
                                    )}
                                >
                                    <Icon className="size-5 shrink-0" />
                                    <span
                                        className={cn(
                                            'min-w-0 truncate',
                                            collapsed && 'sr-only',
                                        )}
                                    >
                                        {item.title}
                                    </span>
                                </Link>
                            );

                            return (
                                <li key={item.title}>
                                    {collapsed ? (
                                        <Tooltip>
                                            <TooltipTrigger asChild>
                                                {link}
                                            </TooltipTrigger>
                                            <TooltipContent side="right">
                                                {item.title}
                                            </TooltipContent>
                                        </Tooltip>
                                    ) : (
                                        link
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </section>
            ))}
        </div>
    );
}

export function AppSidebar({
    collapsed,
    onCollapse,
}: {
    collapsed: boolean;
    onCollapse: () => void;
}) {
    return (
        <GlassNavigationRail
            className={cn(
                'hidden h-[calc(100vh-2rem)] shrink-0 self-start overflow-y-auto lg:sticky lg:top-4 lg:block',
                collapsed ? 'w-[72px]' : 'w-[248px]',
            )}
        >
            <div
                className={cn(
                    'mb-5 flex items-center',
                    collapsed ? 'justify-center' : 'justify-between',
                )}
            >
                <Link href={dashboard()} prefetch aria-label="Dashboard">
                    <AppLogo />
                </Link>
                {collapsed ? (
                    <button
                        type="button"
                        onClick={onCollapse}
                        className="flex min-h-11 min-w-11 items-center justify-center rounded-xl hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring"
                        aria-label="Expand navigation"
                    >
                        <ChevronRight className="size-5" />
                    </button>
                ) : (
                    <button
                        type="button"
                        onClick={onCollapse}
                        className="flex min-h-11 min-w-11 items-center justify-center rounded-xl hover:bg-primary/10 focus-visible:ring-2 focus-visible:ring-ring"
                        aria-label="Collapse navigation"
                    >
                        <ChevronLeft className="size-5" />
                    </button>
                )}
            </div>
            <ResearchNavigation collapsed={collapsed} />
        </GlassNavigationRail>
    );
}
