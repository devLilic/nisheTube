import { Form, Head, router } from '@inertiajs/react';
import { Heart, Plus, Search } from 'lucide-react';
import { StatePanel } from '@/components/data-state';
import { PageContainer } from '@/components/page-container';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { FavoriteCard } from '@/features/library/favorite-card';
import type { LibraryContext, LibraryFavorite } from '@/types';

type Props = {
    favorites: LibraryFavorite[];
    library: LibraryContext;
    filters: {
        search: string;
        type: string;
        project: string;
        tag: string;
        sort: string;
    };
};

export default function FavoritesIndex({ favorites, library, filters }: Props) {
    const update = (key: string, value: string) =>
        router.get(
            '/favorites',
            { ...filters, [key]: value },
            { preserveState: true, replace: true },
        );

    return (
        <>
            <Head title="Favorites" />
            <PageContainer>
                <PageHeader
                    eyebrow="Library"
                    title="Favorites"
                    description="Review saved niches, videos, channels, queries, and runs with project context, notes, and tags."
                />
                <Card>
                    <CardContent className="grid gap-3 pt-6 md:grid-cols-2 xl:grid-cols-5">
                        <label className="relative xl:col-span-2">
                            <span className="sr-only">
                                Search favorite notes
                            </span>
                            <Search className="absolute top-2.5 left-3 size-4 text-muted-foreground" />
                            <Input
                                defaultValue={filters.search}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        update(
                                            'search',
                                            event.currentTarget.value,
                                        );
                                    }
                                }}
                                placeholder="Search notes…"
                                className="pl-9"
                            />
                        </label>
                        <select
                            value={filters.type}
                            onChange={(event) =>
                                update('type', event.target.value)
                            }
                            className="h-9 rounded-md border bg-background px-3 text-sm"
                            aria-label="Favorite type"
                        >
                            <option value="all">All types</option>
                            <option value="niche_candidate">Niches</option>
                            <option value="video">Videos</option>
                            <option value="channel">Channels</option>
                            <option value="research_query">Queries</option>
                            <option value="research_run">Runs</option>
                        </select>
                        <select
                            value={filters.project}
                            onChange={(event) =>
                                update('project', event.target.value)
                            }
                            className="h-9 rounded-md border bg-background px-3 text-sm"
                            aria-label="Favorite project"
                        >
                            <option value="all">All projects</option>
                            {library.projects.map((project) => (
                                <option
                                    key={project.public_id}
                                    value={project.public_id}
                                >
                                    {project.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={filters.tag}
                            onChange={(event) =>
                                update('tag', event.target.value)
                            }
                            className="h-9 rounded-md border bg-background px-3 text-sm"
                            aria-label="Favorite tag"
                        >
                            <option value="all">All tags</option>
                            {library.tags.map((tag) => (
                                <option
                                    key={tag.public_id}
                                    value={tag.public_id}
                                >
                                    {tag.name}
                                </option>
                            ))}
                        </select>
                        <select
                            value={filters.sort}
                            onChange={(event) =>
                                update('sort', event.target.value)
                            }
                            className="h-9 rounded-md border bg-background px-3 text-sm"
                        >
                            <option value="updated">Recently updated</option>
                            <option value="oldest">Oldest</option>
                        </select>
                        <Form
                            action="/library/tags"
                            method="post"
                            disableWhileProcessing
                            className="flex gap-2 md:col-span-2 xl:col-span-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="flex-1">
                                        <Input
                                            name="name"
                                            placeholder="Create a reusable tag…"
                                            maxLength={80}
                                        />
                                        <p className="text-xs text-destructive">
                                            {errors.name}
                                        </p>
                                    </div>
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        {processing ? <Spinner /> : <Plus />}Add
                                        tag
                                    </Button>
                                </>
                            )}
                        </Form>
                    </CardContent>
                </Card>
                {favorites.length === 0 ? (
                    <StatePanel
                        title="No favorites found"
                        description="Favorite a research run, analysis row, or discovery candidate, or adjust these filters."
                        icon={Heart}
                        action={
                            <Button
                                variant="outline"
                                onClick={() => router.get('/search')}
                            >
                                Start research
                            </Button>
                        }
                    />
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                        {favorites.map((favorite) => (
                            <FavoriteCard
                                key={favorite.public_id}
                                favorite={favorite}
                                library={library}
                            />
                        ))}
                    </div>
                )}
            </PageContainer>
        </>
    );
}

FavoritesIndex.layout = {
    breadcrumbs: [{ title: 'Favorites', href: '/favorites' }],
};
