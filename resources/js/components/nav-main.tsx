import { Link, usePage } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { toUrl } from '@/lib/utils';
import type { NavGroup, NavItem } from '@/types';

const pathMatches = (currentPath: string, candidate: string) =>
    currentPath === candidate || currentPath.startsWith(`${candidate}/`);

const isActive = (item: NavItem, currentPath: string) => {
    if (item.excludedPaths?.some((path) => pathMatches(currentPath, path))) {
        return false;
    }

    const paths = item.activePaths ?? [toUrl(item.href).split('?')[0]];

    return paths.some((path) => pathMatches(currentPath, path));
};

export function NavMain({ groups }: { groups: NavGroup[] }) {
    const { setOpenMobile } = useSidebar();
    const currentPath = new URL(
        usePage().url,
        typeof window === 'undefined'
            ? 'http://localhost'
            : window.location.origin,
    ).pathname;

    return (
        <nav aria-label="Primary navigation">
            {groups.map((group) => (
                <SidebarGroup className="px-2 py-1" key={group.label}>
                    <SidebarGroupLabel>{group.label}</SidebarGroupLabel>
                    <SidebarMenu>
                        {group.items.map((item) => {
                            const active = isActive(item, currentPath);

                            return (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={active}
                                        tooltip={{ children: item.title }}
                                        className="min-w-0"
                                    >
                                        <Link
                                            href={item.href}
                                            prefetch
                                            aria-current={
                                                active ? 'page' : undefined
                                            }
                                            onClick={() => setOpenMobile(false)}
                                        >
                                            {item.icon && <item.icon />}
                                            <span title={item.title}>
                                                {item.title}
                                            </span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            );
                        })}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </nav>
    );
}
