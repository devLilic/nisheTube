import { Form } from '@inertiajs/react';
import { Heart, HeartOff } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
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
import type { LibraryContext, LibraryTargetType } from '@/types';

export function FavoriteToggle({
    library,
    targetType,
    targetReference,
    label,
    compact = false,
    context = 'favorite',
    primary = false,
}: {
    library: LibraryContext;
    targetType: LibraryTargetType;
    targetReference: string;
    label: string;
    compact?: boolean;
    context?: 'favorite' | 'shortlist';
    primary?: boolean;
}) {
    const [confirming, setConfirming] = useState(false);
    const favorite = library.favorites.find(
        (item) =>
            item.target_type === targetType &&
            item.target_reference === targetReference,
    );

    if (!favorite) {
        return (
            <Form
                action="/library/favorites"
                method="post"
                disableWhileProcessing
            >
                {({ processing }) => (
                    <>
                        <input
                            type="hidden"
                            name="target_type"
                            value={targetType}
                        />
                        <input
                            type="hidden"
                            name="target_reference"
                            value={targetReference}
                        />
                        <Button
                            type="submit"
                            size={compact ? 'icon' : 'default'}
                            variant={primary ? 'default' : 'outline'}
                            disabled={processing}
                            aria-label={`Add ${label} to ${context === 'shortlist' ? 'shortlist' : 'favorites'}`}
                            title={
                                compact
                                    ? `Add to ${context === 'shortlist' ? 'shortlist' : 'favorites'}`
                                    : undefined
                            }
                        >
                            {processing ? (
                                <Spinner />
                            ) : (
                                <Heart aria-hidden="true" />
                            )}
                            {!compact &&
                                (context === 'shortlist'
                                    ? 'Add to shortlist'
                                    : 'Favorite')}
                        </Button>
                    </>
                )}
            </Form>
        );
    }

    return (
        <>
            <Button
                type="button"
                size={compact ? 'icon' : 'default'}
                variant="secondary"
                onClick={() => setConfirming(true)}
                aria-label={`Remove ${label} from ${context === 'shortlist' ? 'shortlist' : 'favorites'}`}
                title={
                    compact
                        ? `Remove from ${context === 'shortlist' ? 'shortlist' : 'favorites'}`
                        : undefined
                }
            >
                <Heart className="fill-current" aria-hidden="true" />
                {!compact &&
                    (context === 'shortlist' ? 'Shortlisted' : 'Favorited')}
            </Button>
            <Dialog open={confirming} onOpenChange={setConfirming}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Remove{' '}
                            {context === 'shortlist'
                                ? 'shortlist item'
                                : 'favorite'}
                            ?
                        </DialogTitle>
                        <DialogDescription>
                            Remove “{label}” from{' '}
                            {context === 'shortlist'
                                ? 'the Shortlist'
                                : 'Favorites'}
                            ? Its underlying research data will not be deleted.
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
                                    {processing ? <Spinner /> : <HeartOff />}
                                    Remove{' '}
                                    {context === 'shortlist'
                                        ? 'from shortlist'
                                        : 'favorite'}
                                </Button>
                            )}
                        </Form>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
