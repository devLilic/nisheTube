import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: LucideIcon | null;
    isActive?: boolean;
    disabled?: boolean;
    activePaths?: string[];
    excludedPaths?: string[];
};

export type NavGroup = {
    label: 'Research' | 'Library' | 'Tools';
    items: NavItem[];
};

export type ResearchContextOption = {
    value: string;
    label: string;
    marketKey?: string;
    project?: string | null;
};

export type ResearchContext = {
    selection: {
        market: ResearchContextOption | null;
        project: ResearchContextOption | null;
        workspace: ResearchContextOption | null;
    };
    options: {
        markets: ResearchContextOption[];
        projects: ResearchContextOption[];
        workspaces: ResearchContextOption[];
    };
    notice: string | null;
};

export type GlobalResearchSearchItem = {
    kind: 'theme' | 'video' | 'channel' | 'run';
    title: string;
    description: string;
    meta: string;
    href: string;
};

export type GlobalResearchSearchResponse = {
    query: string;
    items: GlobalResearchSearchItem[];
    total: number;
    truncated: boolean;
};

export type CompletedRunNotification = {
    title: string;
    description: string;
    href: string;
    completed_at: string;
    unread: boolean;
};

export type CompletedRunNotifications = {
    unread_count: number;
    items: CompletedRunNotification[];
};
