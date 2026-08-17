import { Link } from '@inertiajs/react';
import {
    Binoculars,
    Compass,
    FileDown,
    FolderKanban,
    Heart,
    History,
    LayoutDashboard,
    Lightbulb,
    PanelsTopLeft,
    Scale,
    ScanSearch,
    SearchCheck,
    Settings,
    Telescope,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { create as createResearch } from '@/routes/research';
import type { NavGroup } from '@/types';

const navigationGroups: NavGroup[] = [
    {
        label: 'Research',
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
            {
                title: 'Discover themes',
                href: '/discover',
                icon: Compass,
                activePaths: ['/discover'],
            },
            {
                title: 'Validate a niche',
                href: createResearch(),
                icon: SearchCheck,
                activePaths: ['/search', '/research'],
            },
            {
                title: 'Explore',
                href: '/explore',
                icon: Binoculars,
                activePaths: ['/explore'],
            },
            {
                title: 'Compare niches',
                href: '/analyzer/compare',
                icon: Scale,
                activePaths: ['/analyzer/compare'],
            },
            { title: 'History', href: '/history', icon: History },
        ],
    },
    {
        label: 'Library',
        items: [
            { title: 'Shortlist', href: '/favorites', icon: Heart },
            {
                title: 'Topic Workspaces',
                href: '/topics',
                icon: PanelsTopLeft,
            },
            { title: 'Watchlist', href: '/watchlist', icon: Telescope },
            { title: 'Projects', href: '/projects', icon: FolderKanban },
            { title: 'Ideas', href: '/ideas', icon: Lightbulb },
        ],
    },
    {
        label: 'Tools',
        items: [
            {
                title: 'Analyzer',
                href: '/analyzer',
                icon: ScanSearch,
                excludedPaths: ['/analyzer/compare'],
            },
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

export function AppSidebar() {
    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain groups={navigationGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
