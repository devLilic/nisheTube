import { Link } from '@inertiajs/react';
import {
    Beaker,
    Compass,
    FileDown,
    FolderKanban,
    Gauge,
    Heart,
    History,
    LayoutDashboard,
    Lightbulb,
    Search,
    ScanSearch,
    Settings,
    Telescope,
    PanelsTopLeft,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
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
import type { NavItem } from '@/types';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutDashboard,
    },
    { title: 'Search', href: createResearch(), icon: Search },
    { title: 'Explore', href: '/explore', icon: Compass },
    { title: 'Analyzer', href: '/analyzer', icon: ScanSearch },
    { title: 'Watchlist', href: '/watchlist', icon: Telescope },
    { title: 'Topic Workspaces', href: '/topics', icon: PanelsTopLeft },
    { title: 'Discover', href: '/discover', icon: Compass },
    {
        title: 'Projects',
        href: '/projects',
        icon: FolderKanban,
    },
    { title: 'Favorites', href: '/favorites', icon: Heart },
    { title: 'Ideas', href: '/ideas', icon: Lightbulb },
    { title: 'History', href: '/history', icon: History },
    { title: 'Exports', href: '/exports', icon: FileDown },
    { title: 'Settings', href: '/settings/profile', icon: Settings },
    { title: 'UI showcase', href: '/design-system', icon: Beaker },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Local quota estimate',
        href: '/design-system#status-and-feedback',
        icon: Gauge,
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
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
