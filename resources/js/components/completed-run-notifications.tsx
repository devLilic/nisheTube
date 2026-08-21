import { Link, router, usePage } from '@inertiajs/react';
import { Bell, BellDot, CheckCheck } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

export function CompletedRunNotifications() {
    const { completedRunNotifications: notifications, auth } = usePage().props;

    if (!notifications) {
        return null;
    }

    const Icon = notifications.unread_count > 0 ? BellDot : Bell;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="relative"
                    aria-label={`Notifications${notifications.unread_count ? `, ${notifications.unread_count} unread` : ''}`}
                >
                    <Icon className="size-4" />
                    {notifications.unread_count > 0 && (
                        <span className="absolute top-1 right-1 flex size-4 items-center justify-center rounded-full bg-primary text-[0.6rem] font-semibold text-primary-foreground">
                            {Math.min(9, notifications.unread_count)}
                        </span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-80">
                <DropdownMenuLabel className="flex items-center justify-between gap-3">
                    <span>Notifications</span>
                    {notifications.unread_count > 0 && (
                        <Button
                            variant="ghost"
                            size="sm"
                            className="h-7 px-2 text-xs"
                            onClick={() =>
                                router.patch(
                                    '/completed-run-notifications/read',
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <CheckCheck /> Mark all read
                        </Button>
                    )}
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                {notifications.items.length === 0 ? (
                    <p className="px-3 py-5 text-center text-sm text-muted-foreground">
                        No notifications yet.
                    </p>
                ) : (
                    notifications.items.map((item) => (
                        <DropdownMenuItem
                            key={`${item.href}-${item.completed_at}`}
                            asChild
                        >
                            <Link
                                href={item.href}
                                className="items-start py-2.5"
                            >
                                <span
                                    className={`mt-1 size-2 shrink-0 rounded-full ${item.unread ? 'bg-primary' : 'bg-muted-foreground/30'}`}
                                    aria-hidden="true"
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-medium">
                                        {item.title}
                                    </span>
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {item.description}
                                    </span>
                                    <span className="block text-[0.7rem] text-muted-foreground">
                                        {new Intl.DateTimeFormat('en', {
                                            dateStyle: 'medium',
                                            timeStyle: 'short',
                                            timeZone: auth.user.timezone,
                                        }).format(new Date(item.completed_at))}
                                    </span>
                                </span>
                                <span className="sr-only">
                                    {item.unread ? 'Unread' : 'Read'}
                                </span>
                            </Link>
                        </DropdownMenuItem>
                    ))
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
