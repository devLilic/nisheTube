import { Link } from '@inertiajs/react';
import {
    CircleAlert,
    FileClock,
    Hash,
    LoaderCircle,
    Search,
    Tv,
    Video,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import type {
    GlobalResearchSearchItem,
    GlobalResearchSearchResponse,
} from '@/types';

const DEBOUNCE_MS = 300;
const MIN_QUERY_LENGTH = 2;

const icons = {
    theme: Hash,
    video: Video,
    channel: Tv,
    run: FileClock,
};

export function GlobalResearchSearch() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [state, setState] = useState<
        'initial' | 'loading' | 'success' | 'empty' | 'error'
    >('initial');
    const [response, setResponse] =
        useState<GlobalResearchSearchResponse | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        window.requestAnimationFrame(() => inputRef.current?.focus());
    }, [open]);

    useEffect(() => {
        const normalized = query.trim();

        if (!open || normalized.length < MIN_QUERY_LENGTH) {
            return;
        }

        const controller = new AbortController();
        const timeout = window.setTimeout(async () => {
            setState('loading');

            try {
                const result = await fetch(
                    `/global-research-search?q=${encodeURIComponent(normalized)}`,
                    {
                        signal: controller.signal,
                        headers: { Accept: 'application/json' },
                    },
                );

                if (!result.ok) {
                    throw new Error('Stored-data search failed.');
                }

                const data =
                    (await result.json()) as GlobalResearchSearchResponse;

                setResponse(data);
                setState(data.items.length === 0 ? 'empty' : 'success');
            } catch (error) {
                if ((error as Error).name !== 'AbortError') {
                    setState('error');
                }
            }
        }, DEBOUNCE_MS);

        return () => {
            window.clearTimeout(timeout);
            controller.abort();
        };
    }, [open, query]);

    const visibleState =
        query.trim().length < MIN_QUERY_LENGTH ? 'initial' : state;

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    variant="outline"
                    className="min-w-11 flex-1 justify-start gap-2 bg-background text-muted-foreground sm:min-w-52 lg:max-w-sm"
                    aria-label="Search stored research"
                >
                    <Search className="size-4" />
                    <span className="hidden truncate sm:inline">
                        Search themes, videos, channels, runs
                    </span>
                </Button>
            </DialogTrigger>
            <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-2xl">
                <DialogHeader className="border-b p-5 pb-4">
                    <DialogTitle>Search stored research</DialogTitle>
                    <DialogDescription>
                        Owner-private stored data only. Searching never calls
                        YouTube.
                    </DialogDescription>
                </DialogHeader>
                <div className="border-b p-4">
                    <label className="sr-only" htmlFor="global-research-query">
                        Search themes, videos, channels, and runs
                    </label>
                    <div className="relative">
                        <Search className="absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            ref={inputRef}
                            id="global-research-query"
                            value={query}
                            maxLength={80}
                            onChange={(event) => setQuery(event.target.value)}
                            placeholder="Type at least 2 characters…"
                            className="pl-9"
                            aria-describedby="global-research-status"
                        />
                    </div>
                </div>
                <div className="max-h-[28rem] min-h-48 overflow-y-auto p-2">
                    <SearchState
                        state={visibleState}
                        response={response}
                        onNavigate={() => setOpen(false)}
                    />
                </div>
                <p
                    id="global-research-status"
                    className="sr-only"
                    role="status"
                    aria-live="polite"
                >
                    {visibleState === 'loading'
                        ? 'Searching stored research.'
                        : visibleState === 'initial'
                          ? 'Enter at least two characters.'
                          : response
                            ? `${response.total} results found.`
                            : 'No results found.'}
                </p>
            </DialogContent>
        </Dialog>
    );
}

function SearchState({
    state,
    response,
    onNavigate,
}: {
    state: 'initial' | 'loading' | 'success' | 'empty' | 'error';
    response: GlobalResearchSearchResponse | null;
    onNavigate: () => void;
}) {
    if (state === 'initial') {
        return (
            <Message
                icon={Search}
                text="Start typing to search up to 20 stored results."
            />
        );
    }

    if (state === 'loading') {
        return (
            <Message
                icon={LoaderCircle}
                text="Searching stored research…"
                spinning
            />
        );
    }

    if (state === 'error') {
        return (
            <Message
                icon={CircleAlert}
                text="Stored research could not be searched. Try again."
            />
        );
    }

    if (state === 'empty') {
        return (
            <Message
                icon={Search}
                text={`No stored results match “${response?.query ?? ''}”.`}
            />
        );
    }

    return (
        <div role="listbox" aria-label="Stored research results">
            {response?.items.map((item, index) => (
                <SearchResult
                    key={`${item.kind}-${item.href}-${index}`}
                    item={item}
                    onNavigate={onNavigate}
                />
            ))}
            {response?.truncated && (
                <p className="px-3 py-2 text-xs text-muted-foreground">
                    Showing the first 20 bounded results. Refine your search for
                    a narrower match.
                </p>
            )}
        </div>
    );
}

function SearchResult({
    item,
    onNavigate,
}: {
    item: GlobalResearchSearchItem;
    onNavigate: () => void;
}) {
    const Icon = icons[item.kind];

    return (
        <Link
            href={item.href}
            onClick={onNavigate}
            role="option"
            className="flex items-start gap-3 rounded-md px-3 py-3 outline-none hover:bg-accent focus-visible:bg-accent focus-visible:ring-2 focus-visible:ring-ring"
        >
            <span className="mt-0.5 rounded-md border bg-background p-2">
                <Icon className="size-4" />
            </span>
            <span className="min-w-0 flex-1">
                <span
                    className="block truncate text-sm font-medium"
                    title={item.title}
                >
                    {item.title}
                </span>
                <span
                    className="block truncate text-xs text-muted-foreground"
                    title={item.description}
                >
                    {item.description}
                </span>
            </span>
            <span className="shrink-0 text-[0.7rem] text-muted-foreground">
                {item.meta}
            </span>
        </Link>
    );
}

function Message({
    icon: Icon,
    text,
    spinning = false,
}: {
    icon: typeof Search;
    text: string;
    spinning?: boolean;
}) {
    return (
        <div className="flex min-h-44 flex-col items-center justify-center gap-3 px-6 text-center text-sm text-muted-foreground">
            <Icon className={`size-6 ${spinning ? 'animate-spin' : ''}`} />
            <p>{text}</p>
        </div>
    );
}
