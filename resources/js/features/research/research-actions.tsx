import { Link } from '@inertiajs/react';
import {
    BarChart3,
    ChevronDown,
    Compass,
    FileDown,
    History,
    ListChecks,
    PanelsTopLeft,
    RefreshCw,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { FavoriteToggle } from '@/features/library/favorite-toggle';
import type { LibraryContext } from '@/types';

export function ResearchActions({
    library,
    runPublicId,
    queryText,
}: {
    library: LibraryContext;
    runPublicId: string;
    queryText: string;
}) {
    return (
        <div className="flex flex-wrap gap-2">
            <FavoriteToggle
                library={library}
                targetType="research_run"
                targetReference={runPublicId}
                label={queryText}
                context="shortlist"
                primary
            />
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">
                        More actions <ChevronDown aria-hidden="true" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-64">
                    <DropdownMenuItem asChild>
                        <Link href="/shortlist">
                            <ListChecks aria-hidden="true" /> Open shortlist
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuLabel>
                        Stored-evidence handoffs
                    </DropdownMenuLabel>
                    <DropdownMenuItem asChild>
                        <Link
                            href={`/discover?source_run=${encodeURIComponent(runPublicId)}`}
                        >
                            <Compass aria-hidden="true" /> Discover related
                            themes
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link
                            href={`/history?anchor=${encodeURIComponent(runPublicId)}`}
                        >
                            <BarChart3 aria-hidden="true" /> Compare snapshots
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link
                            href={`/search?repeat=${encodeURIComponent(runPublicId)}`}
                        >
                            <RefreshCw aria-hidden="true" /> Repeat snapshot
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link href="/search">
                            <BarChart3 aria-hidden="true" /> Validate another
                            idea
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <a href="#research-workspace-handoff">
                            <PanelsTopLeft aria-hidden="true" /> Add to
                            workspace
                        </a>
                    </DropdownMenuItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem asChild>
                        <Link
                            href={`/exports?run=${encodeURIComponent(runPublicId)}`}
                        >
                            <FileDown aria-hidden="true" /> Export this run
                        </Link>
                    </DropdownMenuItem>
                    <DropdownMenuItem asChild>
                        <Link
                            href={`/history?anchor=${encodeURIComponent(runPublicId)}`}
                        >
                            <History aria-hidden="true" /> Open run history
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}
