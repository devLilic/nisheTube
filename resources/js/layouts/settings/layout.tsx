import { Link } from '@inertiajs/react';
import { Palette, ShieldCheck, UserRound, Youtube } from 'lucide-react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editYouTube } from '@/routes/youtube';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: UserRound,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: ShieldCheck,
    },
    {
        title: 'YouTube API',
        href: editYouTube(),
        icon: Youtube,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: Palette,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="px-4 py-6 sm:px-6 lg:px-8">
            <Heading
                title="Account settings"
                description="Manage your identity, research preferences, security, and workspace appearance."
            />

            <div className="mt-8 flex flex-col gap-8 xl:flex-row xl:items-start">
                <aside className="w-full xl:sticky xl:top-6 xl:w-56 xl:shrink-0">
                    <nav
                        className="grid grid-cols-2 gap-1 rounded-xl border bg-card p-1.5 shadow-xs lg:grid-cols-4 xl:flex xl:flex-col"
                        aria-label="Settings"
                    >
                        {sidebarNavItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn(
                                    'w-full justify-center px-2 xl:justify-start xl:px-3',
                                    {
                                        'bg-accent text-accent-foreground':
                                            isCurrentOrParentUrl(item.href),
                                    },
                                )}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="size-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="xl:hidden" />

                <div className="min-w-0 flex-1 xl:max-w-4xl">
                    <section className="space-y-6">{children}</section>
                </div>
            </div>
        </div>
    );
}
