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
    Search,
    Settings,
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
    { title: 'Discover', href: '/discover', icon: Compass, disabled: true },
    {
        title: 'Projects',
        href: '/projects',
        icon: FolderKanban,
        disabled: true,
    },
    { title: 'Favorites', href: '/favorites', icon: Heart, disabled: true },
    { title: 'History', href: '/history', icon: History, disabled: true },
    { title: 'Exports', href: '/exports', icon: FileDown, disabled: true },
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
