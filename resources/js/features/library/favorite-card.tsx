import { Form, Link } from '@inertiajs/react';
import { ExternalLink, FolderInput, Save, Tag, Trash2, X } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';
import type { LibraryContext, LibraryFavorite } from '@/types';

export function FavoriteCard({
    favorite,
    library,
}: {
    favorite: LibraryFavorite;
    library: LibraryContext;
}) {
    const [confirming, setConfirming] = useState(false);
    const [tagId, setTagId] = useState('');
    const external = favorite.href?.startsWith('http');

    return (
        <Card className="h-full">
            <CardHeader className="gap-3">
                <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0">
                        <Badge variant="secondary" className="mb-2 capitalize">
                            {favorite.target_type.replaceAll('_', ' ')}
                        </Badge>
                        <CardTitle
                            className="line-clamp-2 text-base"
                            title={favorite.label}
                        >
                            {favorite.href ? (
                                external ? (
                                    <a
                                        href={favorite.href}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        className="hover:text-primary hover:underline"
                                    >
                                        {favorite.label}{' '}
                                        <ExternalLink className="inline size-3.5" />
                                    </a>
                                ) : (
                                    <Link
                                        href={favorite.href}
                                        className="hover:text-primary hover:underline"
                                    >
                                        {favorite.label}
                                    </Link>
                                )
                            ) : (
                                favorite.label
                            )}
                        </CardTitle>
                        <p className="mt-1 text-xs text-muted-foreground">
                            {favorite.subtitle}
                        </p>
                    </div>
                    <Button
                        size="icon"
                        variant="ghost"
                        onClick={() => setConfirming(true)}
                        aria-label={`Remove ${favorite.label}`}
                    >
                        <Trash2 aria-hidden="true" />
                    </Button>
                </div>
            </CardHeader>
            <CardContent className="space-y-4">
                <Form
                    action={`/library/favorites/${favorite.public_id}`}
                    method="patch"
                    disableWhileProcessing
                    className="space-y-3"
                >
                    {({ processing }) => (
                        <>
                            <label className="block text-xs font-medium">
                                Project
                                <select
                                    name="project_public_id"
                                    defaultValue={
                                        favorite.project?.public_id ?? ''
                                    }
                                    className="mt-1 h-9 w-full rounded-md border bg-background px-3 text-sm"
                                >
                                    <option value="">No project</option>
                                    {library.projects.map((project) => (
                                        <option
                                            key={project.public_id}
                                            value={project.public_id}
                                        >
                                            {project.name}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="block text-xs font-medium">
                                Notes
                                <textarea
                                    name="note"
                                    defaultValue={favorite.note ?? ''}
                                    rows={3}
                                    maxLength={10000}
                                    placeholder="Add research notes…"
                                    className="mt-1 w-full rounded-md border bg-background px-3 py-2 text-sm"
                                />
                            </label>
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={processing}
                            >
                                {processing ? <Spinner /> : <Save />}
                                Save details
                            </Button>
                        </>
                    )}
                </Form>

                <div>
                    <p className="mb-2 flex items-center gap-1 text-xs font-medium">
                        <Tag className="size-3.5" /> Tags
                    </p>
                    <div className="mb-2 flex flex-wrap gap-1.5">
                        {favorite.tags.length === 0 && (
                            <span className="text-xs text-muted-foreground">
                                No tags
                            </span>
                        )}
                        {favorite.tags.map((tag) => (
                            <Form
                                key={tag.public_id}
                                action={`/library/tags/${tag.public_id}/targets`}
                                method="delete"
                                disableWhileProcessing
                            >
                                <input
                                    type="hidden"
                                    name="target_type"
                                    value={favorite.target_type}
                                />
                                <input
                                    type="hidden"
                                    name="target_reference"
                                    value={favorite.target_reference}
                                />
                                <Button
                                    type="submit"
                                    size="sm"
                                    variant="secondary"
                                    className="h-7 gap-1 px-2"
                                    aria-label={`Remove ${tag.name} tag`}
                                >
                                    {tag.name}
                                    <X className="size-3" />
                                </Button>
                            </Form>
                        ))}
                    </div>
                    <div className="flex gap-2">
                        <select
                            value={tagId}
                            onChange={(event) => setTagId(event.target.value)}
                            className="h-9 min-w-0 flex-1 rounded-md border bg-background px-3 text-sm"
                            aria-label="Tag to attach"
                        >
                            <option value="">Choose tag…</option>
                            {library.tags
                                .filter(
                                    (tag) =>
                                        !favorite.tags.some(
                                            (current) =>
                                                current.public_id ===
                                                tag.public_id,
                                        ),
                                )
                                .map((tag) => (
                                    <option
                                        key={tag.public_id}
                                        value={tag.public_id}
                                    >
                                        {tag.name}
                                    </option>
                                ))}
                        </select>
                        {tagId && (
                            <Form
                                action={`/library/tags/${tagId}/targets`}
                                method="post"
                                disableWhileProcessing
                                onSuccess={() => setTagId('')}
                            >
                                <input
                                    type="hidden"
                                    name="target_type"
                                    value={favorite.target_type}
                                />
                                <input
                                    type="hidden"
                                    name="target_reference"
                                    value={favorite.target_reference}
                                />
                                <Button
                                    size="icon"
                                    variant="outline"
                                    aria-label="Attach selected tag"
                                >
                                    <FolderInput />
                                </Button>
                            </Form>
                        )}
                    </div>
                </div>
            </CardContent>

            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Remove favorite?</DialogTitle>
                        <DialogDescription>
                            Remove “{favorite.label}” from your library? The
                            underlying research record will remain available.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <DialogClose asChild>
                            <Button variant="outline">Cancel</Button>
                        </DialogClose>
                        <Form
                            action={`/library/favorites/${favorite.public_id}`}
                            method="delete"
                            disableWhileProcessing
                            onSuccess={() => setConfirming(false)}
                        >
                            {({ processing }) => (
                                <Button
                                    variant="destructive"
                                    disabled={processing}
                                >
                                    {processing ? <Spinner /> : <Trash2 />}
                                    Remove favorite
                                </Button>
                            )}
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </Card>
    );
}
